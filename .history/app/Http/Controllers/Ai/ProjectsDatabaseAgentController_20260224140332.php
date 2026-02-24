<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Agents\ProjectsDatabaseAgent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProjectsDatabaseAgentController extends Controller
{
    public function __invoke(Request $request): StreamedResponse|JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable', 'uuid'],
            'provider' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'stream' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $agent = ProjectsDatabaseAgent::make();

        if (! empty($data['conversation_id'])) {
            $isValidConversation = DB::table('agent_conversations')
                ->where('id', $data['conversation_id'])
                ->where('user_id', $user?->id)
                ->exists();

            if (! $isValidConversation) {
                return response()->json([
                    'message' => 'Invalid conversation_id for this user.',
                ], 422);
            }

            $agent->continue($data['conversation_id'], $user);
        } else {
            $agent->forUser($user);
        }

        $shouldStream = $data['stream'] ?? false;

        if ($shouldStream) {
            return $this->streamResponse($agent, $data);
        }

        try {
            $response = $agent->prompt(
                prompt: $data['question'],
                provider: $data['provider'] ?? null,
                model: $data['model'] ?? null,
            );
        } catch (Throwable $e) {
            $message = strtolower($e->getMessage());
            $requestedProvider = $data['provider'] ?? config('ai.default', 'ollama');

            $canRetryWithToolsModel = str_contains($message, 'does not support tools')
                && $requestedProvider === 'ollama';

            if (! $canRetryWithToolsModel) {
                throw $e;
            }

            // retry using the configured tools model (smaller) if available
            $response = $agent->prompt(
                prompt: $data['question'],
                provider: 'ollama',
                model: config('ai.providers.ollama.models.text.tools_default')
                    ?? config('ai.models.text.tools_default', 'llama3.2:1b'),
            );
        }

        return response()->json([
            'answer' => $response->text,
            'conversation_id' => $response->conversationId,
            'usage' => $response->usage->toArray(),
        ]);
    }

    protected function streamResponse(ProjectsDatabaseAgent $agent, array $data): StreamedResponse
    {
        return response()->stream(function () use ($agent, $data) {
            try {
                $stream = $agent->promptStreaming(
                    prompt: $data['question'],
                    provider: $data['provider'] ?? null,
                    model: $data['model'] ?? null,
                );

                foreach ($stream as $chunk) {
                    $json = json_encode([
                        'content' => $chunk->text,
                        'conversation_id' => $chunk->conversationId ?? null,
                    ]);
                    echo "data: {$json}\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            } catch (Throwable $e) {
                $message = strtolower($e->getMessage());
                $requestedProvider = $data['provider'] ?? config('ai.default', 'ollama');

                $canRetryWithToolsModel = str_contains($message, 'does not support tools')
                    && $requestedProvider === 'ollama';

                if (! $canRetryWithToolsModel) {
                    $error = json_encode(['error' => $e->getMessage()]);
                    echo "data: {$error}\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    return;
                }

                // retry with tools model
                $stream = $agent->promptStreaming(
                    prompt: $data['question'],
                    provider: 'ollama',
                    model: config('ai.providers.ollama.models.text.tools_default')
                        ?? config('ai.models.text.tools_default', 'llama3.2:1b'),
                );

                foreach ($stream as $chunk) {
                    $json = json_encode([
                        'content' => $chunk->text,
                        'conversation_id' => $chunk->conversationId ?? null,
                    ]);
                    echo "data: {$json}\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

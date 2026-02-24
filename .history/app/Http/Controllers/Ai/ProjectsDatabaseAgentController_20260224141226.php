<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Agents\ProjectsDatabaseAgent;
use App\Events\Ai\ProjectsAgentProgress;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProjectsDatabaseAgentController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable', 'uuid'],
            'provider' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'session_id' => ['required', 'uuid'],
        ]);

        $sessionId = $data['session_id'];
        $user = $request->user();
        $agent = ProjectsDatabaseAgent::make();

        // Broadcast start event
        broadcast(new ProjectsAgentProgress(
            $sessionId,
            'start',
            'Connecting to construction database...'
        ));

        if (! empty($data['conversation_id'])) {
            $isValidConversation = DB::table('agent_conversations')
                ->where('id', $data['conversation_id'])
                ->where('user_id', $user?->id)
                ->exists();

            if (! $isValidConversation) {
                broadcast(new ProjectsAgentProgress(
                    $sessionId,
                    'error',
                    'Invalid conversation'
                ));

                return response()->json([
                    'message' => 'Invalid conversation_id for this user.',
                ], 422);
            }

            $agent->continue($data['conversation_id'], $user);
        } else {
            $agent->forUser($user);
        }

        // Broadcast analysis event
        broadcast(new ProjectsAgentProgress(
            $sessionId,
            'progress',
            'Analyzing construction project data...'
        ));

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
                broadcast(new ProjectsAgentProgress(
                    $sessionId,
                    'error',
                    'Failed to process request: ' . $e->getMessage()
                ));
                throw $e;
            }

            // Broadcast retry message
            broadcast(new ProjectsAgentProgress(
                $sessionId,
                'progress',
                'Switching to optimized model...'
            ));

            // retry using the configured tools model (smaller) if available
            $response = $agent->prompt(
                prompt: $data['question'],
                provider: 'ollama',
                model: config('ai.providers.ollama.models.text.tools_default')
                    ?? config('ai.models.text.tools_default', 'llama3.2:1b'),
            );
        }

        // Broadcast completion with content
        broadcast(new ProjectsAgentProgress(
            $sessionId,
            'response',
            'Analysis complete',
            $response->text,
            $response->conversationId
        ));

        broadcast(new ProjectsAgentProgress(
            $sessionId,
            'done',
            'Complete'
        ));

        return response()->json([
            'answer' => $response->text,
            'conversation_id' => $response->conversationId,
            'usage' => $response->usage->toArray(),
        ]);
    }
}

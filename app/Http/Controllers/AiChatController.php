<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Project;
use App\Services\OllamaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AiChatController
{
    public function index(OllamaClient $client): Response
    {
        $projects = Project::where('is_active', true)->orderBy('name')->get();
        $models = [];

        $response = $client->tags();
        if ($response->successful()) {
            $models = $response->json('models', []);
        }

        return Inertia::render('ai/chat', [
            'projects' => $projects,
            'models' => $models,
            'sessions' => ChatSession::where('user_id', auth()->id())
                ->latest('last_message_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $sessions = ChatSession::where('user_id', $request->user()->id)
            ->latest('last_message_at')
            ->limit(50)
            ->get();

        return response()->json(['sessions' => $sessions]);
    }

    public function messages(ChatSession $session, Request $request): JsonResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);

        $messages = $session->messages()
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'created_at']);

        return response()->json(['messages' => $messages]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['nullable', 'integer', 'exists:chat_sessions,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'model' => ['required', 'string'],
            'message' => ['required', 'string'],
        ]);

        $session = null;
        if (! empty($data['session_id'])) {
            $session = ChatSession::where('id', $data['session_id'])
                ->where('user_id', $request->user()->id)
                ->first();
        }

        if (! $session) {
            $session = ChatSession::create([
                'user_id' => $request->user()->id,
                'project_id' => $data['project_id'] ?? null,
                'model' => $data['model'],
                'title' => null,
                'last_message_at' => now(),
            ]);
        }

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $project = null;
        if (! empty($data['project_id'])) {
            $project = Project::find($data['project_id']);
        }

        $systemPrompt = $project
            ? sprintf('You are a helpful assistant for project "%s". Answer using only this project\'s context. If context is missing, say so.', $project->name)
            : 'You are a helpful assistant. Answer across all available project knowledge.';

        $contextMessages = $session->messages()
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();

        $payload = [
            'model' => $data['model'],
            'messages' => array_merge([['role' => 'system', 'content' => $systemPrompt]], $contextMessages),
            'stream' => false,
        ];

        $host = rtrim((string) config('ollama.host'), '/');
        $response = \Illuminate\Support\Facades\Http::timeout(0)
            ->withOptions(['http_errors' => false])
            ->post($host.'/api/chat', $payload);

        if (! $response->successful()) {
            return response()->json([
                'error' => 'Ollama chat failed.',
                'status' => $response->status(),
            ], 502);
        }

        $assistant = (string) ($response->json('message.content') ?? '');

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $assistant,
        ]);

        $session->forceFill([
            'last_message_at' => now(),
            'title' => $session->title ?? Str::limit($data['message'], 40),
        ])
            ->save();

        return response()->json([
            'session_id' => $session->id,
            'assistant' => $assistant,
        ]);
    }
}

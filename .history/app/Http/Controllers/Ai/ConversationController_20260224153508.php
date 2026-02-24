<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    /**
     * List all conversations for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $conversations = DB::table('agent_conversations')
            ->where('user_id', $request->user()->id)
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get(['id', 'title', 'created_at', 'updated_at'])
            ->map(function ($conversation) {
                // Get first user message as title if title is empty
                if (empty($conversation->title) || $conversation->title === 'Untitled') {
                    $firstMessage = DB::table('agent_conversation_messages')
                        ->where('conversation_id', $conversation->id)
                        ->where('role', 'user')
                        ->orderBy('created_at', 'asc')
                        ->value('content');

                    $title = $firstMessage ? (strlen($firstMessage) > 60 ? substr($firstMessage, 0, 60) . '...' : $firstMessage) : 'New Conversation';
                } else {
                    $title = $conversation->title;
                }

                return [
                    'id' => $conversation->id,
                    'title' => $title,
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,
                ];
            });

        return response()->json([
            'conversations' => $conversations,
        ]);
    }

    /**
     * Get messages for a specific conversation.
     */
    public function show(Request $request, string $conversationId): JsonResponse
    {
        // Verify conversation belongs to user
        $conversation = DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$conversation) {
            return response()->json([
                'message' => 'Conversation not found.',
            ], 404);
        }

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get(['role', 'content', 'created_at'])
            ->map(function ($message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at,
                ];
            });

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'created_at' => $conversation->created_at,
                'updated_at' => $conversation->updated_at,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Delete a conversation.
     */
    public function destroy(Request $request, string $conversationId): JsonResponse
    {
        $deleted = DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->where('user_id', $request->user()->id)
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'message' => 'Conversation not found.',
            ], 404);
        }

        // Messages are automatically deleted via cascade
        return response()->json([
            'message' => 'Conversation deleted successfully.',
        ]);
    }
}

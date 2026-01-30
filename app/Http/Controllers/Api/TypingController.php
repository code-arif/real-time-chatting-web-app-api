<?php

namespace App\Http\Controllers\Api;

use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\TypingIndicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TypingController extends Controller
{
    /**
     * User started typing
     */
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        // Check if user is part of the conversation
        if (!$conversation->users()->where('users.id', $request->user()->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not part of this conversation',
            ], 403);
        }

        // Update typing indicator
        TypingIndicator::updateTyping($conversation->id, $request->user()->id);

        // Broadcast typing event
        broadcast(new UserTyping($request->user(), $conversation->id, true))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Typing status updated',
        ]);
    }

    /**
     * User stopped typing
     */
    public function stopTyping(Request $request, Conversation $conversation): JsonResponse
    {
        // Clear typing indicator
        TypingIndicator::clearTyping($conversation->id, $request->user()->id);

        // Broadcast stop typing event
        broadcast(new UserTyping($request->user(), $conversation->id, false))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Stopped typing',
        ]);
    }

    /**
     * Get currently typing users
     */
    public function getCurrentlyTyping(Request $request, Conversation $conversation): JsonResponse
    {
        $typingUsers = TypingIndicator::getCurrentlyTyping(
            $conversation->id,
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'data' => $typingUsers->map(function ($indicator) {
                return [
                    'user_id' => $indicator->user->id,
                    'user_name' => $indicator->user->name,
                ];
            }),
        ]);
    }
}

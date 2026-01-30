<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Message;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Models\Conversation;
use Illuminate\Http\Request;
use App\Events\MessageDeleted;
use App\Events\MessageUpdated;
use App\Models\MessageReaction;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Events\MessageReactionToggled;
use App\Http\Resources\MessageResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\SendMessageRequest;

class MessageController extends Controller
{
    /**
     * Get messages for a conversation
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        // Check if user is part of the conversation
        if (!$conversation->users()->where('users.id', $request->user()->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this conversation',
            ], 403);
        }

        $perPage = $request->input('per_page', 50);

        $messages = $conversation->messages()
            ->with(['sender', 'replyTo.sender', 'reactions.user', 'reads.user'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => MessageResource::collection($messages),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Send a new message
     */
    public function store(SendMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        // Check if user is part of the conversation
        if (!$conversation->users()->where('users.id', $request->user()->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not part of this conversation',
            ], 403);
        }

        try {
            $messageData = [
                'conversation_id' => $validated['conversation_id'],
                'sender_id' => $request->user()->id,
                'type' => $validated['type'],
                'content' => $validated['content'] ?? null,
                'reply_to' => $validated['reply_to'] ?? null,
            ];

            // Handle media upload
            if ($request->hasFile('media')) {
                $file = $request->file('media');
                $path = $file->store('messages', 'public');

                $messageData['media_path'] = $path;
                $messageData['media_name'] = $file->getClientOriginalName();
                $messageData['media_type'] = $file->getMimeType();
                $messageData['media_size'] = $file->getSize();
            }

            $message = Message::create($messageData);

            // Update conversation's last_message_at
            $conversation->update(['last_message_at' => now()]);

            // Load relationships
            $message->load(['sender', 'replyTo.sender']);

            // Broadcast message event
            broadcast(new MessageSent($message))->toOthers();

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => new MessageResource($message),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific message
     */
    public function show(Message $message): JsonResponse
    {
        $message->load(['sender', 'replyTo.sender', 'reactions.user', 'reads.user']);

        return response()->json([
            'success' => true,
            'data' => new MessageResource($message),
        ]);
    }

    /**
     * Update a message (edit)
     */
    public function update(Request $request, Message $message): JsonResponse
    {
        // Check if user is the sender
        if ($message->sender_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit your own messages',
            ], 403);
        }

        // Only text messages can be edited
        if ($message->type !== 'text') {
            return response()->json([
                'success' => false,
                'message' => 'Only text messages can be edited',
            ], 400);
        }

        $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ]);

        $message->update([
            'content' => $request->content,
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        // Broadcast message edited event
        broadcast(new MessageUpdated($message))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Message updated successfully',
            'data' => new MessageResource($message->load(['sender'])),
        ]);
    }

    /**
     * Delete a message
     */
    public function destroy(Request $request, Message $message): JsonResponse
    {
        // Check if user is the sender
        if ($message->sender_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own messages',
            ], 403);
        }

        $conversationId = $message->conversation_id;
        $messageId = $message->id;

        // Delete media file if exists
        if ($message->media_path) {
            Storage::disk('public')->delete($message->media_path);
        }

        $message->delete();

        // Broadcast message deleted event
        broadcast(new MessageDeleted($messageId, $conversationId))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully',
        ]);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'message_ids' => ['required', 'array'],
            'message_ids.*' => ['exists:messages,id'],
        ]);

        foreach ($request->message_ids as $messageId) {
            $message = Message::find($messageId);
            if ($message && $message->sender_id !== $request->user()->id) {
                $message->markAsReadBy($request->user());
            }
        }

        // Get conversation ID from first message
        $firstMessage = Message::find($request->message_ids[0]);
        if ($firstMessage) {
            broadcast(new MessageRead(
                $request->message_ids,
                $request->user(),
                $firstMessage->conversation_id
            ))->toOthers();
        }

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read',
        ]);
    }

    /**
     * Toggle reaction on a message
     */
    public function toggleReaction(Request $request, Message $message): JsonResponse
    {
        $request->validate([
            'emoji' => ['required', 'string', 'max:10'],
        ]);

        $added = MessageReaction::toggle(
            $message->id,
            $request->user()->id,
            $request->emoji
        );

        $message->load(['reactions.user']);

        // Broadcast reaction event
        broadcast(new MessageReactionToggled(
            $message,
            $request->user(),
            $request->emoji,
            $added
        ))->toOthers();

        return response()->json([
            'success' => true,
            'message' => $added ? 'Reaction added' : 'Reaction removed',
            'data' => [
                'added' => $added,
                'reactions' => $message->grouped_reactions,
            ],
        ]);
    }

    /**
     * Get unread messages count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Message::whereHas('conversation.users', function ($query) use ($request) {
            $query->where('users.id', $request->user()->id);
        })
            ->where('sender_id', '!=', $request->user()->id)
            ->whereDoesntHave('reads', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->count();

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $count],
        ]);
    }
}

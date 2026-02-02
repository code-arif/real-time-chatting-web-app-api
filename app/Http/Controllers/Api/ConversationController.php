<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateConversationRequest;
use App\Http\Requests\UpdateConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConversationController extends Controller
{
    /**
     * Get all conversations for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $conversations = Conversation::query()
            ->whereHas('users', function ($query) use ($request) {
                $query->where('users.id', $request->user()->id);
            })
            ->with(['latestMessage.sender', 'users'])
            ->withCount('users')
            // ->orderBy('last_message_at', 'desc')
            // ->orderBy('id', 'desc')
            ->get();

        // $conversations = Conversation::query()
        //     ->whereHas(
        //         'users',
        //         fn($q) =>
        //         $q->where('users.id', $request->user()->id)
        //     )
        //     ->with(['latestMessage.sender', 'users'])
        //     ->orderByRaw("COALESCE(last_message_at, created_at) DESC")
        //     ->get();

        return response()->json([
            'success' => true,
            'data' => ConversationResource::collection($conversations),
        ]);
    }

    /**
     * Create a new conversation
     */
    public function store(CreateConversationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            // Check if private conversation already exists
            if ($validated['type'] === 'private') {
                $existingConversation = $this->findExistingPrivateConversation(
                    $request->user()->id,
                    $validated['user_id']
                );

                if ($existingConversation) {
                    DB::commit();
                    return response()->json([
                        'success' => true,
                        'message' => 'Conversation already exists',
                        'data' => new ConversationResource($existingConversation->load(['latestMessage.sender', 'users'])),
                    ]);
                }
            }

            // Create conversation
            $conversation = Conversation::create([
                'type' => $validated['type'],
                'name' => $validated['name'] ?? null,
                'description' => $validated['description'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            // Handle avatar upload for group
            if ($request->hasFile('avatar') && $validated['type'] === 'group') {
                $avatarPath = $request->file('avatar')->store('conversation-avatars', 'public');
                $conversation->update(['avatar' => $avatarPath]);
            }

            // Add users to conversation
            if ($validated['type'] === 'private') {
                // Add both users
                $conversation->addUser($request->user(), 'member');
                $otherUser = User::findOrFail($validated['user_id']);
                $conversation->addUser($otherUser, 'member');
            } else {
                // Add creator as admin
                $conversation->addUser($request->user(), 'admin');

                // Add other members
                foreach ($validated['user_ids'] as $userId) {
                    if ($userId != $request->user()->id) {
                        $user = User::findOrFail($userId);
                        $conversation->addUser($user, 'member');
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Conversation created successfully',
                'data' => new ConversationResource($conversation->load(['latestMessage.sender', 'users'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create conversation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific conversation
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        // Check if user is part of the conversation
        if (!$conversation->users()->where('users.id', $request->user()->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this conversation',
            ], 403);
        }

        $conversation->load(['latestMessage.sender', 'users']);

        return response()->json([
            'success' => true,
            'data' => new ConversationResource($conversation),
        ]);
    }

    /**
     * Update conversation (group only)
     */
    public function update(UpdateConversationRequest $request, Conversation $conversation): JsonResponse
    {
        // Check if conversation is group
        if (!$conversation->isGroup()) {
            return response()->json([
                'success' => false,
                'message' => 'Only group conversations can be updated',
            ], 400);
        }

        // Check if user is admin
        if (!$conversation->isUserAdmin($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can update group details',
            ], 403);
        }

        $validated = $request->validated();

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar
            if ($conversation->avatar) {
                Storage::disk('public')->delete($conversation->avatar);
            }

            $avatarPath = $request->file('avatar')->store('conversation-avatars', 'public');
            $validated['avatar'] = $avatarPath;
        }

        $conversation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Conversation updated successfully',
            'data' => new ConversationResource($conversation->load(['latestMessage.sender', 'users'])),
        ]);
    }

    /**
     * Delete/Leave conversation
     */
    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        // Check if user is part of the conversation
        if (!$conversation->users()->where('users.id', $request->user()->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not part of this conversation',
            ], 403);
        }

        if ($conversation->isPrivate()) {
            // For private chats, just mark as left
            $conversation->removeUser($request->user());
        } else {
            // For groups
            $conversation->removeUser($request->user());

            // If no users left, delete the conversation
            if ($conversation->users()->count() === 0) {
                $conversation->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Left conversation successfully',
        ]);
    }

    /**
     * Add user to group
     */
    public function addUser(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        if (!$conversation->isGroup()) {
            return response()->json([
                'success' => false,
                'message' => 'Can only add users to group conversations',
            ], 400);
        }

        if (!$conversation->isUserAdmin($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can add users',
            ], 403);
        }

        $user = User::findOrFail($request->user_id);
        $conversation->addUser($user, 'member');

        return response()->json([
            'success' => true,
            'message' => 'User added successfully',
            'data' => new ConversationResource($conversation->load(['users'])),
        ]);
    }

    /**
     * Remove user from group
     */
    public function removeUser(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        if (!$conversation->isGroup()) {
            return response()->json([
                'success' => false,
                'message' => 'Can only remove users from group conversations',
            ], 400);
        }

        if (!$conversation->isUserAdmin($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can remove users',
            ], 403);
        }

        $user = User::findOrFail($request->user_id);
        $conversation->removeUser($user);

        return response()->json([
            'success' => true,
            'message' => 'User removed successfully',
            'data' => new ConversationResource($conversation->load(['users'])),
        ]);
    }

    /**
     * Make user admin
     */
    public function makeAdmin(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        if (!$conversation->isGroup()) {
            return response()->json([
                'success' => false,
                'message' => 'Admin role only applies to groups',
            ], 400);
        }

        if (!$conversation->isUserAdmin($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can promote users',
            ], 403);
        }

        $conversation->users()->updateExistingPivot($request->user_id, [
            'role' => 'admin',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User promoted to admin',
        ]);
    }

    /**
     * Mute/Unmute conversation
     */
    public function toggleMute(Request $request, Conversation $conversation): JsonResponse
    {
        $pivot = $conversation->users()->where('users.id', $request->user()->id)->first()->pivot;
        $newMuteStatus = !$pivot->is_muted;

        $conversation->users()->updateExistingPivot($request->user()->id, [
            'is_muted' => $newMuteStatus,
        ]);

        return response()->json([
            'success' => true,
            'message' => $newMuteStatus ? 'Conversation muted' : 'Conversation unmuted',
            'data' => ['is_muted' => $newMuteStatus],
        ]);
    }

    /**
     * Archive/Unarchive conversation
     */
    public function toggleArchive(Request $request, Conversation $conversation): JsonResponse
    {
        $pivot = $conversation->users()->where('users.id', $request->user()->id)->first()->pivot;
        $newArchiveStatus = !$pivot->is_archived;

        $conversation->users()->updateExistingPivot($request->user()->id, [
            'is_archived' => $newArchiveStatus,
        ]);

        return response()->json([
            'success' => true,
            'message' => $newArchiveStatus ? 'Conversation archived' : 'Conversation unarchived',
            'data' => ['is_archived' => $newArchiveStatus],
        ]);
    }

    /**
     * Find existing private conversation between two users
     */
    private function findExistingPrivateConversation(int $userId1, int $userId2): ?Conversation
    {
        return Conversation::private()
            ->whereHas('users', function ($query) use ($userId1) {
                $query->where('users.id', $userId1);
            })
            ->whereHas('users', function ($query) use ($userId2) {
                $query->where('users.id', $userId2);
            })
            ->first();
    }
}

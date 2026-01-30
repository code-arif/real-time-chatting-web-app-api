<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Events\UserStatusChanged;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\UpdateProfileRequest;

class ProfileController extends Controller
{
    /**
     * Get user profile
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user()),
        ]);
    }

    /**
     * Update user profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Store new avatar
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $avatarPath;
        }

        // Update user
        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Update user status
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:online,offline,away'],
        ]);

        $user = $request->user();
        $user->update([
            'status' => $request->status,
            'last_seen_at' => now(),
        ]);

        // Broadcast status change
        broadcast(new UserStatusChanged($user));

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Remove avatar
     */
    public function removeAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Avatar removed successfully',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update push subscription
     */
    public function updatePushSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'subscription' => ['required', 'json'],
        ]);

        $user = $request->user();
        $user->update([
            'push_subscription' => $request->subscription,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Push subscription updated successfully',
        ]);
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->getNameForUser($currentUser),
            'avatar' => $this->getAvatarForUser($currentUser),
            'description' => $this->when($this->isGroup(), $this->description),
            'is_group' => $this->isGroup(),
            'created_by' => $this->when($this->isGroup(), $this->created_by),
            'last_message' => new MessageResource($this->whenLoaded('latestMessage')),
            'last_message_at' => $this->last_message_at?->toISOString(),
            'unread_count' => $this->when(
                $request->user(),
                fn() => $this->getUnreadCountForUser($currentUser)
            ),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'users_count' => $this->when($this->isGroup(), $this->users_count ?? $this->users->count()),
            'pivot' => $this->when(
                $this->relationLoaded('users'),
                fn() => [
                    'role' => $this->pivot?->role,
                    'is_muted' => $this->pivot?->is_muted ?? false,
                    'is_archived' => $this->pivot?->is_archived ?? false,
                    'joined_at' => $this->pivot?->joined_at,
                ]
            ),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}

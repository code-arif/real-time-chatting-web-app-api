<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar_url,
            'bio' => $this->bio,
            'phone' => $this->phone,
            'status' => $this->status,
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'is_online' => $this->isOnline(),
            'unread_messages_count' => $this->when(
                $request->user() && $request->user()->id === $this->id,
                fn() => $this->unread_messages_count
            ),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}

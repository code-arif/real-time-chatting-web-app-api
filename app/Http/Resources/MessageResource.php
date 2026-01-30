<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender' => new UserResource($this->whenLoaded('sender')),
            'sender_id' => $this->sender_id,
            'type' => $this->type,
            'content' => $this->content,
            'media_url' => $this->media_url,
            'media_name' => $this->media_name,
            'media_type' => $this->media_type,
            'media_size' => $this->media_size,
            'formatted_size' => $this->formatted_size,
            'reply_to' => $this->when($this->reply_to, function () {
                return new MessageResource($this->replyTo);
            }),
            'is_edited' => $this->is_edited,
            'edited_at' => $this->edited_at?->toISOString(),
            'reactions' => $this->whenLoaded('reactions', function () {
                return $this->grouped_reactions;
            }),
            'read_by' => $this->whenLoaded('reads', function () use ($request) {
                return $this->reads->map(function ($read) {
                    return [
                        'user_id' => $read->user_id,
                        'user_name' => $read->user->name,
                        'read_at' => $read->read_at->toISOString(),
                    ];
                });
            }),
            'is_read' => $this->when(
                $request->user(),
                fn() => $this->isReadBy($request->user())
            ),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

<?php

namespace App\Models;

use App\Models\User;
use App\Models\Conversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'type',
        'content',
        'media_path',
        'media_name',
        'media_type',
        'media_size',
        'reply_to',
        'is_edited',
        'edited_at',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
    ];

    protected $appends = [
        'media_url',
    ];

    /**
     * Get the conversation this message belongs to
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender of this message
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the message this is replying to
     */
    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to');
    }

    /**
     * Get all reactions to this message
     */
    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    /**
     * Get all reads for this message
     */
    public function reads()
    {
        return $this->hasMany(MessageRead::class);
    }

    /**
     * Get grouped reactions (emoji => count)
     */
    public function getGroupedReactionsAttribute()
    {
        return $this->reactions()
            ->select('emoji', DB::raw('count(*) as count'))
            ->groupBy('emoji')
            ->get()
            ->map(function ($reaction) {
                return [
                    'emoji' => $reaction->emoji,
                    'count' => $reaction->count,
                    'users' => $this->reactions()
                        ->where('emoji', $reaction->emoji)
                        ->with('user:id,name')
                        ->get()
                        ->pluck('user'),
                ];
            });
    }

    /**
     * Get media URL
     */
    public function getMediaUrlAttribute(): ?string
    {
        if (!$this->media_path) {
            return null;
        }

        return asset('storage/' . $this->media_path);
    }

    /**
     * Check if message has media
     */
    public function hasMedia(): bool
    {
        return !empty($this->media_path);
    }

    /**
     * Check if message is text only
     */
    public function isText(): bool
    {
        return $this->type === 'text';
    }

    /**
     * Check if message is read by a specific user
     */
    public function isReadBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }

    /**
     * Mark message as read by a user
     */
    public function markAsReadBy(User $user): void
    {
        if (!$this->isReadBy($user) && $this->sender_id !== $user->id) {
            $this->reads()->create([
                'user_id' => $user->id,
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Get read status for all conversation members
     */
    public function getReadStatusAttribute()
    {
        $conversationUsers = $this->conversation->users()->pluck('users.id');

        return $conversationUsers->map(function ($userId) {
            if ($userId === $this->sender_id) {
                return null; // Sender doesn't need read status
            }

            $read = $this->reads()->where('user_id', $userId)->first();

            return [
                'user_id' => $userId,
                'is_read' => $read !== null,
                'read_at' => $read?->read_at,
            ];
        })->filter();
    }

    /**
     * Scope: Unread messages for a user
     */
    public function scopeUnreadFor($query, User $user)
    {
        return $query->where('sender_id', '!=', $user->id)
            ->whereDoesntHave('reads', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute(): ?string
    {
        if (!$this->media_size) {
            return null;
        }

        $size = $this->media_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, 2) . ' ' . $units[$i];
    }
}

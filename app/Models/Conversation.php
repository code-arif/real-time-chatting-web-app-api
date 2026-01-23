<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'avatar',
        'description',
        'created_by',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * Get all users in this conversation
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_users')
            ->withPivot('role', 'is_muted', 'is_archived', 'last_read_message_id', 'joined_at', 'left_at')
            ->withTimestamps()
            ->wherePivot('left_at', null);
    }

    /**
     * Get all messages in this conversation
     */
    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the latest message
     */
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Get the creator of the conversation
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get typing indicators for this conversation
     */
    public function typingIndicators()
    {
        return $this->hasMany(TypingIndicator::class);
    }

    /**
     * Scope: Private conversations
     */
    public function scopePrivate($query)
    {
        return $query->where('type', 'private');
    }

    /**
     * Scope: Group conversations
     */
    public function scopeGroup($query)
    {
        return $query->where('type', 'group');
    }

    /**
     * Check if conversation is private
     */
    public function isPrivate(): bool
    {
        return $this->type === 'private';
    }

    /**
     * Check if conversation is group
     */
    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    /**
     * Get the other user in a private conversation
     */
    public function getOtherUser(User $currentUser)
    {
        if ($this->isGroup()) {
            return null;
        }

        return $this->users()->where('users.id', '!=', $currentUser->id)->first();
    }

    /**
     * Get conversation name for a specific user
     */
    public function getNameForUser(User $user): string
    {
        if ($this->isGroup()) {
            return $this->name ?? 'Group Chat';
        }

        $otherUser = $this->getOtherUser($user);
        return $otherUser ? $otherUser->name : 'Unknown User';
    }

    /**
     * Get conversation avatar for a specific user
     */
    public function getAvatarForUser(User $user): string
    {
        if ($this->isGroup()) {
            return $this->avatar
                ? asset('storage/' . $this->avatar)
                : 'https://ui-avatars.com/api/?name=' . urlencode($this->name ?? 'Group') . '&color=400127&background=f0f0f0';
        }

        $otherUser = $this->getOtherUser($user);
        return $otherUser ? $otherUser->avatar_url : '';
    }

    /**
     * Get unread messages count for a specific user
     */
    public function getUnreadCountForUser(User $user): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereDoesntHave('reads', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->count();
    }

    /**
     * Add user to conversation
     */
    public function addUser(User $user, string $role = 'member'): void
    {
        if (!$this->users()->where('users.id', $user->id)->exists()) {
            $this->users()->attach($user->id, [
                'role' => $role,
                'joined_at' => now(),
            ]);
        }
    }

    /**
     * Remove user from conversation
     */
    public function removeUser(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'left_at' => now(),
        ]);
    }

    /**
     * Check if user is admin
     */
    public function isUserAdmin(User $user): bool
    {
        return $this->users()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}

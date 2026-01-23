<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'bio',
        'phone',
        'status',
        'last_seen_at',
        'push_subscription',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get all conversations for this user
     */
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_users')
            ->withPivot('role', 'is_muted', 'is_archived', 'last_read_message_id', 'joined_at', 'left_at')
            ->withTimestamps()
            ->wherePivot('left_at', null);
    }

    /**
     * Get all messages sent by this user
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Get all message reactions by this user
     */
    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    /**
     * Get all message reads by this user
     */
    public function messageReads()
    {
        return $this->hasMany(MessageRead::class);
    }

    /**
     * Get conversations created by this user
     */
    public function createdConversations()
    {
        return $this->hasMany(Conversation::class, 'created_by');
    }

    /**
     * Scope: Get online users
     */
    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    /**
     * Scope: Get offline users
     */
    public function scopeOffline($query)
    {
        return $query->where('status', 'offline');
    }

    /**
     * Check if user is online
     */
    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    /**
     * Mark user as online
     */
    public function markAsOnline(): void
    {
        $this->update([
            'status' => 'online',
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Mark user as offline
     */
    public function markAsOffline(): void
    {
        $this->update([
            'status' => 'offline',
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Get avatar URL with fallback
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }

        // Generate avatar with user initials
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=400127&background=f0f0f0';
    }

    /**
     * Get unread messages count across all conversations
     */
    public function getUnreadMessagesCountAttribute(): int
    {
        return Message::whereHas('conversation.users', function ($query) {
            $query->where('users.id', $this->id);
        })
        ->where('sender_id', '!=', $this->id)
        ->whereDoesntHave('reads', function ($query) {
            $query->where('user_id', $this->id);
        })
        ->count();
    }
}

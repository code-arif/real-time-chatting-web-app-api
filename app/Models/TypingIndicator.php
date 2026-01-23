<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypingIndicator extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'last_typed_at',
    ];

    protected $casts = [
        'last_typed_at' => 'datetime',
    ];

    /**
     * Get the conversation
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Update or create typing indicator
     */
    public static function updateTyping(int $conversationId, int $userId): void
    {
        self::updateOrCreate(
            [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
            ],
            [
                'last_typed_at' => now(),
            ]
        );
    }

    /**
     * Get currently typing users (active in last 3 seconds)
     */
    public static function getCurrentlyTyping(int $conversationId, int $excludeUserId = null)
    {
        $query = self::where('conversation_id', $conversationId)
            ->where('last_typed_at', '>', now()->subSeconds(3))
            ->with('user:id,name');

        if ($excludeUserId) {
            $query->where('user_id', '!=', $excludeUserId);
        }

        return $query->get();
    }

    /**
     * Clear typing indicator
     */
    public static function clearTyping(int $conversationId, int $userId): void
    {
        self::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->delete();
    }
}

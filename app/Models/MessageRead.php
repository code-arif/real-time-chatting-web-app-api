<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageRead extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'user_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * Get the message this read belongs to
     */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the user who read the message
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark multiple messages as read
     */
    public static function markMultipleAsRead(array $messageIds, int $userId): void
    {
        foreach ($messageIds as $messageId) {
            self::firstOrCreate([
                'message_id' => $messageId,
                'user_id' => $userId,
            ], [
                'read_at' => now(),
            ]);
        }
    }
}

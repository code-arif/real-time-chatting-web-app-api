<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Emoji reaction (stored as unicode or shortcode)
            $table->string('emoji', 10); // e.g., '👍', '❤️', '😂'

            $table->timestamps();

            // A user can only react once with each emoji per message
            $table->unique(['message_id', 'user_id', 'emoji']);

            // Indexes
            $table->index('message_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};

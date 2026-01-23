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
        Schema::create('conversation_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Role in group (admin, member)
            $table->enum('role', ['admin', 'member'])->default('member');

            // Mute notifications
            $table->boolean('is_muted')->default(false);

            // Archived conversation
            $table->boolean('is_archived')->default(false);

            // Last read message tracking
            $table->unsignedBigInteger('last_read_message_id')->nullable();

            // Joined and left timestamps
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();

            $table->timestamps();

            // Unique constraint
            $table->unique(['conversation_id', 'user_id']);

            // Indexes
            $table->index('user_id');
            $table->index('conversation_id');
            $table->index(['user_id', 'is_archived']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_users');
    }
};

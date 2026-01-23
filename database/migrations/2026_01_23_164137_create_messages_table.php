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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');

            // Message type: text, image, video, file
            $table->enum('type', ['text', 'image', 'video', 'file', 'audio'])->default('text');

            // Message content
            $table->text('content')->nullable(); // Text content or caption

            // Media information
            $table->string('media_path')->nullable();
            $table->string('media_name')->nullable(); // Original filename
            $table->string('media_type')->nullable(); // MIME type
            $table->unsignedBigInteger('media_size')->nullable(); // File size in bytes

            // Reply to another message
            $table->foreignId('reply_to')->nullable()->constrained('messages')->onDelete('set null');

            // Message status
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();

            // Soft delete for "delete for me" functionality
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('conversation_id');
            $table->index('sender_id');
            $table->index(['conversation_id', 'created_at']);
            $table->index('reply_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

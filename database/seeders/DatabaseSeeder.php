<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\Conversation;
use App\Models\MessageReaction;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create main test user
        $mainUser = User::factory()->online()->create([
            'name' => 'John Doe',
            'email' => 'john@gmail.com',
            'password' => bcrypt('12345678'),
        ]);

        echo "Created main user: {$mainUser->email} (password: 12345678)\n";

        // Create additional users
        $users = User::factory(10)->create();
        $allUsers = $users->push($mainUser);

        echo "Created " . $users->count() . " additional users\n";

        // Create private conversations
        $privateConversations = [];
        for ($i = 0; $i < 10; $i++) {
            $conversation = Conversation::factory()->private()->create([
                'created_by' => $mainUser->id,
            ]);

            // Add main user and one random user
            $conversation->users()->attach($mainUser->id, [
                'role' => 'member',
                'joined_at' => now(),
            ]);

            $otherUser = $users->random();
            $conversation->users()->attach($otherUser->id, [
                'role' => 'member',
                'joined_at' => now(),
            ]);

            $privateConversations[] = $conversation;
        }

        echo "Created " . count($privateConversations) . " private conversations\n";

        // Create group conversations
        $groupConversations = [];
        for ($i = 0; $i < 5; $i++) {
            $conversation = Conversation::factory()->group()->create([
                'name' => fake()->words(3, true) . ' Group',
                'created_by' => $mainUser->id,
            ]);

            // Add main user as admin
            $conversation->users()->attach($mainUser->id, [
                'role' => 'admin',
                'joined_at' => now(),
            ]);

            // Add 3-8 random members
            $members = $users->random(rand(3, 8));
            foreach ($members as $member) {
                if (!$conversation->users()->where('users.id', $member->id)->exists()) {
                    $conversation->users()->attach($member->id, [
                        'role' => 'member',
                        'joined_at' => now(),
                    ]);
                }
            }

            $groupConversations[] = $conversation;
        }

        echo "Created " . count($groupConversations) . " group conversations\n";

        // Create messages for all conversations
        $allConversations = array_merge($privateConversations, $groupConversations);
        $totalMessages = 0;

        foreach ($allConversations as $conversation) {
            $conversationUsers = $conversation->users;
            $messageCount = rand(10, 50);

            for ($i = 0; $i < $messageCount; $i++) {
                $sender = $conversationUsers->random();

                $message = Message::factory()->create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'created_at' => now()->subDays(rand(0, 7))->subHours(rand(0, 23))->subMinutes(rand(0, 59)),
                ]);

                // Add some replies (20% chance)
                if (rand(1, 100) <= 20 && $i > 0) {
                    $previousMessages = $conversation->messages()->where('id', '<', $message->id)->get();
                    if ($previousMessages->count() > 0) {
                        $message->update([
                            'reply_to' => $previousMessages->random()->id,
                        ]);
                    }
                }

                // Add reactions (30% chance)
                if (rand(1, 100) <= 30) {
                    $emojis = ['👍', '❤️', '😂', '😮', '😢', '🔥'];
                    $reactingUsers = $conversationUsers->random(rand(1, min(3, $conversationUsers->count())));

                    foreach ($reactingUsers as $reactingUser) {
                        if ($reactingUser->id !== $sender->id) {
                            MessageReaction::create([
                                'message_id' => $message->id,
                                'user_id' => $reactingUser->id,
                                'emoji' => $emojis[array_rand($emojis)],
                            ]);
                        }
                    }
                }

                // Mark some messages as read
                foreach ($conversationUsers as $user) {
                    if ($user->id !== $sender->id && rand(1, 100) <= 70) {
                        MessageRead::create([
                            'message_id' => $message->id,
                            'user_id' => $user->id,
                            'read_at' => $message->created_at->addMinutes(rand(1, 30)),
                        ]);
                    }
                }

                $totalMessages++;
            }

            // Update last_message_at
            $lastMessage = $conversation->messages()->latest()->first();
            if ($lastMessage) {
                $conversation->update([
                    'last_message_at' => $lastMessage->created_at,
                ]);
            }
        }

        echo "✅ Created {$totalMessages} messages with reactions and read receipts\n";

        // Summary
        echo "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🎉 Database seeding completed!\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📊 Summary:\n";
        echo "   • Users: " . User::count() . "\n";
        echo "   • Conversations: " . Conversation::count() . "\n";
        echo "   • Messages: " . Message::count() . "\n";
        echo "   • Reactions: " . MessageReaction::count() . "\n";
        echo "   • Read Receipts: " . MessageRead::count() . "\n";
        echo "\n";
        echo "🔐 Test Account:\n";
        echo "   Email: john@example.com\n";
        echo "   Password: password\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    }
}

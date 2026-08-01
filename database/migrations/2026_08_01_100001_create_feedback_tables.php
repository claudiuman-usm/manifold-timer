<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A "report" a kid raises: a glitch or a feature idea. Parent can reply
        // (see feedback_messages) and mark it resolved.
        Schema::create('feedback_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kid_id')->constrained('kids');
            $table->enum('type', ['glitch', 'feature']);
            $table->timestamp('resolved_at')->nullable(); // null = open
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kid_id', 'resolved_at']);
        });

        // One chat message inside a thread. `read_at` = when the *recipient* saw
        // it (a kid message is read by the parent, a parent message by the kid),
        // which drives the unread badges on both floating widgets.
        Schema::create('feedback_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('feedback_threads')->cascadeOnDelete();
            $table->enum('sender', ['kid', 'parent']);
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['thread_id', 'sender']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_messages');
        Schema::dropIfExists('feedback_threads');
    }
};

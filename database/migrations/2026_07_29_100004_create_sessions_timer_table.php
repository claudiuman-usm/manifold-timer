<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timer_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kid_id')->constrained('kids');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('category_name')->nullable();   // snapshot at creation
            $table->enum('phase', ['work', 'break']);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();      // null = currently running
            $table->unsignedInteger('duration_seconds')->nullable(); // computed on stop
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kid_id', 'started_at']);
            $table->index(['kid_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timer_sessions');
    }
};

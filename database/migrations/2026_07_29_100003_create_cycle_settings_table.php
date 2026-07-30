<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('work_minutes')->default(120);
            $table->unsignedInteger('break_minutes')->default(45);
            $table->time('cutoff_time')->default('00:00:00'); // midnight lock
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_settings');
    }
};

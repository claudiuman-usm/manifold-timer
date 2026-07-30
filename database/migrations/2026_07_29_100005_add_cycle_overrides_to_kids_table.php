<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kids', function (Blueprint $table) {
            // Per-kid cycle overrides. NULL = inherit the global cycle_settings.
            $table->unsignedInteger('work_minutes')->nullable()->after('dark_mode');
            $table->unsignedInteger('break_minutes')->nullable()->after('work_minutes');
            $table->time('cutoff_time')->nullable()->after('break_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('kids', function (Blueprint $table) {
            $table->dropColumn(['work_minutes', 'break_minutes', 'cutoff_time']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kids', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('pin');                 // hashed short numeric PIN
            $table->string('color', 7)->default('#6366f1'); // hex accent
            $table->boolean('dark_mode')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kids');
    }
};

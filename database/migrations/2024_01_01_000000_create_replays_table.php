<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('replay.table', 'replays'), function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->string('event');
            $table->string('relation')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->nullableMorphs('user');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('replay.table', 'replays'));
    }
};

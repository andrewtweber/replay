<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $keyType = config('replay.morph_key_type', 'int');

        Schema::create(config('replay.table', 'replays'), function (Blueprint $table) use ($keyType) {
            $table->id();
            $this->morphColumns($table, 'model', $keyType, nullable: false);
            $table->string('event');
            $table->string('relation')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $this->morphColumns($table, 'user', $keyType, nullable: true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('replay.table', 'replays'));
    }

    /**
     * Add a polymorphic {name}_type / {name}_id pair whose key column matches
     * the configured morph_key_type.
     */
    private function morphColumns(Blueprint $table, string $name, string $keyType, bool $nullable): void
    {
        match ($keyType) {
            'uuid' => $nullable
                ? $table->nullableUuidMorphs($name)
                : $table->uuidMorphs($name),
            // A single string column holds either integer or UUID keys, so a
            // mixed-key install can share one table. Queries always pair the
            // id with the type, so the composite index still applies.
            'string' => $this->stringMorphs($table, $name, $nullable),
            default => $nullable
                ? $table->nullableMorphs($name)
                : $table->morphs($name),
        };
    }

    private function stringMorphs(Blueprint $table, string $name, bool $nullable): void
    {
        $type = $table->string("{$name}_type");
        $id = $table->string("{$name}_id");

        if ($nullable) {
            $type->nullable();
            $id->nullable();
        }

        $table->index(["{$name}_type", "{$name}_id"], "{$table->getTable()}_{$name}_index");
    }
};

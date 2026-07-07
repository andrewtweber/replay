<?php

namespace Replay\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Replay\Replay;
use Replay\Tests\Fixtures\Post;
use Replay\Tests\Fixtures\UuidPost;

class MorphKeyTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('uuid_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->timestamps();
        });
    }

    /**
     * Re-create the replays table under a given morph key type. The base
     * migration runs once during setUp; this reruns it so each test can
     * exercise a different column type.
     */
    private function migrateReplays(string $keyType): void
    {
        config(['replay.morph_key_type' => $keyType]);

        Schema::dropIfExists('replays');

        $migration = require __DIR__.'/../database/migrations/2024_01_01_000000_create_replays_table.php';
        $migration->up();
    }

    public function test_int_key_type_uses_integer_columns(): void
    {
        $this->migrateReplays('int');

        $this->assertSame('integer', Schema::getColumnType('replays', 'model_id'));
        $this->assertSame('integer', Schema::getColumnType('replays', 'user_id'));
    }

    public function test_uuid_key_type_uses_string_columns(): void
    {
        $this->migrateReplays('uuid');

        $this->assertSame('varchar', Schema::getColumnType('replays', 'model_id'));
        $this->assertSame('varchar', Schema::getColumnType('replays', 'user_id'));
    }

    public function test_string_key_type_uses_string_columns(): void
    {
        $this->migrateReplays('string');

        $this->assertSame('varchar', Schema::getColumnType('replays', 'model_id'));
        $this->assertSame('varchar', Schema::getColumnType('replays', 'user_id'));
    }

    public function test_uuid_keyed_model_records_and_replays(): void
    {
        $this->migrateReplays('uuid');

        $post = UuidPost::create(['title' => 'Hello']);

        $step = $post->replays()->first();

        $this->assertNotNull($step);
        $this->assertSame('created', $step->event);
        $this->assertSame($post->getKey(), $step->model_id);
        $this->assertIsString($step->model_id);
        $this->assertTrue($post->is($step->model));
    }

    public function test_string_key_type_supports_mixed_int_and_uuid_models(): void
    {
        $this->migrateReplays('string');

        $intPost = Post::create(['title' => 'Int keyed']);
        $uuidPost = UuidPost::create(['title' => 'Uuid keyed']);

        $this->assertSame((string) $intPost->getKey(), (string) $intPost->replays()->first()->model_id);
        $this->assertSame($uuidPost->getKey(), $uuidPost->replays()->first()->model_id);

        // Both share the one table, retrievable through the polymorphic relation.
        $this->assertTrue($intPost->is(Replay::where('model_type', $intPost->getMorphClass())->first()->model));
        $this->assertTrue($uuidPost->is(Replay::where('model_type', $uuidPost->getMorphClass())->first()->model));
    }
}

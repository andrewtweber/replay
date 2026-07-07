<?php

namespace Replay\Tests;

use Replay\Exceptions\ReplayException;
use Replay\Replay;
use Replay\Replayer;
use Replay\Tests\Fixtures\Post;
use Replay\Tests\Fixtures\SoftPost;
use Replay\Tests\Fixtures\Tag;

class ReplayerTest extends TestCase
{
    public function test_replay_recreates_a_deleted_model_in_its_final_state(): void
    {
        $post = Post::create(['title' => 'v1', 'body' => 'original']);
        $post->update(['title' => 'v2']);
        $post->update(['body' => 'rewritten', 'published_at' => '2026-01-02 03:04:05']);

        $expected = $post->fresh()->getAttributes();
        $key = $post->getKey();

        Replayer::withoutRecording(fn () => $post->forceDelete());
        $this->assertDatabaseMissing('posts', ['id' => $key]);

        $replayed = Replayer::for(Post::class, $key)->replay();

        $this->assertSame($expected, $replayed->fresh()->getAttributes());
    }

    public function test_up_to_reproduces_an_intermediate_state(): void
    {
        $post = Post::create(['title' => 'v1']);
        $post->update(['title' => 'v2']);
        $checkpoint = $post->replays()->latest('id')->first();
        $post->update(['title' => 'v3']);

        $key = $post->getKey();
        Replayer::withoutRecording(fn () => $post->forceDelete());

        $replayed = Replayer::for(Post::class, $key)->upTo($checkpoint)->replay();

        $this->assertSame('v2', $replayed->fresh()->title);
    }

    public function test_replay_reproduces_pivot_changes(): void
    {
        $post = Post::create(['title' => 'Hello']);
        $php = Tag::create(['name' => 'php']);
        $laravel = Tag::create(['name' => 'laravel']);

        $post->tags()->attach($php, ['note' => 'keep']);
        $post->tags()->attach($laravel);
        $post->tags()->detach($laravel->id);
        $post->tags()->updateExistingPivot($php->id, ['note' => 'updated']);

        $key = $post->getKey();
        Replayer::withoutRecording(function () use ($post) {
            $post->tags()->detach();
            $post->forceDelete();
        });

        $replayed = Replayer::for(Post::class, $key)->replay();

        $tags = $replayed->tags;
        $this->assertCount(1, $tags);
        $this->assertSame('php', $tags->first()->name);
        $this->assertSame('updated', $tags->first()->pivot->note);
    }

    public function test_replay_reproduces_soft_delete_state_with_original_timestamp(): void
    {
        $post = SoftPost::create(['title' => 'Hello']);
        $post->delete();

        $deletedAt = $post->fresh()->getRawOriginal('deleted_at');
        $key = $post->getKey();

        Replayer::withoutRecording(fn () => $post->forceDelete());

        $replayed = Replayer::for(SoftPost::class, $key)->replay();

        $this->assertTrue($replayed->fresh()->trashed());
        $this->assertSame($deletedAt, $replayed->fresh()->getRawOriginal('deleted_at'));
    }

    public function test_replay_throws_when_the_model_still_exists(): void
    {
        $post = Post::create(['title' => 'Hello']);

        $this->expectException(ReplayException::class);

        Replayer::for(Post::class, $post->getKey())->replay();
    }

    public function test_overwriting_replays_over_an_existing_model(): void
    {
        $post = Post::create(['title' => 'v1']);
        $tag = Tag::create(['name' => 'php']);
        $post->tags()->attach($tag->id);
        $checkpoint = $post->replays()->where('event', 'created')->first();
        $post->update(['title' => 'v2']);

        $replayed = Replayer::for(Post::class, $post->getKey())
            ->upTo($checkpoint)
            ->overwriting()
            ->replay();

        $this->assertSame('v1', $replayed->fresh()->title);
        $this->assertCount(0, $replayed->tags, 'Recorded pivot rows should be cleared before replaying');
    }

    public function test_replaying_is_quiet_by_default_and_never_records_new_steps(): void
    {
        $post = Post::create(['title' => 'v1']);
        $post->update(['title' => 'v2']);
        $key = $post->getKey();
        Replayer::withoutRecording(fn () => $post->forceDelete());

        $stepsBefore = Replay::count();

        $fired = [];
        Post::created(function () use (&$fired) {
            $fired[] = 'created';
        });

        Replayer::for(Post::class, $key)->replay();

        $this->assertSame([], $fired);
        $this->assertSame($stepsBefore, Replay::count());
    }

    public function test_replaying_with_events_fires_them_but_still_records_nothing(): void
    {
        $post = Post::create(['title' => 'v1']);
        $post->update(['title' => 'v2']);
        $key = $post->getKey();
        Replayer::withoutRecording(fn () => $post->forceDelete());

        $stepsBefore = Replay::count();

        $fired = [];
        Post::created(function () use (&$fired) {
            $fired[] = 'created';
        });
        Post::updated(function () use (&$fired) {
            $fired[] = 'updated';
        });

        Replayer::for(Post::class, $key)->withEvents()->replay();

        $this->assertSame(['created', 'updated'], $fired);
        $this->assertSame($stepsBefore, Replay::count());
    }

    public function test_custom_events_replay_through_registered_handlers(): void
    {
        Replayer::extend('published', function (Post $model, Replay $step) {
            $model->update(['published_at' => $step->new_values['at']]);
        });

        $post = Post::create(['title' => 'Hello']);
        $post->recordReplay('published', ['at' => '2026-01-02 03:04:05']);

        $key = $post->getKey();
        Replayer::withoutRecording(fn () => $post->forceDelete());

        $replayed = Replayer::for(Post::class, $key)->replay();

        $this->assertSame('2026-01-02 03:04:05', $replayed->fresh()->getRawOriginal('published_at'));
    }

    public function test_replay_throws_for_unknown_custom_events(): void
    {
        $post = Post::create(['title' => 'Hello']);
        $post->recordReplay('exploded');

        $key = $post->getKey();
        Replayer::withoutRecording(fn () => $post->forceDelete());

        $this->expectException(ReplayException::class);
        $this->expectExceptionMessageMatches('/exploded/');

        Replayer::for(Post::class, $key)->replay();
    }
}

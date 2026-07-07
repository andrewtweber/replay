<?php

namespace Replay\Tests;

use Replay\Replayer;
use Replay\Tests\Fixtures\Post;
use Replay\Tests\Fixtures\SoftPost;
use Replay\Tests\Fixtures\User;

class TrackingTest extends TestCase
{
    public function test_creating_records_all_attributes(): void
    {
        $post = Post::create(['title' => 'Hello', 'body' => 'World']);

        $step = $post->replays()->first();

        $this->assertNotNull($step);
        $this->assertSame('created', $step->event);
        $this->assertSame('Hello', $step->new_values['title']);
        $this->assertSame('World', $step->new_values['body']);
        $this->assertArrayHasKey('id', $step->new_values);
        $this->assertArrayHasKey('created_at', $step->new_values);
    }

    public function test_updating_records_only_changed_attributes(): void
    {
        $post = Post::create(['title' => 'Hello', 'body' => 'World']);

        $post->update(['title' => 'Goodbye']);

        $step = $post->replays()->where('event', 'updated')->first();

        $this->assertNotNull($step);
        $this->assertSame('Goodbye', $step->new_values['title']);
        $this->assertArrayNotHasKey('body', $step->new_values);
        $this->assertSame('Hello', $step->old_values['title']);
    }

    public function test_saving_without_changes_records_nothing(): void
    {
        $post = Post::create(['title' => 'Hello']);

        $post->save();
        $post->update(['title' => 'Hello']);

        $this->assertSame(1, $post->replays()->count());
    }

    public function test_old_values_can_be_disabled(): void
    {
        config(['replay.store_old_values' => false]);

        $post = Post::create(['title' => 'Hello']);
        $post->update(['title' => 'Goodbye']);

        $step = $post->replays()->where('event', 'updated')->first();

        $this->assertNull($step->old_values);
    }

    public function test_deleting_is_recorded(): void
    {
        $post = Post::create(['title' => 'Hello']);
        $post->delete();

        $this->assertSame('deleted', $post->replays()->latest('id')->first()->event);
    }

    public function test_soft_delete_and_restore_are_recorded(): void
    {
        $post = SoftPost::create(['title' => 'Hello']);

        $post->delete();
        $post->restore();
        $post->forceDelete();

        $events = $post->replays()->orderBy('id')->pluck('event')->all();

        $this->assertSame(['created', 'deleted', 'restored', 'force_deleted'], $events);

        $deleted = $post->replays()->where('event', 'deleted')->first();
        $this->assertNotNull($deleted->new_values['deleted_at']);

        $restored = $post->replays()->where('event', 'restored')->first();
        $this->assertNull($restored->new_values['deleted_at']);
    }

    public function test_excluded_attributes_are_not_recorded(): void
    {
        config(['replay.exclude' => ['body']]);

        $post = Post::create(['title' => 'Hello', 'body' => 'Secret']);
        $post->update(['body' => 'Still secret']);

        $created = $post->replays()->where('event', 'created')->first();

        $this->assertArrayNotHasKey('body', $created->new_values);
        $this->assertSame(1, $post->replays()->count(), 'A body-only update should record nothing');
    }

    public function test_authenticated_user_is_tracked(): void
    {
        $user = User::create(['name' => 'Andrew']);
        $this->actingAs($user);

        $post = Post::create(['title' => 'Hello']);

        $step = $post->replays()->first();

        $this->assertSame($user->getKey(), $step->user_id);
        $this->assertSame(User::class, $step->user_type);
        $this->assertTrue($step->user->is($user));
    }

    public function test_recording_can_be_paused(): void
    {
        $post = Replayer::withoutRecording(function () {
            $post = Post::create(['title' => 'Hello']);
            $post->update(['title' => 'Goodbye']);

            return $post;
        });

        $this->assertSame(0, $post->replays()->count());
    }

    public function test_manual_recording(): void
    {
        $post = Post::create(['title' => 'Hello']);

        $step = $post->recordReplay('published', ['channel' => 'rss']);

        $this->assertSame('published', $step->event);
        $this->assertSame(['channel' => 'rss'], $step->new_values);
        $this->assertTrue($step->model->is($post));
    }
}

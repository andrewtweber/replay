<?php

namespace Replay\Tests;

use Replay\Tests\Fixtures\Post;
use Replay\Tests\Fixtures\Tag;

class PivotTrackingTest extends TestCase
{
    protected Post $post;

    protected Tag $php;

    protected Tag $laravel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->post = Post::create(['title' => 'Hello']);
        $this->php = Tag::create(['name' => 'php']);
        $this->laravel = Tag::create(['name' => 'laravel']);
    }

    public function test_attach_is_recorded(): void
    {
        $this->post->tags()->attach($this->php, ['note' => 'first']);

        $step = $this->post->replays()->where('event', 'attached')->first();

        $this->assertNotNull($step);
        $this->assertSame('tags', $step->relation);
        $this->assertSame(['note' => 'first'], $step->new_values[$this->php->id]);
    }

    public function test_attach_many_with_pivot_attributes_is_recorded(): void
    {
        $this->post->tags()->attach([
            $this->php->id => ['note' => 'first'],
            $this->laravel->id => ['note' => 'second'],
        ]);

        $step = $this->post->replays()->where('event', 'attached')->first();

        $this->assertSame(['note' => 'first'], $step->new_values[$this->php->id]);
        $this->assertSame(['note' => 'second'], $step->new_values[$this->laravel->id]);
    }

    public function test_attach_list_with_shared_attributes_is_recorded(): void
    {
        $this->post->tags()->attach([$this->php->id, $this->laravel->id], ['note' => 'both']);

        $step = $this->post->replays()->where('event', 'attached')->first();

        $this->assertSame(['note' => 'both'], $step->new_values[$this->php->id]);
        $this->assertSame(['note' => 'both'], $step->new_values[$this->laravel->id]);
    }

    public function test_detach_is_recorded(): void
    {
        $this->post->tags()->attach([$this->php->id, $this->laravel->id]);

        $this->post->tags()->detach($this->php->id);

        $step = $this->post->replays()->where('event', 'detached')->first();

        $this->assertSame('tags', $step->relation);
        $this->assertSame([$this->php->id], $step->new_values);
    }

    public function test_detach_all_records_the_actual_ids(): void
    {
        $this->post->tags()->attach([$this->php->id, $this->laravel->id]);

        $this->post->tags()->detach();

        $step = $this->post->replays()->where('event', 'detached')->first();

        $this->assertEqualsCanonicalizing(
            [$this->php->id, $this->laravel->id],
            $step->new_values,
        );
    }

    public function test_sync_is_recorded_as_attach_and_detach_steps(): void
    {
        $this->post->tags()->attach($this->php->id);

        $this->post->tags()->sync([$this->laravel->id]);

        $events = $this->post->replays()->pluck('event')->all();

        $this->assertContains('detached', $events);
        $this->assertSame(2, $this->post->replays()->whereIn('event', ['attached'])->count());
    }

    public function test_pivot_update_is_recorded(): void
    {
        $this->post->tags()->attach($this->php->id, ['note' => 'before']);

        $this->post->tags()->updateExistingPivot($this->php->id, ['note' => 'after']);

        $step = $this->post->replays()->where('event', 'pivot_updated')->first();

        $this->assertSame('tags', $step->relation);
        $this->assertSame(['note' => 'after'], $step->new_values[$this->php->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\DuplicateEpisodeJob;
use App\Models\Episode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EpisodeDuplicationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the duplication endpoint dispatches the job and creates a duplication record.
     */
    public function test_it_dispatches_duplication_job(): void
    {
        Queue::fake();

        $episode = Episode::factory()->create();

        $response = $this->postJson("/api/episodes/{$episode->id}/duplicate");

        $response->assertStatus(202);

        Queue::assertPushed(DuplicateEpisodeJob::class, function ($job) {
            return !empty($job->duplicationId);
        });

        $this->assertDatabaseCount('episode_duplications', 1);
    }

    /**
     * Test that the duplication status endpoint returns the correct status and progress.
     */
    public function test_it_returns_duplication_status(): void
    {
        $duplication = \App\Models\EpisodeDuplication::factory()->create([
            'status' => 'processing',
            'progress' => 50,
        ]);

        $response = $this->getJson("/api/episode-duplications/{$duplication->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'processing',
                'progress' => 50,
            ]);
    }

    /**
     * Test that the duplication job completes successfully and updates the duplication record.
     */
    public function test_job_completes_duplication(): void
    {
        $episode = Episode::factory()->create();

        $duplication = \App\Models\EpisodeDuplication::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'source_episode_id' => $episode->id,
            'status' => 'pending',
        ]);

        $job = new \App\Jobs\DuplicateEpisodeJob($duplication->id);

        $job->handle(app(\App\Services\EpisodeDuplicationService::class));

        $this->assertDatabaseHas('episode_duplications', [
            'id' => $duplication->id,
            'status' => 'completed',
        ]);
    }

    /**
     * Test that running the duplication service twice does not create duplicate mappings.
     */
    public function test_it_does_not_duplicate_same_entity_twice(): void
    {
        $episode = Episode::factory()->create();

        $duplication = \App\Models\EpisodeDuplication::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'source_episode_id' => $episode->id,
            'status' => 'pending',
        ]);

        $service = app(\App\Services\EpisodeDuplicationService::class);

        // run twice
        $service->duplicate($duplication);
        $service->duplicate($duplication);

        $mappings = \App\Models\DuplicationMapping::all();

        $this->assertTrue($mappings->count() === $mappings->unique('old_id')->count());
    }
}

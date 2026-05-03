<?php

namespace App\Jobs;

use App\Models\EpisodeDuplication;
use App\Services\EpisodeDuplicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DuplicateEpisodeJob implements ShouldQueue
{
    use FoundationQueueable;

    public int $tries = 5;

    public int $timeout = 900;

    public int $backoff = 60;

    public function __construct(
        public string $duplicationId
    ) {}

    /**
     * @param EpisodeDuplicationService $service
     * @return void
     * @throws Throwable
     */
    public function handle(EpisodeDuplicationService $service): void
    {
        $duplication = EpisodeDuplication::findOrFail($this->duplicationId);

        try {
            $duplication->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            Log::info('Episode duplication started', [
                'duplication_id' => $duplication->id,
                'source_episode_id' => $duplication->source_episode_id,
            ]);

            $service->duplicate($duplication);

            $duplication->update([
                'status' => 'completed',
                'progress' => 100,
                'finished_at' => now(),
            ]);

            Log::info('Episode duplication completed', [
                'duplication_id' => $duplication->id,
                'target_episode_id' => $duplication->target_episode_id,
            ]);

        } catch (Throwable $e) {
            $duplication->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            Log::error('Episode duplication failed', [
                'duplication_id' => $duplication->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

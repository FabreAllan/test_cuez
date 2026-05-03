<?php

namespace App\Http\Controllers;

use App\Jobs\DuplicateEpisodeJob;
use App\Models\Episode;
use App\Models\EpisodeDuplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class EpisodeController extends Controller
{
    public function duplicate(Episode $episode): JsonResponse
    {
        $duplication = EpisodeDuplication::create([
            'id' => (string) Str::uuid(),
            'source_episode_id' => $episode->id,
            'status' => 'pending',
            'progress' => 0,
        ]);

        DuplicateEpisodeJob::dispatch($duplication->id)
            ->onQueue('episode-duplications');

        return response()->json([
            'message' => 'Duplication has started',
            'duplication_id' => $duplication->id,
            'status' => $duplication->status,
        ], 202);
    }

    public function duplicationStatus(EpisodeDuplication $duplication): JsonResponse
    {
        return response()->json([
            'id' => $duplication->id,
            'status' => $duplication->status,
            'progress' => $duplication->progress,
            'target_episode_id' => $duplication->target_episode_id,
            'error_message' => $duplication->error_message,
            'metadata' => $duplication->metadata,
            'finished_at' => $duplication->finished_at,
        ]);
    }
}

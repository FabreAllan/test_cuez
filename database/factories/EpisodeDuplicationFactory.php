<?php

namespace Database\Factories;

use App\Models\EpisodeDuplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EpisodeDuplication>
 */
class EpisodeDuplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'source_episode_id' => \App\Models\Episode::factory(),
            'status' => 'pending',
            'progress' => 0,
        ];
    }
}

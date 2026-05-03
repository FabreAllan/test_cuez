<?php

namespace Database\Factories;

use App\Models\BlockField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockField>
 */
class BlockFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->word(),
            'value' => fake()->sentence(),
        ];
    }
}

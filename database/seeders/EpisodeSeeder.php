<?php

namespace Database\Seeders;

use App\Models\Episode;
use App\Models\Part;
use App\Models\Article;
use App\Models\Block;
use App\Models\BlockField;
use App\Models\Media;
use Illuminate\Database\Seeder;

class EpisodeSeeder extends Seeder
{
    public function run(): void
    {
        Episode::factory(2)->create()->each(function ($episode) {

            Part::factory(3)->create([
                'episode_id' => $episode->id,
            ])->each(function ($part) {

                Article::factory(3)->create([
                    'part_id' => $part->id,
                ])->each(function ($article) {

                    Block::factory(3)->create([
                        'article_id' => $article->id,
                    ])->each(function ($block) {

                        BlockField::factory(2)->create([
                            'block_id' => $block->id,
                        ]);

                        Media::factory(2)->create([
                            'block_id' => $block->id,
                        ]);
                    });
                });
            });
        });
    }
}

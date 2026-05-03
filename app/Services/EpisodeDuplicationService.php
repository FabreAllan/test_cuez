<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Block;
use App\Models\BlockField;
use App\Models\DuplicationMapping;
use App\Models\Episode;
use App\Models\EpisodeDuplication;
use App\Models\Media;
use App\Models\Part;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EpisodeDuplicationService
{
    private const CHUNK_SIZE = 500;

    public function duplicate(EpisodeDuplication $duplication): void
    {
        $sourceEpisode = Episode::findOrFail($duplication->source_episode_id);

        $targetEpisode = $this->createTargetEpisode($duplication, $sourceEpisode);

        $this->duplicateParts($duplication, $sourceEpisode, $targetEpisode);
        $this->updateProgress($duplication, 25, 'parts_completed');

        $this->duplicateArticles($duplication);
        $this->updateProgress($duplication, 45, 'articles_completed');

        $this->duplicateBlocks($duplication);
        $this->updateProgress($duplication, 65, 'blocks_completed');

        $this->duplicateBlockFields($duplication);
        $this->updateProgress($duplication, 80, 'block_fields_completed');

        $this->duplicateMedias($duplication);
        $this->updateProgress($duplication, 95, 'medias_completed');

        $targetEpisode->update([
            'status' => 'draft',
        ]);
    }

    private function createTargetEpisode(EpisodeDuplication $duplication, Episode $sourceEpisode): Episode
    {
        if ($duplication->target_episode_id) {
            return Episode::findOrFail($duplication->target_episode_id);
        }

        return DB::transaction(function () use ($duplication, $sourceEpisode) {
            $targetEpisode = $sourceEpisode->replicate();
            $targetEpisode->title = $sourceEpisode->title . ' - Copy';
            $targetEpisode->status = 'duplicating';
            $targetEpisode->save();

            $duplication->update([
                'target_episode_id' => $targetEpisode->id,
                'progress' => 5,
            ]);

            return $targetEpisode;
        });
    }

    private function duplicateParts(EpisodeDuplication $duplication, Episode $sourceEpisode, Episode $targetEpisode): void
    {
        Part::where('episode_id', $sourceEpisode->id)
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($parts) use ($duplication, $targetEpisode) {
                DB::transaction(function () use ($parts, $duplication, $targetEpisode) {
                    foreach ($parts as $part) {
                        if ($this->alreadyDuplicated($duplication, 'part', $part->id)) {
                            continue;
                        }

                        $newPart = $part->replicate();
                        $newPart->episode_id = $targetEpisode->id;
                        $newPart->save();

                        $this->saveMapping($duplication, 'part', $part->id, $newPart->id);
                    }
                });
            });
    }

    private function duplicateArticles(EpisodeDuplication $duplication): void
    {
        DuplicationMapping::where('duplication_id', $duplication->id)
            ->where('entity_type', 'part')
            ->orderBy('old_id')
            ->chunkById(self::CHUNK_SIZE, function ($partMappings) use ($duplication) {
                foreach ($partMappings as $partMapping) {
                    Article::where('part_id', $partMapping->old_id)
                        ->orderBy('id')
                        ->chunkById(self::CHUNK_SIZE, function ($articles) use ($duplication, $partMapping) {
                            DB::transaction(function () use ($articles, $duplication, $partMapping) {
                                foreach ($articles as $article) {
                                    if ($this->alreadyDuplicated($duplication, 'article', $article->id)) {
                                        continue;
                                    }

                                    $newArticle = $article->replicate();
                                    $newArticle->part_id = $partMapping->new_id;
                                    $newArticle->save();

                                    $this->saveMapping($duplication, 'article', $article->id, $newArticle->id);
                                }
                            });
                        });
                }
            }, 'id');
    }

    private function duplicateBlocks(EpisodeDuplication $duplication): void
    {
        DuplicationMapping::where('duplication_id', $duplication->id)
            ->where('entity_type', 'article')
            ->orderBy('old_id')
            ->chunkById(self::CHUNK_SIZE, function ($articleMappings) use ($duplication) {
                foreach ($articleMappings as $articleMapping) {
                    Block::where('article_id', $articleMapping->old_id)
                        ->orderBy('id')
                        ->chunkById(self::CHUNK_SIZE, function ($blocks) use ($duplication, $articleMapping) {
                            DB::transaction(function () use ($blocks, $duplication, $articleMapping) {
                                foreach ($blocks as $block) {
                                    if ($this->alreadyDuplicated($duplication, 'block', $block->id)) {
                                        continue;
                                    }

                                    $newBlock = $block->replicate();
                                    $newBlock->article_id = $articleMapping->new_id;
                                    $newBlock->save();

                                    $this->saveMapping($duplication, 'block', $block->id, $newBlock->id);
                                }
                            });
                        });
                }
            }, 'id');
    }

    private function duplicateBlockFields(EpisodeDuplication $duplication): void
    {
        DuplicationMapping::where('duplication_id', $duplication->id)
            ->where('entity_type', 'block')
            ->orderBy('old_id')
            ->chunkById(self::CHUNK_SIZE, function ($blockMappings) use ($duplication) {
                foreach ($blockMappings as $blockMapping) {
                    BlockField::where('block_id', $blockMapping->old_id)
                        ->orderBy('id')
                        ->chunkById(self::CHUNK_SIZE, function ($fields) use ($duplication, $blockMapping) {
                            DB::transaction(function () use ($fields, $duplication, $blockMapping) {
                                foreach ($fields as $field) {
                                    if ($this->alreadyDuplicated($duplication, 'block_field', $field->id)) {
                                        continue;
                                    }

                                    $newField = $field->replicate();
                                    $newField->block_id = $blockMapping->new_id;
                                    $newField->save();

                                    $this->saveMapping($duplication, 'block_field', $field->id, $newField->id);
                                }
                            });
                        });
                }
            }, 'id');
    }

    private function duplicateMedias(EpisodeDuplication $duplication): void
    {
        DuplicationMapping::where('duplication_id', $duplication->id)
            ->where('entity_type', 'block')
            ->orderBy('old_id')
            ->chunkById(self::CHUNK_SIZE, function ($blockMappings) use ($duplication) {
                foreach ($blockMappings as $blockMapping) {
                    Media::where('block_id', $blockMapping->old_id)
                        ->orderBy('id')
                        ->chunkById(self::CHUNK_SIZE, function ($medias) use ($duplication, $blockMapping) {
                            DB::transaction(function () use ($medias, $duplication, $blockMapping) {
                                foreach ($medias as $media) {
                                    if ($this->alreadyDuplicated($duplication, 'media', $media->id)) {
                                        continue;
                                    }

                                    $newMedia = $media->replicate();
                                    $newMedia->block_id = $blockMapping->new_id;
                                    $newMedia->save();

                                    $this->saveMapping($duplication, 'media', $media->id, $newMedia->id);
                                }
                            });
                        });
                }
            }, 'id');
    }

    private function alreadyDuplicated(EpisodeDuplication $duplication, string $entityType, int $oldId): bool
    {
        return DuplicationMapping::where('duplication_id', $duplication->id)
            ->where('entity_type', $entityType)
            ->where('old_id', $oldId)
            ->exists();
    }

    private function saveMapping(EpisodeDuplication $duplication, string $entityType, int $oldId, int $newId): void
    {
        DuplicationMapping::firstOrCreate([
            'duplication_id' => $duplication->id,
            'entity_type' => $entityType,
            'old_id' => $oldId,
        ], [
            'new_id' => $newId,
        ]);
    }

    private function updateProgress(EpisodeDuplication $duplication, int $progress, string $step): void
    {
        $duplication->update([
            'progress' => $progress,
            'metadata' => [
                'current_step' => $step,
                'updated_at' => now()->toISOString(),
            ],
        ]);
    }
}

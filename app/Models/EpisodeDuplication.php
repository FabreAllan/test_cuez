<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EpisodeDuplication extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'source_episode_id',
        'target_episode_id',
        'status',
        'progress',
        'metadata',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function sourceEpisode(): BelongsTo
    {
        return $this->belongsTo(Episode::class, 'source_episode_id');
    }

    public function targetEpisode(): BelongsTo
    {
        return $this->belongsTo(Episode::class, 'target_episode_id');
    }
}

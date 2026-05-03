<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'type',
        'position',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(BlockField::class);
    }

    public function medias(): HasMany
    {
        return $this->hasMany(Media::class);
    }
}

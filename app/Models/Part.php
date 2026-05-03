<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Part extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'title',
        'position',
    ];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}

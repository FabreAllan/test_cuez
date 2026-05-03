<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Media extends Model
{
    use HasFactory;
    protected $fillable = [
        'block_id',
        'disk',
        'path',
        'mime_type',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}

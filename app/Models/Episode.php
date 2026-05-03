<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Episode extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'status',
    ];

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }
}

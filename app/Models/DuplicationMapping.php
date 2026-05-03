<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DuplicationMapping extends Model
{
    protected $fillable = [
        'duplication_id',
        'entity_type',
        'old_id',
        'new_id',
    ];
}

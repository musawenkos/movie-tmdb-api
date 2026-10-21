<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favourite extends Model
{
    protected $fillable = [
        'tmdb_movie_id',
        'title',
        'poster_path',
        'release_date'
    ];

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['release_date' => 'date'];
    }

}

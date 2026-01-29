<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get the notes that have this tag.
     */
    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class);
    }

    /**
     * Get the projects that have this tag.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    /**
     * Get the concepts that have this tag.
     */
    public function concepts(): BelongsToMany
    {
        return $this->belongsToMany(Concept::class);
    }
}

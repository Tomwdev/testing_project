<?php

namespace App\Models;

use App\Events\NoteCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Note extends Model
{
    /** @use HasFactory<\Database\Factories\NoteFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'project_id',
    ];

    /**
     * Get the user that owns the note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tags for the note.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    protected static function booted(): void
    {
        static::created(function (Note $note) {
            event(new NoteCreated($note, $note->user));
        });

        static::saved(function (Note $note) {
            \App\Jobs\AnalyzeNoteContent::dispatchAfterResponse($note);
        });
    }
}

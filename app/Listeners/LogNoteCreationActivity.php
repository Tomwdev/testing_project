<?php

namespace App\Listeners;

use App\Events\NoteCreated;
use App\Models\ActivityLog;
use App\Models\Note;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogNoteCreationActivity
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(NoteCreated $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'action' => 'created',
            'subject_type' => Note::class,  // Full class name for morphTo
            'subject_id' => $event->note->id,
            'properties' => ['title' => $event->note->title],
        ]);
    }
}

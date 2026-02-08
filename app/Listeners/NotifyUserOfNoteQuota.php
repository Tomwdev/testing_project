<?php

namespace App\Listeners;

use App\Events\NoteCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyUserOfNoteQuota implements ShouldQueue
{

    public function shouldQueue(NoteCreated $event): bool
    {
        return $event->user->notes()->count() > 50;
    }

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
        logger('User approaching note quota', [
            'user_id' => $event->user->id
        ]);
    }
}

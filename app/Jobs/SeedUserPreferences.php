<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class SeedUserPreferences implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public User $user)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $project = $this->user->projects()->where('title', 'My Playground')->first();
        $note = $this->user->notes()->where('title', 'Welcome to your Notes')->first();

        $globalTags = Tag::whereIn('name', ['Laravel', 'api'])->get();

        if ($globalTags->isEmpty()) {
            Log::warning("Skipping tag assignment for user {$this->user->id}: Global tags not found.");
            return;
        }

        if ($note) {
            $note->tags()->syncWithoutDetaching($globalTags->pluck('id'));

            Log::info("Attached tags to Note ID: {$note->id}");
        }

        if ($project) {
            $laravelTag = $globalTags->firstWhere('slug', 'laravel');

            if ($laravelTag) {
                $project->tags()->syncWithoutDetaching([$laravelTag->id]);
                Log::info("Attached 'Laravel' tag to Project ID: {$project->id}");
            }
        }
    }
}

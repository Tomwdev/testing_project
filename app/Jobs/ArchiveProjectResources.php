<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Tag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ArchiveProjectResources implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Project $project)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Archiving resources for Project ID: {$this->project->id}");

        $archivedTag = Tag::firstOrCreate(
            ['slug' => 'archived'],
            ['name' => 'Archived'],
        );

        $this->project->notes()->chunk(100, function ($notes) use ($archivedTag) {
            foreach ($notes as $note) {
                $note->tags()->syncWithoutDetaching([$archivedTag->id]);
            }
        });

        Log::info("Successfully archived notes for Project ID: {$this->project->id}");
    }
}

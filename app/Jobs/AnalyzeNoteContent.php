<?php

namespace App\Jobs;

use App\Models\Note;
use App\Models\Tag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeNoteContent implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Note $note)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $content = strtolower($this->note->body);
        $tagsToApply = [];

        if (str_contains($content, 'urgent') || str_contains($content, 'important')) {
            $tagsToApply[] = 'Urgent';
        }

        if (str_contains($content, 'http') || str_contains($content, 'www.')) {
            $tagsToApply[] = 'Reference';
        }

        if (str_contains($content, '<?php') || str_contains($content, 'function') || str_contains($content, 'console.log')) {
            $tagsToApply[] = 'Code Snippet';
        }

        if (str_contains($content, '[ ]') || str_contains($content, 'todo')) {
            $tagsToApply[] = 'Tasks';
        }

        if (!empty($tagsToApply)) {
            foreach ($tagsToApply as $tagName) {

                $tag = Tag::firstOrCreate(['name' => $tagName], ['slug' => \Illuminate\Support\Str::slug($tagName)]);

                $this->note->tags()->syncWithoutDetaching([$tag->id]);
            }
        }
    }
}

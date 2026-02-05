<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AssignTagToItem implements ShouldQueue
{
    use Queueable, Batchable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $modelType,   // Stored as $this->modelType
        public int $modelId,        // Stored as $this->modelId
        public int $tagId,          // Stored as $this->tagId
        public string $action       // Stored as $this->action
    ) {
        // Constructor is EMPTY - just stores data
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if batch was cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Resolve the model dynamically
        $model = $this->modelType::find($this->modelId);

        // Handle case where item was deleted before job ran
        if (!$model) {
            return;
        }

        // Perform the tag operation
        if ($this->action === 'attach') {
            $model->tags()->syncWithoutDetaching([$this->tagId]);
        } else {
            $model->tags()->detach($this->tagId);
        }
    }
}

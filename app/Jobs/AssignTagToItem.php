<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Jobs\LogActivity;

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
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $model = $this->modelType::find($this->modelId);

        if (!$model) {
            return;
        }

        if ($this->action === 'attach') {
            $model->tags()->syncWithoutDetaching([$this->tagId]);
        } else {
            $model->tags()->detach($this->tagId);
        }

        LogActivity::dispatch(
            $model->user,
            $this->action === 'attach' ? 'tagged' : 'untagged',
            class_basename($this->modelType),
            $this->modelId
        );
    }
}

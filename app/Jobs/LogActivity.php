<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\ActivityLog;

class LogActivity implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public string $action,
        public string $modelType,
        public int $modelId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Appends the full namespace to the model type and sets to upper case first letter
        $subjectClass = 'App\\Models\\' . ucfirst($this->modelType);

        ActivityLog::create([
            'user_id' => $this->user->id,
            'action' => $this->action,
            'subject_type' => $subjectClass,
            'subject_id' => $this->modelId,
        ]);
    }
}

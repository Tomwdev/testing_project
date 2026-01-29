<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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
        Log::info('User activity logged', [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'action' => $this->action,
            'model_type' => $this->modelType,
            'model_id' => $this->modelId,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Logged job for testing
        logger('Test Job for Welcome Email', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'name' => $this->user->name,
        ]);

        // Simulate email sending delay
        // In production, use: Mail::to($this->user)->send(new WelcomeMail($this->user));
    }
}

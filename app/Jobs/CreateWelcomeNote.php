<?php
namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateWelcomeNote implements ShouldQueue
{
    use Queueable;

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
        $this->user->notes()->firstOrCreate(
            [
                'title' => 'Welcome to your Notes',
            ],
            [
                'body' => "This is a sample note.\n\nYou can edit this, delete it, or add tags to organize your thoughts."
            ]
        );
    }
}

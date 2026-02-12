<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Jobs\CreateDefaultProject;
use App\Jobs\CreateWelcomeNote;
use App\Jobs\SeedUserPreferences;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class SetupNewUserAccount
{
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
    public function handle(UserRegistered $event): void
    {
        if (!$event->user || !$event->user->exists) {
            Log::warning('Attempted to setup account for invalid user.');
            return;
        }

        Bus::chain([
            new CreateDefaultProject($event->user),
            new CreateWelcomeNote($event->user),
            new SeedUserPreferences($event->user),
        ])
            ->catch(function (Throwable $e) use ($event) {
                Log::error("User Onboarding Chain failed for User ID: {$event->user->id}", [
                    'error' => $e->getMessage(),
                ]);
            })
            ->dispatch();
    }
}

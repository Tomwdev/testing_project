<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail; // Optional check
use App\Models\User; // <--- Import your User model
use App\Jobs\CreateDefaultProject;
use App\Jobs\CreateWelcomeNote;
use App\Jobs\SeedUserPreferences;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class SetupNewUserAccount
{
    public function handle(Registered $event): void
    {
        /** @var User $user */
        $user = $event->user;

        if (!$user instanceof User || !$user->exists) {
            Log::warning('SetupNewUserAccount: Invalid user in event.', [
                'event_user' => $user
            ]);
            return;
        }

        Bus::chain([
            new CreateDefaultProject($user),
            new CreateWelcomeNote($user),
            new SeedUserPreferences($user),
        ])
            ->catch(function (Throwable $e) use ($user) {
                Log::error("User Onboarding Chain failed for User ID: {$user->id}", [
                    'error' => $e->getMessage(),
                ]);
            })
            ->dispatch();
    }
}

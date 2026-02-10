<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Jobs\SendWelcomeEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendWelcomeEmailListener
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
        $user = $event->user;

        // Logic to send welcome email to the user
        dispatch(new SendWelcomeEmail($user));
    }
}

<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Mail\WeeklyDigest;
use Illuminate\Support\Facades\Mail;

class SendWeeklyDigestEmail implements ShouldQueue
{
    use Queueable;

    public User $user;

    /**
     * Create a new job instance.
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $activities = ActivityLog::where('user_id', $this->user->id)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->get()
            ->groupBy(['action', 'subject_type']);

        if ($activities->isEmpty()) {
            logger('Weekly digest skipped - no activity', ['user_id' => $this->user->id]);
            return;
        }

        Mail::to($this->user)->send(new WeeklyDigest($this->user, $activities));

        logger('Weekly digest sent', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'activity_count' => $activities->flatten()->count(),
        ]);
    }
}

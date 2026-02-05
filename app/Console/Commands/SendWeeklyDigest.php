<?php

namespace App\Console\Commands;

use App\Jobs\SendWeeklyDigestEmail;
use App\Models\User;
use Illuminate\Console\Command;

class SendWeeklyDigest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'digests:send-weekly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::all();

        foreach ($users as $user) {
            SendWeeklyDigestEmail::dispatch($user);
        }

    }
}

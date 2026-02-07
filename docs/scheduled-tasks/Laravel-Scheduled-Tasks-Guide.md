# Laravel Scheduled Tasks — Implementation Guide

Run code automatically at defined intervals without manual cron configuration per task. Laravel's scheduler lets you define all schedules fluently in PHP.

---

## Table of Contents

1. [What Are Scheduled Tasks?](#1-what-are-scheduled-tasks)
2. [Architecture Overview](#2-architecture-overview)
3. [Phase A: Server Cron Entry](#phase-a-server-cron-entry)
4. [Phase B: Define Schedules](#phase-b-define-schedules)
5. [Phase C: Scheduling Jobs](#phase-c-scheduling-jobs)
6. [Phase D: Scheduling Commands](#phase-d-scheduling-commands)
7. [Phase E: Output & Notifications](#phase-e-output--notifications)
8. [Phase F: Testing Schedules](#phase-f-testing-schedules)
9. [Complete Example](#complete-example)
10. [Checklist](#checklist)
11. [Quick Reference](#quick-reference)

---

## 1. What Are Scheduled Tasks?

### The Traditional Problem

Without Laravel, you'd add a cron entry for each task:

```bash
# Crontab nightmare
0 0 * * * php /path/to/artisan reports:daily
0 * * * * php /path/to/artisan cache:prune
*/5 * * * * php /path/to/artisan queue:work --stop-when-empty
0 6 * * 1 php /path/to/artisan backups:run
```

Problems:

- Schedules spread across server config
- Not version controlled
- Hard to test locally
- Different syntax from your app

### Laravel's Solution

One cron entry, all schedules in code:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

```php
// routes/console.php - All schedules in one place
Schedule::command('reports:daily')->dailyAt('00:00');
Schedule::command('cache:prune')->hourly();
Schedule::job(new CleanupJob)->everyFiveMinutes();
Schedule::command('backups:run')->weeklyOn(1, '6:00');
```

Benefits:

- All schedules in version control
- Test with `schedule:test`
- Fluent, readable syntax
- Runs closures, commands, jobs, or shell commands

---

## 2. Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│                   Server Cron                        │
│         * * * * * php artisan schedule:run          │
└─────────────────────┬───────────────────────────────┘
                      │ Every minute
                      ▼
┌─────────────────────────────────────────────────────┐
│               Laravel Scheduler                      │
│                                                      │
│  Checks all defined schedules                       │
│  "Is it time to run this task?"                     │
└─────────────────────┬───────────────────────────────┘
                      │
        ┌─────────────┼─────────────┐
        ▼             ▼             ▼
   ┌─────────┐   ┌─────────┐   ┌─────────┐
   │ Command │   │   Job   │   │ Closure │
   │ emails: │   │Cleanup  │   │ Log     │
   │ digest  │   │   Job   │   │ Stats   │
   └─────────┘   └─────────┘   └─────────┘
```

---

## Phase A: Server Cron Entry

### Step A1: Add the Cron Entry

This is the ONLY cron entry you need:

```bash
# Edit crontab
crontab -e

# Add this line
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Step A2: Verify Cron is Running

```bash
# Check cron service
sudo systemctl status cron

# View your crontab
crontab -l

# Watch cron logs (Debian/Ubuntu)
tail -f /var/log/syslog | grep CRON
```

### Local Development

For local testing, run the scheduler manually:

```bash
# Run once
php artisan schedule:run

# Run continuously (watches every minute)
php artisan schedule:work
```

---

## Phase B: Define Schedules

Schedules are defined in `routes/console.php` (Laravel 11+).

### Step B1: Basic Schedule

```php
<?php

use Illuminate\Support\Facades\Schedule;

// Run a closure every hour
Schedule::call(function () {
    info('Hourly task ran');
})->hourly();
```

### Step B2: Frequency Options

```php
// Time-based
Schedule::call($task)->everyMinute();
Schedule::call($task)->everyTwoMinutes();
Schedule::call($task)->everyFiveMinutes();
Schedule::call($task)->everyTenMinutes();
Schedule::call($task)->everyFifteenMinutes();
Schedule::call($task)->everyThirtyMinutes();
Schedule::call($task)->hourly();
Schedule::call($task)->hourlyAt(17);              // At :17 each hour
Schedule::call($task)->everyOddHour();
Schedule::call($task)->everyTwoHours();
Schedule::call($task)->everyFourHours();
Schedule::call($task)->everySixHours();
Schedule::call($task)->daily();
Schedule::call($task)->dailyAt('13:00');
Schedule::call($task)->twiceDaily(1, 13);         // 1:00 & 13:00
Schedule::call($task)->twiceDailyAt(1, 13, 15);   // 1:15 & 13:15
Schedule::call($task)->weekly();
Schedule::call($task)->weeklyOn(1, '8:00');       // Monday 8:00
Schedule::call($task)->monthly();
Schedule::call($task)->monthlyOn(4, '15:00');     // 4th at 15:00
Schedule::call($task)->twiceMonthly(1, 16, '13:00');
Schedule::call($task)->lastDayOfMonth('17:00');
Schedule::call($task)->quarterly();
Schedule::call($task)->yearly();
Schedule::call($task)->yearlyOn(6, 1, '17:00');   // June 1st 17:00
```

### Step B3: Custom Cron Expression

```php
// Every day at 1:30 AM
Schedule::call($task)->cron('30 1 * * *');

// Every weekday at 9 AM
Schedule::call($task)->cron('0 9 * * 1-5');
```

### Step B4: Day Constraints

```php
Schedule::call($task)
    ->daily()
    ->weekdays();           // Mon-Fri only

Schedule::call($task)
    ->daily()
    ->weekends();           // Sat-Sun only

Schedule::call($task)
    ->daily()
    ->sundays();            // or mondays(), tuesdays(), etc.

Schedule::call($task)
    ->daily()
    ->days([0, 3]);         // Sunday and Wednesday
```

### Step B5: Time Constraints

```php
Schedule::call($task)
    ->hourly()
    ->between('8:00', '17:00');    // Only during business hours

Schedule::call($task)
    ->hourly()
    ->unlessBetween('23:00', '5:00'); // Not during night
```

### Step B6: Timezone

```php
Schedule::call($task)
    ->dailyAt('14:00')
    ->timezone('America/New_York');
```

---

## Phase C: Scheduling Jobs

Queue jobs to run on schedule.

### Step C1: Schedule a Job

```php
use App\Jobs\CleanupOldRecords;
use App\Jobs\GenerateReports;
use App\Jobs\SyncExternalData;

// Basic job scheduling
Schedule::job(new CleanupOldRecords)->daily();

// With constructor arguments
Schedule::job(new GenerateReports('weekly'))->weeklyOn(1, '6:00');

// Job on specific queue
Schedule::job(new SyncExternalData, 'integrations')->everyFiveMinutes();

// Job on specific queue and connection
Schedule::job(new SyncExternalData, 'integrations', 'redis')
    ->everyFiveMinutes();
```

### Step C2: Create the Job

```bash
php artisan make:job CleanupOldRecords
```

```php
<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanupOldRecords implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        // Delete activity logs older than 90 days
        $deleted = ActivityLog::where('created_at', '<', now()->subDays(90))
            ->delete();

        info("Cleaned up {$deleted} old activity logs");
    }
}
```

### Step C3: Verify Job Is Scheduled

```bash
php artisan schedule:list
```

---

## Phase D: Scheduling Commands

Run Artisan commands on schedule.

### Step D1: Built-in Commands

```php
// Prune old models
Schedule::command('model:prune')->daily();

// Clear expired tokens
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Clear old cache
Schedule::command('cache:prune-stale-tags')->hourly();

// Backup database (if using spatie/laravel-backup)
Schedule::command('backup:run')->dailyAt('02:00');
Schedule::command('backup:clean')->dailyAt('03:00');
```

### Step D2: Custom Commands

Create a custom command:

```bash
php artisan make:command SendDailyDigest
```

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Mail\DailyDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDailyDigest extends Command
{
    protected $signature = 'emails:daily-digest';
    protected $description = 'Send daily digest email to subscribed users';

    public function handle(): int
    {
        $users = User::where('daily_digest', true)->get();

        $this->info("Sending digest to {$users->count()} users");

        foreach ($users as $user) {
            Mail::to($user)->queue(new DailyDigest($user));
        }

        $this->info('Daily digest emails queued');

        return Command::SUCCESS;
    }
}
```

Schedule it:

```php
Schedule::command('emails:daily-digest')->dailyAt('08:00');
```

### Step D3: Command with Arguments

```php
// With options
Schedule::command('reports:generate --type=weekly')
    ->weeklyOn(1, '06:00');

// With arguments
Schedule::command('users:cleanup old')
    ->monthly();

// Multiple arguments and options
Schedule::command('sync:products', ['--force', '--category=electronics'])
    ->everyFifteenMinutes();
```

### Step D4: Shell Commands

```php
// External shell command
Schedule::exec('node /home/app/scripts/process.js')
    ->everyFiveMinutes();

// With output
Schedule::exec('mysqldump -u root mydb > /backup/db.sql')
    ->dailyAt('03:00');
```

---

## Phase E: Output & Notifications

### Step E1: Send Output to File

```php
Schedule::command('reports:generate')
    ->daily()
    ->sendOutputTo('/var/log/reports.log');

// Append instead of overwrite
Schedule::command('reports:generate')
    ->daily()
    ->appendOutputTo('/var/log/reports.log');
```

### Step E2: Email Output

```php
Schedule::command('reports:generate')
    ->daily()
    ->emailOutputTo('admin@example.com');

// Only email on failure
Schedule::command('reports:generate')
    ->daily()
    ->emailOutputOnFailure('admin@example.com');
```

### Step E3: Webhooks

```php
Schedule::command('sync:external')
    ->hourly()
    ->pingBefore('https://health.example.com/start/abc123')
    ->pingOnSuccess('https://health.example.com/success/abc123')
    ->pingOnFailure('https://health.example.com/failure/abc123');

// Or just one ping
Schedule::command('sync:external')
    ->hourly()
    ->thenPing('https://health.example.com/complete/abc123');
```

### Step E4: Callbacks

```php
Schedule::command('reports:generate')
    ->daily()
    ->before(function () {
        // Before task runs
        info('Starting report generation');
    })
    ->after(function () {
        // After task completes (success or failure)
        info('Report generation finished');
    })
    ->onSuccess(function () {
        // Only on success
        cache()->put('last_report_at', now());
    })
    ->onFailure(function () {
        // Only on failure
        Notification::route('slack', config('services.slack.alerts'))
            ->notify(new ScheduledTaskFailed('reports:generate'));
    });
```

---

## Phase F: Testing Schedules

### Step F1: List Schedules

```bash
php artisan schedule:list
```

Output:

```
+---------------------+-------------+---------------------+
| Command             | Interval    | Next Due            |
+---------------------+-------------+---------------------+
| emails:daily-digest | Daily       | 2026-01-30 08:00:00 |
| CleanupOldRecords   | Daily       | 2026-01-30 00:00:00 |
| cache:prune         | Hourly      | 2026-01-29 15:00:00 |
+---------------------+-------------+---------------------+
```

### Step F2: Test Individual Tasks

```bash
php artisan schedule:test

# Select from list and run immediately
```

### Step F3: Run Scheduler Once

```bash
php artisan schedule:run
```

### Step F4: Run Scheduler Continuously

```bash
# Runs every minute (for local development)
php artisan schedule:work
```

### Step F5: Test in PHPUnit

```php
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

public function test_daily_digest_is_scheduled(): void
{
    $schedule = app(Schedule::class);

    $events = collect($schedule->events())->filter(function ($event) {
        return str_contains($event->command, 'emails:daily-digest');
    });

    $this->assertCount(1, $events);
    $this->assertEquals('0 8 * * *', $events->first()->expression);
}

public function test_daily_digest_command_works(): void
{
    Mail::fake();

    User::factory()->create(['daily_digest' => true]);

    Artisan::call('emails:daily-digest');

    Mail::assertQueued(DailyDigest::class);
}
```

---

## Complete Example

### routes/console.php

```php
<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\CleanupOldRecords;
use App\Jobs\SyncExternalData;
use App\Jobs\GenerateAnalytics;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduling
|--------------------------------------------------------------------------
*/

// EVERY MINUTE - Real-time needs
// (Use sparingly - these run very frequently)

// EVERY 5 MINUTES - Near real-time
Schedule::job(new SyncExternalData)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// HOURLY - Regular maintenance
Schedule::command('cache:prune-stale-tags')
    ->hourly()
    ->runInBackground();

Schedule::call(function () {
    cache()->put('hourly_check', now());
})->hourly();

// DAILY - Cleanup and reports
Schedule::job(new CleanupOldRecords)
    ->dailyAt('02:00')
    ->onOneServer()
    ->emailOutputOnFailure('admin@example.com');

Schedule::command('emails:daily-digest')
    ->dailyAt('08:00')
    ->weekdays()
    ->timezone('America/New_York')
    ->pingOnSuccess(config('services.healthcheck.digest'));

Schedule::command('model:prune')
    ->dailyAt('03:00');

// WEEKLY - Analytics and backups
Schedule::job(new GenerateAnalytics('weekly'))
    ->weeklyOn(1, '06:00')  // Monday 6 AM
    ->onOneServer();

Schedule::command('backup:run')
    ->weeklyOn(0, '04:00')  // Sunday 4 AM
    ->onOneServer();

// MONTHLY - Big reports
Schedule::command('reports:monthly')
    ->monthlyOn(1, '05:00')
    ->before(function () {
        info('Starting monthly report generation');
    })
    ->after(function () {
        info('Monthly report complete');
    });
```

### app/Jobs/SyncExternalData.php

```php
<?php

namespace App\Jobs;

use App\Services\ExternalApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncExternalData implements ShouldQueue
{
    use Queueable;

    public function handle(ExternalApiService $api): void
    {
        $data = $api->fetchLatest();

        foreach ($data as $item) {
            // Sync each item
            $this->processItem($item);
        }

        info('External sync complete', ['count' => count($data)]);
    }

    protected function processItem(array $item): void
    {
        // Process logic
    }
}
```

---

## Checklist

### Setup

- [ ] Single cron entry added: `* * * * * cd /path && php artisan schedule:run`
- [ ] Cron service running on server
- [ ] Schedules defined in `routes/console.php`

### For Each Scheduled Task

- [ ] Appropriate frequency chosen
- [ ] Timezone set if needed
- [ ] `withoutOverlapping()` if task might run long
- [ ] `onOneServer()` if running multiple servers
- [ ] Output/notification configured for failures
- [ ] Task tested with `schedule:test`

### Testing

- [ ] `php artisan schedule:list` shows all tasks
- [ ] Each task runs successfully via `schedule:test`
- [ ] Failure notifications working

### Production

- [ ] Monitor task execution times
- [ ] Review logs for failures
- [ ] Healthcheck pings configured for critical tasks

---

## Quick Reference

### Define Schedules In

```
Laravel 11+: routes/console.php
Laravel 10:  app/Console/Kernel.php (schedule method)
```

### Schedule Types

| Method                      | What It Runs    |
| --------------------------- | --------------- |
| `Schedule::call($closure)`  | PHP closure     |
| `Schedule::job($job)`       | Queued job      |
| `Schedule::command('name')` | Artisan command |
| `Schedule::exec('command')` | Shell command   |

### Common Frequencies

| Method               | Cron Equivalent |
| -------------------- | --------------- |
| `everyMinute()`      | `* * * * *`     |
| `everyFiveMinutes()` | `*/5 * * * *`   |
| `hourly()`           | `0 * * * *`     |
| `daily()`            | `0 0 * * *`     |
| `dailyAt('13:00')`   | `0 13 * * *`    |
| `weekly()`           | `0 0 * * 0`     |
| `monthly()`          | `0 0 1 * *`     |

### Important Options

| Option                    | Purpose                             |
| ------------------------- | ----------------------------------- |
| `withoutOverlapping()`    | Prevent concurrent runs             |
| `onOneServer()`           | Single server only (requires cache) |
| `runInBackground()`       | Don't wait for completion           |
| `evenInMaintenanceMode()` | Run during maintenance              |
| `when($boolean)`          | Conditional execution               |

### Commands

| Command                     | Purpose                    |
| --------------------------- | -------------------------- |
| `php artisan schedule:list` | Show all scheduled tasks   |
| `php artisan schedule:run`  | Run due tasks once         |
| `php artisan schedule:work` | Run scheduler continuously |
| `php artisan schedule:test` | Interactive task testing   |

---

## See Also

- [Scheduled Tasks Production Reference](Laravel-Scheduled-Tasks-Production-Reference.md) — Monitoring, overlapping, multi-server
- [Standard Jobs Guide](../standard-jobs/Laravel-Jobs-Guide.md) — Queued jobs basics
- [Events & Listeners Guide](../events-listeners/Laravel-Events-Listeners-Guide.md) — Event-driven patterns

# Laravel Scheduled Tasks — Production & Advanced Patterns Reference

A companion reference to the Implementation Guide. Covers overlap prevention, multi-server scheduling, maintenance mode, monitoring, and advanced patterns.

---

## Table of Contents

1. [Preventing Task Overlap](#1-preventing-task-overlap)
2. [Multi-Server Scheduling](#2-multi-server-scheduling)
3. [Maintenance Mode Handling](#3-maintenance-mode-handling)
4. [Background Processing](#4-background-processing)
5. [Conditional Scheduling](#5-conditional-scheduling)
6. [Monitoring & Observability](#6-monitoring--observability)
7. [Error Handling](#7-error-handling)
8. [Performance Optimization](#8-performance-optimization)
9. [Common Patterns](#9-common-patterns)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. Preventing Task Overlap

When a task takes longer than its frequency, you can get concurrent runs.

### The Problem

```
Task: Run every 5 minutes, takes 8 minutes

12:00 - Task starts
12:05 - New task starts (first still running!) ← OVERLAP
12:08 - First task finishes
12:10 - Another task starts
12:13 - Second task finishes
```

### Solution: withoutOverlapping

```php
Schedule::command('reports:generate')
    ->everyFiveMinutes()
    ->withoutOverlapping();
```

### How It Works

Laravel creates a cache lock. If the lock exists, the task is skipped.

```php
// Default lock expires after 24 hours
->withoutOverlapping()

// Custom expiration (in minutes)
->withoutOverlapping(expiresAt: 60)  // Lock expires after 60 minutes
```

### Lock Drivers

The lock uses your default cache driver. For production:

```php
// config/cache.php
'default' => env('CACHE_DRIVER', 'redis'),
```

### Manual Lock Management

If a task crashes, the lock might persist. Clear manually:

```bash
# If using file cache
rm storage/framework/cache/schedule-*

# If using Redis
redis-cli DEL "laravel_cache:schedule-*"

# Or clear all cache
php artisan cache:clear
```

---

## 2. Multi-Server Scheduling

With multiple servers, all servers run `schedule:run` every minute.

### The Problem

```
Server A: php artisan schedule:run → runs emails:daily-digest
Server B: php artisan schedule:run → runs emails:daily-digest (duplicate!)
Server C: php artisan schedule:run → runs emails:daily-digest (triplicate!)
```

### Solution: onOneServer

```php
Schedule::command('emails:daily-digest')
    ->dailyAt('08:00')
    ->onOneServer();
```

### Requirements

Requires a centralized cache driver that all servers share:

```php
// config/cache.php - Must be shared across servers
'default' => 'redis',  // or memcached, database, etc.
```

### How It Works

1. First server acquires a lock
2. Other servers see the lock and skip
3. Lock released after task completes

### Combined with withoutOverlapping

```php
Schedule::command('sync:inventory')
    ->everyFiveMinutes()
    ->onOneServer()           // Only one server runs it
    ->withoutOverlapping();   // Prevent overlap on that server
```

---

## 3. Maintenance Mode Handling

By default, scheduled tasks don't run during maintenance mode.

### Run During Maintenance

```php
Schedule::command('health:check')
    ->everyMinute()
    ->evenInMaintenanceMode();
```

### Use Cases

| Task               | Run During Maintenance? |
| ------------------ | ----------------------- |
| Health checks      | Yes                     |
| Backup completion  | Yes                     |
| User notifications | No                      |
| Data sync          | No                      |
| Queue monitoring   | Yes                     |

### Check Maintenance Status in Task

```php
Schedule::call(function () {
    if (app()->isDownForMaintenance()) {
        // Limited operations only
        return;
    }

    // Full operations
})->hourly()->evenInMaintenanceMode();
```

---

## 4. Background Processing

### Synchronous (Default)

The scheduler waits for each task to complete:

```php
Schedule::command('task:one')->hourly();    // Runs, waits
Schedule::command('task:two')->hourly();    // Then runs
```

### Background Mode

Run tasks without waiting:

```php
Schedule::command('reports:generate')
    ->hourly()
    ->runInBackground();  // Don't wait for completion
```

### When to Use Background

| Scenario                 | Use Background?       |
| ------------------------ | --------------------- |
| Quick tasks (< 1 second) | No                    |
| Long-running tasks       | Yes                   |
| Tasks with dependencies  | No (need to sequence) |
| Independent tasks        | Yes                   |

### Limitations

- Can't capture output directly
- Errors don't block other tasks
- Harder to debug

---

## 5. Conditional Scheduling

### Environment-Based

```php
Schedule::command('emails:marketing')
    ->daily()
    ->environments(['production']);  // Only in production

Schedule::command('debug:dump')
    ->everyMinute()
    ->environments(['local', 'staging']);  // Not in production
```

### Closure Conditions

```php
Schedule::command('notifications:send')
    ->hourly()
    ->when(function () {
        return User::whereNotNull('push_token')->exists();
    });

Schedule::command('expensive:operation')
    ->daily()
    ->skip(function () {
        return cache()->get('skip_expensive_tasks', false);
    });
```

### Feature Flags

```php
Schedule::command('new-feature:process')
    ->hourly()
    ->when(function () {
        return config('features.new_processing') === true;
    });
```

### Time-Based Conditions

```php
use Carbon\Carbon;

Schedule::command('heavy:task')
    ->hourly()
    ->when(function () {
        $hour = Carbon::now()->hour;
        return $hour >= 22 || $hour < 6;  // Only night hours
    });
```

---

## 6. Monitoring & Observability

### Health Check Pings

Use external monitoring services:

```php
Schedule::command('critical:job')
    ->hourly()
    ->pingBefore('https://hc-ping.com/uuid/start')
    ->pingOnSuccess('https://hc-ping.com/uuid')
    ->pingOnFailure('https://hc-ping.com/uuid/fail');
```

Popular services:

- Healthchecks.io
- Cronitor
- Dead Man's Snitch
- Better Stack (formerly Better Uptime)

### Output Logging

```php
// Dedicated log file
Schedule::command('important:task')
    ->daily()
    ->appendOutputTo(storage_path('logs/scheduled-tasks.log'));

// Email on failure
Schedule::command('critical:task')
    ->daily()
    ->emailOutputOnFailure('ops@example.com');
```

### Custom Monitoring

```php
Schedule::command('data:sync')
    ->everyFiveMinutes()
    ->before(function () {
        DB::table('task_runs')->insert([
            'task' => 'data:sync',
            'started_at' => now(),
        ]);
    })
    ->after(function () {
        DB::table('task_runs')
            ->where('task', 'data:sync')
            ->whereNull('finished_at')
            ->latest()
            ->update(['finished_at' => now()]);
    });
```

### Laravel Telescope

If using Telescope, schedule events are automatically logged.

### Custom Dashboard

```php
// Track in database
class ScheduleMonitor
{
    public static function recordStart(string $task): int
    {
        return DB::table('schedule_runs')->insertGetId([
            'task' => $task,
            'started_at' => now(),
            'status' => 'running',
        ]);
    }

    public static function recordSuccess(int $id): void
    {
        DB::table('schedule_runs')->where('id', $id)->update([
            'finished_at' => now(),
            'status' => 'success',
        ]);
    }

    public static function recordFailure(int $id, string $error): void
    {
        DB::table('schedule_runs')->where('id', $id)->update([
            'finished_at' => now(),
            'status' => 'failed',
            'error' => $error,
        ]);
    }
}
```

---

## 7. Error Handling

### Task Failure

By default, a failed task doesn't stop other scheduled tasks.

```php
Schedule::command('might:fail')
    ->hourly()
    ->onFailure(function (Throwable $exception) {
        Log::error('Scheduled task failed', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        Notification::route('slack', config('services.slack.ops'))
            ->notify(new ScheduledTaskFailed('might:fail', $exception));
    });
```

### Retry Logic

For jobs (not commands), use job retry mechanisms:

```php
// The job handles retries
class SyncData implements ShouldQueue
{
    public $tries = 3;
    public $backoff = [60, 300, 900];
}

Schedule::job(new SyncData)->hourly();
```

### Circuit Breaker Pattern

```php
Schedule::command('external:api-sync')
    ->everyFiveMinutes()
    ->when(function () {
        $failures = cache()->get('api_sync_failures', 0);

        if ($failures >= 5) {
            // Check if we should reset
            $lastFailure = cache()->get('api_sync_last_failure');
            if ($lastFailure && $lastFailure->diffInMinutes(now()) > 30) {
                cache()->forget('api_sync_failures');
                return true;
            }
            return false;  // Circuit open, skip
        }

        return true;
    })
    ->onFailure(function () {
        cache()->increment('api_sync_failures');
        cache()->put('api_sync_last_failure', now(), 3600);
    })
    ->onSuccess(function () {
        cache()->forget('api_sync_failures');
    });
```

---

## 8. Performance Optimization

### Stagger Tasks

Don't run everything at midnight:

```php
// ❌ Bad - all at midnight
Schedule::command('task:a')->daily();
Schedule::command('task:b')->daily();
Schedule::command('task:c')->daily();

// ✅ Good - staggered
Schedule::command('task:a')->dailyAt('00:00');
Schedule::command('task:b')->dailyAt('00:15');
Schedule::command('task:c')->dailyAt('00:30');
```

### Off-Peak Hours

```php
Schedule::command('heavy:processing')
    ->dailyAt('03:00');  // 3 AM, low traffic
```

### Queue Heavy Work

```php
// ❌ Bad - command does heavy work directly
Schedule::command('generate:reports')->daily();

// ✅ Good - command dispatches jobs
// ReportsCommand dispatches GenerateReportJob to queue
Schedule::command('generate:reports')->daily();

// Or schedule job directly
Schedule::job(new GenerateReportsJob)->daily();
```

### Limit Database Connections

```php
// If running many tasks at once
Schedule::command('task:one')
    ->dailyAt('03:00')
    ->runInBackground();

Schedule::command('task:two')
    ->dailyAt('03:00');  // Waits, shares connection
```

---

## 9. Common Patterns

### Database Cleanup

```php
Schedule::command('model:prune', [
    '--model' => [
        App\Models\ActivityLog::class,
        App\Models\Notification::class,
        App\Models\PasswordResetToken::class,
    ],
])->daily();
```

With Prunable trait:

```php
// In model
use Illuminate\Database\Eloquent\Prunable;

class ActivityLog extends Model
{
    use Prunable;

    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDays(90));
    }
}
```

### Cache Warming

```php
Schedule::call(function () {
    // Pre-compute expensive queries
    cache()->put('dashboard_stats', Dashboard::computeStats(), 3600);
    cache()->put('top_products', Product::getTopSelling(), 3600);
})->hourly();
```

### External API Sync

```php
Schedule::job(new SyncInventoryFromSupplier)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new SyncOrdersToShipping)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->pingOnFailure(config('services.healthcheck.orders'));
```

### Report Generation Pipeline

```php
// Step 1: Gather data (runs first)
Schedule::command('reports:gather-data')
    ->dailyAt('05:00');

// Step 2: Generate reports (must run after gather)
Schedule::command('reports:generate')
    ->dailyAt('05:30');  // 30 min buffer

// Step 3: Distribute reports
Schedule::command('reports:distribute')
    ->dailyAt('06:00');
```

### Queue Health Check

```php
Schedule::call(function () {
    $size = Queue::size('default');

    if ($size > 1000) {
        Notification::route('slack', config('services.slack.ops'))
            ->notify(new QueueBacklog($size));
    }

    cache()->put('queue_size', $size);
})->everyFiveMinutes();
```

---

## 10. Troubleshooting

### Task Not Running

**Check 1: Cron entry exists**

```bash
crontab -l
# Should show: * * * * * cd /path && php artisan schedule:run
```

**Check 2: Path is correct**

```bash
cd /path-to-your-project && php artisan schedule:run
# Should not error
```

**Check 3: PHP is accessible**

```bash
which php
# Should return path
```

**Check 4: Task is due**

```bash
php artisan schedule:list
# Check "Next Due" column
```

**Check 5: Conditions pass**

```php
// Add temporary logging
Schedule::command('my:task')
    ->daily()
    ->when(function () {
        $shouldRun = someCondition();
        Log::info('Task condition check', ['result' => $shouldRun]);
        return $shouldRun;
    });
```

### Task Running Multiple Times

- Check if cron entry is duplicated
- Verify `onOneServer()` for multi-server setups
- Check if `withoutOverlapping()` is needed

### Task Not Completing

**Check logs:**

```bash
tail -f storage/logs/laravel.log
```

**Check for memory issues:**

```php
Schedule::command('heavy:task')
    ->daily()
    ->before(function () {
        Log::info('Memory at start', ['memory' => memory_get_usage(true)]);
    })
    ->after(function () {
        Log::info('Memory at end', ['memory' => memory_get_usage(true)]);
    });
```

### Debugging Schedule Expression

```php
use Illuminate\Console\Scheduling\Schedule;

// In tinker
$schedule = app(Schedule::class);
foreach ($schedule->events() as $event) {
    dump([
        'command' => $event->command,
        'expression' => $event->expression,
        'next_run' => $event->nextRunDate()->format('Y-m-d H:i:s'),
    ]);
}
```

### Lock Stuck (withoutOverlapping)

If a task crashed while running, the lock might be stuck:

```bash
# Clear schedule mutex locks
php artisan cache:clear

# Or specifically for Redis
redis-cli KEYS "laravel_cache:framework/schedule*" | xargs redis-cli DEL
```

---

## Quick Reference

### Server Setup

```bash
# Single cron entry (all servers)
* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

### Key Options

| Option                         | Purpose                                         |
| ------------------------------ | ----------------------------------------------- |
| `withoutOverlapping(60)`       | Prevent concurrent runs, lock expires in 60 min |
| `onOneServer()`                | Only one server runs (needs shared cache)       |
| `runInBackground()`            | Don't block other tasks                         |
| `evenInMaintenanceMode()`      | Run during `php artisan down`                   |
| `environments(['production'])` | Only in specific environments                   |
| `when($closure)`               | Conditional execution                           |
| `skip($closure)`               | Skip if condition true                          |

### Monitoring Methods

| Method                         | Purpose                 |
| ------------------------------ | ----------------------- |
| `pingBefore($url)`             | Ping before start       |
| `pingOnSuccess($url)`          | Ping on success         |
| `pingOnFailure($url)`          | Ping on failure         |
| `thenPing($url)`               | Ping after (any result) |
| `emailOutputTo($email)`        | Email output            |
| `emailOutputOnFailure($email)` | Email only on failure   |
| `sendOutputTo($path)`          | Write to file           |
| `appendOutputTo($path)`        | Append to file          |

### Callbacks

| Callback        | When                         |
| --------------- | ---------------------------- |
| `before(fn)`    | Before task starts           |
| `after(fn)`     | After task ends (any result) |
| `onSuccess(fn)` | Only on success              |
| `onFailure(fn)` | Only on failure              |

### Testing Commands

| Command         | Purpose                           |
| --------------- | --------------------------------- |
| `schedule:list` | Show all tasks with next run time |
| `schedule:run`  | Execute due tasks                 |
| `schedule:work` | Run scheduler continuously (dev)  |
| `schedule:test` | Run specific task interactively   |

---

## See Also

- [Scheduled Tasks Implementation Guide](Laravel-Scheduled-Tasks-Guide.md) — Setup and basics
- [Standard Jobs Production Reference](../standard-jobs/Laravel-Jobs-Production-Reference.md) — Queue workers and job config
- [Batch Jobs Production Reference](../batch-jobs/Laravel-Batch-Jobs-Production-Reference.md) — Batch processing

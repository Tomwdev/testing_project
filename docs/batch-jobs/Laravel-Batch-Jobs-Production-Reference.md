# Laravel Batch Jobs — Production & Advanced Patterns Reference

A companion reference to the Implementation Guide. Covers infrastructure, deployment, error handling, testing, and advanced patterns for production batch job systems.

---

## Table of Contents

1. [Queue Drivers](#1-queue-drivers)
2. [Production Worker Setup](#2-production-worker-setup)
3. [Error Handling & Retry Strategies](#3-error-handling--retry-strategies)
4. [Idempotency](#4-idempotency)
5. [Testing Batch Jobs](#5-testing-batch-jobs)
6. [Monitoring & Debugging](#6-monitoring--debugging)
7. [Rate Limiting](#7-rate-limiting)
8. [Performance & Memory](#8-performance--memory)
9. [Advanced Batch Features](#9-advanced-batch-features)
10. [Security Considerations](#10-security-considerations)

---

## 1. Queue Drivers

### Available Drivers

| Driver       | Best For                          | Pros                                    | Cons                              |
| ------------ | --------------------------------- | --------------------------------------- | --------------------------------- |
| `sync`       | Local development, debugging      | Immediate execution, easy debugging     | Blocks request, no async          |
| `database`   | Simple apps, getting started      | No extra infrastructure, works anywhere | Slower, table locking at scale    |
| `redis`      | Production apps                   | Fast, reliable, works with Horizon      | Requires Redis server             |
| `sqs`        | AWS infrastructure, massive scale | Managed service, auto-scaling           | AWS lock-in, eventual consistency |
| `beanstalkd` | High-throughput systems           | Purpose-built for queues                | Additional service to manage      |

### Configuration

In `.env`:

```bash
# Development
QUEUE_CONNECTION=database

# Production
QUEUE_CONNECTION=redis
```

In `config/queue.php`:

```php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,        // Seconds before job is retried if worker dies
        'block_for' => null,
        'after_commit' => false,    // Wait for DB transaction to commit before dispatch
    ],
],
```

### When to Use Each

```
Development → sync or database
Staging → database or redis
Production (small) → database with few workers
Production (medium) → redis + Horizon
Production (large) → redis + Horizon OR sqs
```

---

## 2. Production Worker Setup

### Basic Worker Command

```bash
php artisan queue:work --queue=high,default,low --tries=3 --timeout=90
```

| Flag                       | Purpose                                |
| -------------------------- | -------------------------------------- |
| `--queue=high,default,low` | Priority order of queues to process    |
| `--tries=3`                | Max attempts before job is failed      |
| `--timeout=90`             | Max seconds a job can run              |
| `--memory=128`             | Restart worker if memory exceeds (MB)  |
| `--sleep=3`                | Seconds to wait when no jobs available |
| `--max-jobs=1000`          | Restart after processing N jobs        |
| `--max-time=3600`          | Restart after N seconds                |

### Supervisor Configuration

Supervisor keeps workers running and restarts them if they crash.

Install:

```bash
sudo apt-get install supervisor
```

Create config at `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/your-app/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/var/www/your-app/storage/logs/worker.log
stopwaitsecs=3600
```

Start Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### Laravel Horizon (Recommended for Redis)

Horizon provides a dashboard and better worker management for Redis queues.

Install:

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate
```

Configure in `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['high', 'default', 'low'],
            'balance' => 'auto',          // auto-balance workers across queues
            'minProcesses' => 1,
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 90,
        ],
    ],
    'local' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'simple',
            'processes' => 3,
            'tries' => 3,
        ],
    ],
],
```

Run Horizon:

```bash
php artisan horizon
```

Supervisor config for Horizon:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/your-app/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/your-app/storage/logs/horizon.log
stopwaitsecs=3600
```

### Deployment Restarts

Always restart workers after deployment (code changes aren't picked up automatically):

```bash
# Standard workers
php artisan queue:restart

# Horizon
php artisan horizon:terminate
```

Add to deployment script:

```bash
php artisan queue:restart  # Graceful restart after current job
```

---

## 3. Error Handling & Retry Strategies

### Job Retry Configuration

```php
class ProcessItem implements ShouldQueue
{
    use Queueable, Batchable;

    public $tries = 3;                      // Max attempts
    public $maxExceptions = 2;              // Max exceptions before failing (can differ from tries)
    public $timeout = 120;                  // Seconds before job times out
    public $backoff = [10, 60, 300];        // Seconds between retries (exponential)

    // OR calculate backoff dynamically
    public function backoff(): array
    {
        return [10, 60, 300]; // 10s, 1min, 5min
    }
}
```

### The failed() Method

Called after all retries are exhausted:

```php
public function failed(Throwable $exception): void
{
    // Log the failure
    Log::error('Job permanently failed', [
        'job' => static::class,
        'model_id' => $this->modelId,
        'error' => $exception->getMessage(),
    ]);

    // Notify developers
    Notification::route('slack', config('services.slack.webhook'))
        ->notify(new JobFailedNotification($this, $exception));

    // Update database state
    YourModel::find($this->modelId)?->update(['status' => 'failed']);
}
```

### Release Back to Queue

Temporarily release a job to retry later (doesn't count against $tries):

```php
public function handle(): void
{
    if ($this->shouldWait()) {
        $this->release(60); // Try again in 60 seconds
        return;
    }

    // Continue processing...
}
```

### Fail Immediately

```php
public function handle(): void
{
    if ($this->isInvalidData()) {
        $this->fail('Invalid data provided');
        return;
    }
}
```

### Batch-Specific Error Handling

```php
$batch = Bus::batch($jobs)
    ->catch(function (Batch $batch, Throwable $e) {
        // Called on FIRST failure only

        // Notify admin
        AdminNotification::send("Batch {$batch->name} had failures");

        // Optionally cancel remaining jobs
        // $batch->cancel();
    })
    ->finally(function (Batch $batch) {
        // Always runs - good for cleanup and summary

        if ($batch->failedJobs > 0) {
            Log::warning("Batch completed with failures", [
                'batch_id' => $batch->id,
                'total' => $batch->totalJobs,
                'failed' => $batch->failedJobs,
            ]);
        }
    })
    ->allowFailures()
    ->dispatch();
```

### Failed Jobs Table

Failed jobs are stored in `failed_jobs` table:

```bash
# View failed jobs
php artisan queue:failed

# Retry a specific job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all

# Delete a failed job
php artisan queue:forget <job-id>

# Delete all failed jobs
php artisan queue:flush
```

---

## 4. Idempotency

**Idempotent = Safe to run multiple times with the same result.**

Critical for production because jobs CAN run multiple times due to:

- Worker crashes mid-job
- Network timeouts with job still completing
- Manual retries

### Bad (Not Idempotent)

```php
public function handle(): void
{
    $user = User::find($this->userId);
    $user->increment('points', 100);  // ❌ Running twice = 200 points
}
```

### Good (Idempotent)

```php
public function handle(): void
{
    // Check if already processed
    if (PointsLog::where('user_id', $this->userId)
                 ->where('transaction_id', $this->transactionId)
                 ->exists()) {
        return; // Already processed, skip
    }

    DB::transaction(function () {
        User::find($this->userId)->increment('points', 100);

        PointsLog::create([
            'user_id' => $this->userId,
            'transaction_id' => $this->transactionId,
            'points' => 100,
        ]);
    });
}
```

### Patterns for Idempotency

| Pattern                  | How                                                                          |
| ------------------------ | ---------------------------------------------------------------------------- |
| **Unique constraint**    | Database prevents duplicate records                                          |
| **Status check**         | Check status before processing (`if ($order->status !== 'pending') return;`) |
| **Idempotency key**      | Store processed transaction IDs                                              |
| **Upsert**               | Use `updateOrCreate()` instead of `create()`                                 |
| **syncWithoutDetaching** | For pivot tables (prevents duplicate relations)                              |

### ShouldBeUnique

Prevent duplicate jobs from being dispatched:

```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessItem implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return $this->modelType . ':' . $this->modelId;
    }

    // Optional: How long to maintain uniqueness
    public $uniqueFor = 3600; // 1 hour
}
```

---

## 5. Testing Batch Jobs

### Fake the Queue

Prevent jobs from actually running during tests:

```php
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Bus;

public function test_bulk_tag_creates_batch(): void
{
    Bus::fake();

    $response = $this->postJson('/bulk-tags', [
        'item_ids' => [1, 2, 3],
        'item_type' => 'note',
        'tag_id' => 1,
        'action' => 'attach',
    ]);

    $response->assertOk();

    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 3;
    });
}
```

### Assert Job Was Pushed

```php
Bus::fake();

// Trigger the action...

Bus::assertDispatched(AssignTagToItem::class, function ($job) {
    return $job->modelId === 1 && $job->action === 'attach';
});

Bus::assertDispatchedTimes(AssignTagToItem::class, 3);
```

### Test Job Logic Directly

```php
public function test_assign_tag_job_attaches_tag(): void
{
    $note = Note::factory()->create();
    $tag = Tag::factory()->create();

    $job = new AssignTagToItem(
        Note::class,
        $note->id,
        $tag->id,
        'attach'
    );

    $job->handle();

    $this->assertTrue($note->fresh()->tags->contains($tag));
}
```

### Test with Synchronous Queue

Run jobs immediately during tests:

```php
// In phpunit.xml or test
<env name="QUEUE_CONNECTION" value="sync"/>
```

Or per-test:

```php
public function test_full_flow(): void
{
    config(['queue.default' => 'sync']);

    // Jobs run immediately when dispatched
}
```

### Testing Batch Callbacks

```php
public function test_batch_callbacks_fire(): void
{
    $callbackFired = false;

    Bus::fake([AssignTagToItem::class]);

    Bus::batch([
        new AssignTagToItem(...),
    ])
    ->finally(function () use (&$callbackFired) {
        $callbackFired = true;
    })
    ->dispatch();

    // Manually process the batch
    Bus::dispatchSync(/* ... */);

    $this->assertTrue($callbackFired);
}
```

---

## 6. Monitoring & Debugging

### Laravel Horizon Dashboard

Access at `/horizon` (configure auth in `HorizonServiceProvider`):

```php
// app/Providers/HorizonServiceProvider.php
protected function gate(): void
{
    Gate::define('viewHorizon', function ($user) {
        return in_array($user->email, [
            'admin@example.com',
        ]);
    });
}
```

### Failed Jobs Table

```sql
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;
```

Key columns:

- `uuid` — Unique job identifier
- `connection` — Queue connection
- `queue` — Queue name
- `payload` — Serialized job data (inspect to debug)
- `exception` — Full stack trace
- `failed_at` — When it failed

### Logging in Jobs

```php
public function handle(): void
{
    Log::info('Processing item', [
        'job' => static::class,
        'model_id' => $this->modelId,
        'batch_id' => $this->batch()?->id,
    ]);

    // ... do work ...

    Log::info('Item processed successfully', [
        'model_id' => $this->modelId,
    ]);
}
```

### Laravel Telescope

Install for detailed request/job inspection:

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

### Debugging Tips

| Problem            | Solution                                                 |
| ------------------ | -------------------------------------------------------- |
| Job not running    | Check worker is running: `php artisan queue:work`        |
| Job keeps retrying | Check `$tries`, look at exception in `failed_jobs`       |
| Job stuck          | Check `retry_after` in queue config, check for deadlocks |
| Memory issues      | Add `--memory=128` flag, check for memory leaks          |
| Slow jobs          | Add logging with timestamps, profile the job             |

### Health Checks

```php
// Check queue is processing
$pending = DB::table('jobs')->count();
$failed = DB::table('failed_jobs')->where('failed_at', '>', now()->subHour())->count();

if ($pending > 1000) {
    // Alert: Queue backlog
}

if ($failed > 10) {
    // Alert: High failure rate
}
```

---

## 7. Rate Limiting

### Job Middleware

Create middleware for rate limiting external API calls:

```php
// app/Jobs/Middleware/RateLimited.php
namespace App\Jobs\Middleware;

use Illuminate\Support\Facades\RateLimiter;

class RateLimited
{
    public function __construct(
        public string $key = 'default',
        public int $maxAttempts = 10,
        public int $decaySeconds = 60
    ) {}

    public function handle($job, $next)
    {
        if (RateLimiter::tooManyAttempts($this->key, $this->maxAttempts)) {
            $job->release($this->decaySeconds);
            return;
        }

        RateLimiter::hit($this->key, $this->decaySeconds);

        $next($job);
    }
}
```

### Apply Middleware to Job

```php
use App\Jobs\Middleware\RateLimited;

class CallExternalApi implements ShouldQueue
{
    public function middleware(): array
    {
        return [
            new RateLimited('external-api', maxAttempts: 30, decaySeconds: 60),
        ];
    }
}
```

### Redis-Based Throttling

```php
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;

public function middleware(): array
{
    return [
        // Only allow one job with this key at a time
        new WithoutOverlapping($this->orderId),

        // If exceptions occur, wait before retrying
        new ThrottlesExceptions(maxAttempts: 3, decayMinutes: 5),
    ];
}
```

---

## 8. Performance & Memory

### Chunking Large Datasets

Never load thousands of records at once:

```php
// ❌ Bad - loads all into memory
$jobs = Note::all()->map(fn($note) => new ProcessNote($note->id));

// ✅ Good - chunks into smaller batches
$jobs = [];
Note::chunk(100, function ($notes) use (&$jobs) {
    foreach ($notes as $note) {
        $jobs[] = new ProcessNote($note->id);
    }
});
```

### Lazy Collections

```php
Note::lazy()->each(function ($note) {
    ProcessNote::dispatch($note->id);
});
```

### Worker Memory Management

```bash
# Restart worker if it exceeds 128MB
php artisan queue:work --memory=128

# Restart after processing 1000 jobs (prevents memory leaks)
php artisan queue:work --max-jobs=1000
```

### Avoid N+1 in Jobs

```php
// ❌ Bad - N+1 query
public function handle(): void
{
    $note = Note::find($this->noteId);
    foreach ($note->tags as $tag) { // Query per iteration
        // ...
    }
}

// ✅ Good - Eager load
public function handle(): void
{
    $note = Note::with('tags')->find($this->noteId);
    foreach ($note->tags as $tag) {
        // ...
    }
}
```

### Queue Priority

Process important jobs first:

```php
// Dispatch to specific queue
ProcessPayment::dispatch($order)->onQueue('high');
ProcessEmail::dispatch($email)->onQueue('low');

// Worker processes in priority order
php artisan queue:work --queue=high,default,low
```

---

## 9. Advanced Batch Features

### Dispatch to Specific Queue

```php
$batch = Bus::batch($jobs)
    ->onQueue('bulk-operations')
    ->dispatch();
```

### Dispatch to Specific Connection

```php
$batch = Bus::batch($jobs)
    ->onConnection('redis')
    ->dispatch();
```

### Add Jobs to Running Batch

```php
// In a job within the batch
public function handle(): void
{
    // ... do work ...

    // Dynamically add more jobs to this batch
    if ($this->needsMoreProcessing()) {
        $this->batch()->add([
            new AdditionalJob($this->relatedId),
        ]);
    }
}
```

### Chaining After Batch

Run jobs after batch completes:

```php
$batch = Bus::batch($jobs)
    ->then(function (Batch $batch) {
        // Dispatch follow-up job
        GenerateReport::dispatch($batch->id);
    })
    ->dispatch();
```

### Job Chains Within Batch

```php
$batch = Bus::batch([
    [
        new ProcessOrder($orderId),
        new SendConfirmation($orderId),
        new NotifyWarehouse($orderId),
    ],
    [
        new ProcessOrder($orderId2),
        new SendConfirmation($orderId2),
        new NotifyWarehouse($orderId2),
    ],
])->dispatch();
```

Each array is a chain — jobs run sequentially within the chain, but chains run in parallel.

### Nested Batches

```php
$batch = Bus::batch([
    new ProcessCategory($categoryId),
])->dispatch();

// Inside ProcessCategory job
public function handle(): void
{
    $products = $this->category->products;

    $this->batch()->add(
        $products->map(fn($p) => new ProcessProduct($p->id))->toArray()
    );
}
```

---

## 10. Security Considerations

### Authorize Batch Operations

```php
public function store(Request $request)
{
    $validated = $request->validate([...]);

    // Verify user owns all items
    $itemCount = Note::whereIn('id', $validated['item_ids'])
        ->where('user_id', auth()->id())
        ->count();

    if ($itemCount !== count($validated['item_ids'])) {
        abort(403, 'Unauthorized access to one or more items');
    }

    // Proceed with batch...
}
```

### Rate Limit Batch Creation

In `bootstrap/app.php` or route middleware:

```php
RateLimiter::for('batch-operations', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
});
```

Apply to route:

```php
Route::post('/bulk-tags', [BulkTagController::class, 'store'])
    ->middleware('throttle:batch-operations');
```

### Limit Batch Size

```php
$validated = $request->validate([
    'item_ids' => ['required', 'array', 'min:1', 'max:500'], // Limit to 500 items
    'item_ids.*' => ['integer'],
]);
```

### Secure Batch Status Endpoint

Only allow users to view their own batches:

```php
public function show(string $batchId)
{
    $batch = Bus::findBatch($batchId);

    if (!$batch) {
        return response()->json(['error' => 'Batch not found'], 404);
    }

    // Store user_id in batch name or metadata
    // Or maintain a batch_user pivot table
    if (!$this->userOwnsBatch($batch, auth()->user())) {
        abort(403);
    }

    return response()->json([...]);
}
```

---

## Quick Reference Cards

### Worker Commands

| Command             | Purpose                                                  |
| ------------------- | -------------------------------------------------------- |
| `queue:work`        | Process jobs (stays running)                             |
| `queue:listen`      | Process jobs (restarts after each job — slower, for dev) |
| `queue:restart`     | Gracefully restart all workers                           |
| `queue:retry all`   | Retry all failed jobs                                    |
| `queue:flush`       | Delete all failed jobs                                   |
| `queue:failed`      | List failed jobs                                         |
| `horizon`           | Run Horizon (Redis only)                                 |
| `horizon:terminate` | Gracefully stop Horizon                                  |

### Debugging Commands

| Command                             | Purpose                   |
| ----------------------------------- | ------------------------- |
| `queue:failed`                      | List failed jobs with IDs |
| `queue:retry <id>`                  | Retry specific failed job |
| `tinker` → `Bus::findBatch('<id>')` | Inspect batch status      |
| `tail -f storage/logs/laravel.log`  | Watch logs in real-time   |

### Environment Variables

```bash
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_QUEUE=default
```

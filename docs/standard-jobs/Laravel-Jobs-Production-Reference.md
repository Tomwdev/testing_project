# Laravel Jobs — Production & Advanced Patterns Reference

A companion reference to the Implementation Guide. Covers infrastructure, deployment, error handling, testing, and advanced patterns for production job systems.

---

## Table of Contents

1. [Queue Drivers](#1-queue-drivers)
2. [Production Worker Setup](#2-production-worker-setup)
3. [Error Handling & Retry Strategies](#3-error-handling--retry-strategies)
4. [Idempotency](#4-idempotency)
5. [Testing Jobs](#5-testing-jobs)
6. [Monitoring & Debugging](#6-monitoring--debugging)
7. [Job Middleware](#7-job-middleware)
8. [Performance & Memory](#8-performance--memory)
9. [Job Chaining](#9-job-chaining)
10. [Unique Jobs](#10-unique-jobs)
11. [Events & Transactions](#11-events--transactions)
12. [Security Considerations](#12-security-considerations)

---

## 1. Queue Drivers

### Available Drivers

| Driver       | Best For                          | Pros                                | Cons                           |
| ------------ | --------------------------------- | ----------------------------------- | ------------------------------ |
| `sync`       | Local development, debugging      | Immediate execution, easy debugging | Blocks request, no async       |
| `database`   | Simple apps, getting started      | No extra infrastructure             | Slower, table locking at scale |
| `redis`      | Production apps                   | Fast, reliable, Horizon support     | Requires Redis server          |
| `sqs`        | AWS infrastructure, massive scale | Managed, auto-scaling               | AWS lock-in                    |
| `beanstalkd` | High-throughput systems           | Purpose-built for queues            | Extra service                  |

### Configuration

In `.env`:

```bash
# Development
QUEUE_CONNECTION=sync

# Staging
QUEUE_CONNECTION=database

# Production
QUEUE_CONNECTION=redis
```

### Queue Connection Settings

In `config/queue.php`:

```php
'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,        // Re-release job if worker dies
        'after_commit' => false,    // Wait for DB commit before dispatch
    ],

    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
    ],
],
```

### Driver Decision Matrix

```
┌─────────────────────────────────────────────────────────────┐
│                   Which Queue Driver?                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Is this local development?                                  │
│      YES → sync (or database for testing queue behavior)    │
│      NO  ↓                                                   │
│                                                              │
│  Do you have Redis available?                                │
│      YES → redis + Horizon                                   │
│      NO  ↓                                                   │
│                                                              │
│  Is this on AWS with high scale requirements?                │
│      YES → sqs                                               │
│      NO  → database                                          │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Production Worker Setup

### Worker Command Options

```bash
php artisan queue:work [options]
```

| Option                     | Default | Purpose                             |
| -------------------------- | ------- | ----------------------------------- |
| `--queue=high,default,low` | default | Priority order of queues            |
| `--tries=3`                | 1       | Max attempts before failing         |
| `--timeout=60`             | 60      | Max seconds per job                 |
| `--memory=128`             | 128     | Restart if memory exceeds (MB)      |
| `--sleep=3`                | 3       | Seconds to wait when queue empty    |
| `--max-jobs=1000`          | 0       | Restart after N jobs (0 = never)    |
| `--max-time=3600`          | 0       | Restart after N seconds (0 = never) |
| `--rest=0`                 | 0       | Seconds to rest between jobs        |

### Production Command Example

```bash
php artisan queue:work redis --queue=high,default,low --tries=3 --timeout=90 --memory=128 --max-jobs=1000
```

### Supervisor Configuration

Keep workers running across crashes and reboots.

Install:

```bash
sudo apt-get install supervisor
```

Config file at `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/your-app/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/your-app/storage/logs/worker.log
stopwaitsecs=3600
```

| Setting             | Purpose                                     |
| ------------------- | ------------------------------------------- |
| `numprocs=4`        | Run 4 worker processes                      |
| `autorestart=true`  | Restart if worker dies                      |
| `stopasgroup=true`  | Stop all child processes together           |
| `stopwaitsecs=3600` | Wait up to 1 hour for job to finish on stop |

Apply config:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### Multiple Queue Workers

For different queues with different resources:

```ini
[program:laravel-worker-high]
command=php /var/www/app/artisan queue:work --queue=high --tries=3 --timeout=30
numprocs=4

[program:laravel-worker-default]
command=php /var/www/app/artisan queue:work --queue=default --tries=3 --timeout=90
numprocs=2

[program:laravel-worker-low]
command=php /var/www/app/artisan queue:work --queue=low --tries=3 --timeout=300
numprocs=1
```

### Laravel Horizon (Redis Only)

Dashboard and advanced worker management.

Install:

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate
```

Run:

```bash
php artisan horizon
```

Configure supervisors in `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-high' => [
            'connection' => 'redis',
            'queue' => ['high'],
            'balance' => 'simple',
            'processes' => 4,
            'tries' => 3,
            'timeout' => 30,
        ],
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 10,
            'tries' => 3,
            'timeout' => 90,
        ],
    ],
],
```

Supervisor config for Horizon:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/app/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/app/storage/logs/horizon.log
stopwaitsecs=3600
```

### Deployment: Restart Workers

Workers don't pick up code changes automatically.

```bash
# Graceful restart (after current job)
php artisan queue:restart

# Horizon
php artisan horizon:terminate
```

Add to deployment script (e.g., Envoy, Deployer):

```bash
php artisan queue:restart
```

---

## 3. Error Handling & Retry Strategies

### Configure Retries

```php
class ProcessPayment implements ShouldQueue
{
    public $tries = 5;           // Max attempts
    public $maxExceptions = 3;   // Max exceptions (can differ from tries)
    public $timeout = 120;       // Seconds before timeout
    public $backoff = [30, 60, 120, 300, 600]; // Retry delays
}
```

### Exponential Backoff

```php
// Static
public $backoff = [10, 60, 300]; // 10s, 1min, 5min

// Dynamic
public function backoff(): array
{
    return [
        1 * 60,   // 1 minute
        5 * 60,   // 5 minutes
        15 * 60,  // 15 minutes
        60 * 60,  // 1 hour
    ];
}
```

### The failed() Method

Called after all retries exhausted:

```php
use Throwable;

public function failed(Throwable $exception): void
{
    // Log the failure
    Log::error('Job permanently failed', [
        'job' => static::class,
        'data' => $this->getData(),
        'error' => $exception->getMessage(),
        'trace' => $exception->getTraceAsString(),
    ]);

    // Notify developers
    Notification::route('slack', config('services.slack.alerts'))
        ->notify(new JobFailedNotification($this, $exception));

    // Update related records
    $this->order?->update(['status' => 'failed']);

    // Create support ticket
    SupportTicket::create([
        'subject' => 'Job Failed: ' . static::class,
        'body' => $exception->getMessage(),
    ]);
}
```

### Fail Immediately (Skip Retries)

```php
public function handle(): void
{
    if ($this->isInvalidData()) {
        $this->fail('Data validation failed - not retrying');
        return;
    }

    if ($this->order->status === 'cancelled') {
        $this->delete(); // Remove from queue, don't fail
        return;
    }
}
```

### Release Back to Queue

Retry later without counting against `$tries`:

```php
public function handle(): void
{
    if ($this->externalServiceUnavailable()) {
        $this->release(300); // Try again in 5 minutes
        return;
    }
}
```

### Retry Failed Jobs

```bash
# List failed jobs
php artisan queue:failed

# Retry specific job
php artisan queue:retry 5

# Retry all failed jobs
php artisan queue:retry all

# Retry jobs that failed in last hour
php artisan queue:retry --range=0-100

# Delete a failed job
php artisan queue:forget 5

# Delete all failed jobs
php artisan queue:flush
```

---

## 4. Idempotency

**Idempotent = Running multiple times produces the same result.**

Essential for production because jobs CAN run multiple times:

- Worker crashes mid-job, job is retried
- Network timeout but job actually completed
- Manual retry of "failed" job that actually succeeded

### ❌ Not Idempotent

```php
public function handle(): void
{
    $user = User::find($this->userId);
    $user->increment('points', 100); // Running twice = 200 points!
}
```

### ✅ Idempotent

```php
public function handle(): void
{
    // Check if already processed
    if (PointsTransaction::where('idempotency_key', $this->transactionId)->exists()) {
        return;
    }

    DB::transaction(function () {
        User::find($this->userId)->increment('points', 100);

        PointsTransaction::create([
            'user_id' => $this->userId,
            'idempotency_key' => $this->transactionId,
            'points' => 100,
        ]);
    });
}
```

### Idempotency Patterns

| Pattern                  | Implementation                                       |
| ------------------------ | ---------------------------------------------------- |
| **Idempotency Key**      | Store unique transaction ID, check before processing |
| **Status Check**         | `if ($order->status !== 'pending') return;`          |
| **Upsert**               | `updateOrCreate()` instead of `create()`             |
| **Database Constraints** | Unique index prevents duplicates                     |
| **Sync Methods**         | `syncWithoutDetaching()` for relations               |

### Example: Payment Processing

```php
public function handle(): void
{
    $payment = Payment::lockForUpdate()->find($this->paymentId);

    // Already processed
    if ($payment->status !== 'pending') {
        return;
    }

    DB::transaction(function () use ($payment) {
        // Process with payment provider
        $result = $this->paymentGateway->charge($payment);

        // Update status atomically
        $payment->update([
            'status' => $result->success ? 'completed' : 'failed',
            'gateway_id' => $result->transactionId,
            'processed_at' => now(),
        ]);
    });
}
```

---

## 5. Testing Jobs

### Fake the Queue

Prevent jobs from running, just record dispatches:

```php
use Illuminate\Support\Facades\Queue;

public function test_order_dispatches_payment_job(): void
{
    Queue::fake();

    // Action that should dispatch job
    $this->post('/orders', $orderData);

    // Assert job was dispatched
    Queue::assertPushed(ProcessPayment::class);
}
```

### Assert Job Properties

```php
Queue::fake();

$this->post('/orders', $orderData);

Queue::assertPushed(ProcessPayment::class, function ($job) {
    return $job->orderId === 123
        && $job->amount === 99.99;
});
```

### Assert Job Count

```php
Queue::assertPushed(SendNotification::class, 3);

Queue::assertNotPushed(ProcessRefund::class);
```

### Assert Queue and Delay

```php
Queue::assertPushedOn('payments', ProcessPayment::class);

Queue::assertPushed(SendReminder::class, function ($job) {
    return $job->delay->eq(now()->addDay());
});
```

### Test Job Logic Directly

```php
public function test_payment_job_charges_card(): void
{
    $paymentGateway = Mockery::mock(PaymentGateway::class);
    $paymentGateway->shouldReceive('charge')->once()->andReturn(new ChargeResult(true));

    $order = Order::factory()->create();
    $job = new ProcessPayment($order->id);

    $job->handle($paymentGateway);

    $this->assertEquals('completed', $order->fresh()->payment_status);
}
```

### Synchronous Testing

Run jobs immediately:

```php
public function test_full_order_flow(): void
{
    // Force sync queue
    config(['queue.default' => 'sync']);

    $response = $this->post('/orders', $orderData);

    // Job already ran
    $this->assertDatabaseHas('payments', ['status' => 'completed']);
}
```

### Test failed() Method

```php
public function test_failed_method_logs_error(): void
{
    Log::fake();

    $job = new ProcessPayment(123);
    $exception = new \Exception('Payment declined');

    $job->failed($exception);

    Log::assertLogged('error', function ($message, $context) {
        return str_contains($message, 'failed');
    });
}
```

---

## 6. Monitoring & Debugging

### Laravel Horizon Dashboard

Access at `/horizon`. Secure it:

```php
// app/Providers/HorizonServiceProvider.php
protected function gate(): void
{
    Gate::define('viewHorizon', function ($user) {
        return $user->isAdmin();
    });
}
```

### Failed Jobs Table

The `failed_jobs` table stores all permanently failed jobs:

| Column       | Content                          |
| ------------ | -------------------------------- |
| `uuid`       | Unique job ID                    |
| `connection` | Queue connection                 |
| `queue`      | Queue name                       |
| `payload`    | Serialized job (decode to debug) |
| `exception`  | Full stack trace                 |
| `failed_at`  | Failure timestamp                |

### Decode Job Payload

```php
php artisan tinker

$failed = DB::table('failed_jobs')->first();
$payload = json_decode($failed->payload, true);
$command = unserialize($payload['data']['command']);
dd($command); // See job properties
```

### Job Logging

```php
public function handle(): void
{
    Log::info('Starting job', [
        'job' => static::class,
        'id' => $this->jobId,
        'attempt' => $this->attempts(),
    ]);

    // ... work ...

    Log::info('Job completed', [
        'job' => static::class,
        'id' => $this->jobId,
        'duration_ms' => $this->getDuration(),
    ]);
}
```

### Laravel Telescope

Full debugging for development:

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

### Common Issues

| Problem              | Cause                                     | Solution                 |
| -------------------- | ----------------------------------------- | ------------------------ |
| Jobs not processing  | No worker running                         | `php artisan queue:work` |
| Jobs stuck           | Timeout too short, `retry_after` too long | Adjust settings          |
| High memory usage    | Memory leaks, no `--max-jobs`             | Add `--max-jobs=1000`    |
| Duplicate processing | Missing idempotency                       | Add idempotency checks   |
| Jobs disappearing    | `sync` driver                             | Set `QUEUE_CONNECTION`   |

### Health Monitoring

```php
// Check queue health
$pendingJobs = DB::table('jobs')->count();
$failedRecent = DB::table('failed_jobs')
    ->where('failed_at', '>', now()->subHour())
    ->count();

if ($pendingJobs > 10000) {
    // Alert: Queue backlog
}

if ($failedRecent > 50) {
    // Alert: High failure rate
}
```

---

## 7. Job Middleware

### Built-in Middleware

```php
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\Middleware\RateLimited;

public function middleware(): array
{
    return [
        // Prevent concurrent execution for same key
        new WithoutOverlapping($this->orderId),

        // Throttle if exceptions occur
        new ThrottlesExceptions(maxAttempts: 3, decayMinutes: 5),

        // Rate limit using named limiter
        new RateLimited('external-api'),
    ];
}
```

### WithoutOverlapping

Prevent race conditions by ensuring only one job with the same key runs at a time:

```php
public function middleware(): array
{
    return [
        (new WithoutOverlapping($this->orderId))
            ->releaseAfter(60)      // Release lock after 60 seconds
            ->expireAfter(3600),    // Lock expires after 1 hour
    ];
}
```

### ThrottlesExceptions

Back off when external service is failing:

```php
public function middleware(): array
{
    return [
        (new ThrottlesExceptions(10, 5))  // 10 exceptions in 5 minutes
            ->backoff(5),                  // Wait 5 minutes before retrying
    ];
}
```

### Custom Middleware

```php
// app/Jobs/Middleware/EnsureActiveSubscription.php
namespace App\Jobs\Middleware;

class EnsureActiveSubscription
{
    public function handle($job, $next)
    {
        if (!$job->user->hasActiveSubscription()) {
            // Delete job without processing
            return;
        }

        $next($job);
    }
}

// Usage
public function middleware(): array
{
    return [new EnsureActiveSubscription];
}
```

### Rate Limiting Middleware

Define limiters in `AppServiceProvider`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    RateLimiter::for('stripe-api', function ($job) {
        return Limit::perMinute(100);
    });

    RateLimiter::for('sendgrid', function ($job) {
        return Limit::perSecond(10);
    });
}
```

Apply to job:

```php
use Illuminate\Queue\Middleware\RateLimited;

public function middleware(): array
{
    return [new RateLimited('stripe-api')];
}
```

---

## 8. Performance & Memory

### Memory Management

```bash
# Restart worker if memory exceeds 128MB
php artisan queue:work --memory=128

# Restart after 1000 jobs to prevent memory leaks
php artisan queue:work --max-jobs=1000

# Restart after 1 hour
php artisan queue:work --max-time=3600
```

### Avoid Loading Large Data

```php
// ❌ Bad - serializes entire collection
public function __construct(
    public Collection $users
) {}

// ✅ Good - serialize only IDs
public function __construct(
    public array $userIds
) {}

public function handle(): void
{
    User::whereIn('id', $this->userIds)
        ->chunk(100, function ($users) {
            // Process in chunks
        });
}
```

### Chunk Processing

```php
public function handle(): void
{
    User::where('needs_sync', true)
        ->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $this->syncUser($user);
            }
        });
}
```

### Lazy Collections

```php
public function handle(): void
{
    User::lazy()->each(function ($user) {
        // Memory-efficient iteration
    });
}
```

### Avoid N+1 Queries

```php
// ❌ Bad
public function handle(): void
{
    $orders = Order::all();
    foreach ($orders as $order) {
        echo $order->user->name; // N+1 query
    }
}

// ✅ Good
public function handle(): void
{
    $orders = Order::with('user')->get();
    foreach ($orders as $order) {
        echo $order->user->name;
    }
}
```

### Queue Priority

Route important jobs to fast queues:

```php
// Dispatch to specific queue
ProcessPayment::dispatch($order)->onQueue('high');
SendEmail::dispatch($user)->onQueue('low');

// Worker processes in order
php artisan queue:work --queue=high,default,low
```

---

## 9. Job Chaining

### Sequential Execution

```php
use Illuminate\Support\Facades\Bus;

Bus::chain([
    new ProcessOrder($orderId),
    new ChargePayment($orderId),
    new SendConfirmation($orderId),
    new NotifyWarehouse($orderId),
])->dispatch();
```

If any job fails, remaining jobs are skipped.

### Chain with Callbacks

```php
Bus::chain([
    new ProcessOrder($orderId),
    new ChargePayment($orderId),
])
->catch(function (Throwable $e) {
    // Handle chain failure
    Log::error('Order chain failed', ['error' => $e->getMessage()]);
})
->dispatch();
```

### Chain on Specific Queue

```php
Bus::chain([...])->onQueue('orders')->dispatch();
```

### Dynamic Chaining

Add to chain from within a job:

```php
public function handle(): void
{
    // Process current job...

    // Conditionally chain another job
    if ($this->needsFollowUp()) {
        Bus::chain([
            new FollowUpJob($this->id),
        ])->dispatch();
    }
}
```

---

## 10. Unique Jobs

### Prevent Duplicate Dispatch

```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessPodcast implements ShouldQueue, ShouldBeUnique
{
    public function __construct(
        public int $podcastId
    ) {}

    // Unique identifier
    public function uniqueId(): string
    {
        return (string) $this->podcastId;
    }

    // How long to enforce uniqueness
    public $uniqueFor = 3600; // 1 hour
}
```

### ShouldBeUniqueUntilProcessing

Allow new job to be dispatched once current one starts processing:

```php
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

class ProcessPodcast implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    // Lock released when job starts processing, not when it completes
}
```

### Custom Unique Lock

```php
public function uniqueId(): string
{
    // Combine multiple properties
    return $this->userId . ':' . $this->action;
}

public function uniqueVia(): Repository
{
    // Use specific cache store
    return Cache::driver('redis');
}
```

---

## 11. Events & Transactions

### After Database Commit

Ensure job only dispatches if transaction commits:

```php
// Per-dispatch
ProcessOrder::dispatch($order)->afterCommit();

// Always for this job class
class ProcessOrder implements ShouldQueue
{
    public $afterCommit = true;
}

// In queue config (affects all jobs)
'database' => [
    'after_commit' => true,
],
```

### Why This Matters

```php
DB::transaction(function () {
    $order = Order::create([...]);

    // ❌ Job might run before transaction commits
    ProcessOrder::dispatch($order);

    // Transaction might roll back, but job was already dispatched!
    throw new Exception('Rollback!');
});

// ✅ Safe - job only dispatches if transaction succeeds
DB::transaction(function () {
    $order = Order::create([...]);
    ProcessOrder::dispatch($order)->afterCommit();
});
```

### Job Events

Listen to job lifecycle events:

```php
// In EventServiceProvider or AppServiceProvider
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobFailed;

Event::listen(JobProcessing::class, function ($event) {
    Log::info('Job starting', ['job' => $event->job->getName()]);
});

Event::listen(JobProcessed::class, function ($event) {
    Log::info('Job completed', ['job' => $event->job->getName()]);
});

Event::listen(JobFailed::class, function ($event) {
    Log::error('Job failed', [
        'job' => $event->job->getName(),
        'exception' => $event->exception->getMessage(),
    ]);
});
```

---

## 12. Security Considerations

### Don't Serialize Sensitive Data

```php
// ❌ Bad - password in serialized payload
public function __construct(
    public string $email,
    public string $password
) {}

// ✅ Good - only reference, fetch when needed
public function __construct(
    public int $userId
) {}
```

### Validate Before Processing

```php
public function handle(): void
{
    $user = User::find($this->userId);

    // User might have been deleted or permissions changed
    if (!$user || !$user->can('perform-action')) {
        return;
    }
}
```

### Rate Limit Job Dispatching

Prevent abuse of endpoints that dispatch jobs:

```php
Route::post('/generate-report', [ReportController::class, 'generate'])
    ->middleware('throttle:10,1'); // 10 per minute
```

### Audit Job Actions

```php
public function handle(): void
{
    $result = $this->performAction();

    AuditLog::create([
        'job' => static::class,
        'user_id' => $this->userId,
        'action' => 'processed',
        'result' => $result,
        'ip' => $this->ipAddress, // Captured at dispatch time
    ]);
}
```

---

## Quick Reference

### Job Class Properties

| Property         | Type       | Purpose                 |
| ---------------- | ---------- | ----------------------- |
| `$tries`         | int        | Max attempts            |
| `$backoff`       | int\|array | Retry delay(s)          |
| `$timeout`       | int        | Max seconds             |
| `$maxExceptions` | int        | Max exceptions allowed  |
| `$queue`         | string     | Default queue           |
| `$connection`    | string     | Default connection      |
| `$afterCommit`   | bool       | Wait for DB transaction |
| `$uniqueFor`     | int        | Unique lock duration    |

### Dispatch Modifiers

| Modifier                  | Purpose               |
| ------------------------- | --------------------- |
| `->onQueue('name')`       | Specify queue         |
| `->onConnection('redis')` | Specify connection    |
| `->delay(60)`             | Delay seconds         |
| `->afterCommit()`         | Wait for DB commit    |
| `->beforeCommit()`        | Don't wait for commit |

### Worker Commands

| Command             | Purpose                         |
| ------------------- | ------------------------------- |
| `queue:work`        | Process jobs (persistent)       |
| `queue:listen`      | Process jobs (restarts per job) |
| `queue:restart`     | Graceful restart                |
| `queue:retry <id>`  | Retry failed job                |
| `queue:retry all`   | Retry all failed                |
| `queue:forget <id>` | Delete failed job               |
| `queue:flush`       | Delete all failed               |
| `queue:failed`      | List failed jobs                |
| `queue:monitor`     | Monitor queue sizes             |

### Environment Variables

```bash
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_QUEUE=default
```

---

## See Also

- [Laravel Batch Jobs Guide](../batch-jobs/Laravel-Batch-Jobs-Guide.md)
- [Laravel Batch Jobs Production Reference](../batch-jobs/Laravel-Batch-Jobs-Production-Reference.md)

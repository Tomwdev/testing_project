# Laravel Jobs — Implementation Guide

A step-by-step blueprint for implementing standard (non-batched) queued jobs in Laravel.

---

## What is a Queued Job?

A job is a unit of work pushed to a queue for asynchronous processing. Instead of blocking the user's request, heavy tasks run in the background.

---

## When to Use Standard Jobs vs Batch Jobs

| Use Standard Jobs When          | Use Batch Jobs When            |
| ------------------------------- | ------------------------------ |
| Single operation per trigger    | Many items need same operation |
| No need to track group progress | Need to track overall progress |
| Operations are independent      | Operations are related/grouped |
| Simple fire-and-forget          | Need cancellation support      |

**Examples of Standard Jobs:**

- Send welcome email after registration
- Process uploaded image (resize, optimize)
- Sync data with external API
- Generate PDF report
- Log activity

---

## Architecture Overview

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                    User triggers action                                       │
│                    (register, upload, etc.)                                   │
└──────────────────────────────────┬───────────────────────────────────────────┘
                                   │
                                   ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                        Controller / Event                                     │
│                                                                               │
│   1. Handle the immediate request                                            │
│   2. Dispatch job to queue                                                   │
│   3. Return response immediately                                             │
└──────────────────────────────────┬───────────────────────────────────────────┘
                                   │ Job::dispatch()
                                   ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                              QUEUE                                            │
│                    Stored in: jobs table (or Redis)                           │
│                                                                               │
│   ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐                │
│   │  SendWelcome    │ │  ProcessImage   │ │  SyncToAPI      │                │
│   │    Email        │ │    #upload123   │ │    #user456     │                │
│   └─────────────────┘ └─────────────────┘ └─────────────────┘                │
└──────────────────────────────────┬───────────────────────────────────────────┘
                                   │ queue:work processes jobs
                                   ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                            Job Handler                                        │
│                                                                               │
│   - Receives serialized data                                                 │
│   - Performs the work                                                         │
│   - Handles success/failure                                                   │
└──────────────────────────────────────────────────────────────────────────────┘
                                   │
                    ┌──────────────┴──────────────┐
                    ▼                              ▼
             ┌───────────┐                  ┌───────────┐
             │  Success  │                  │  Failed   │
             │  (done)   │                  │  (retry?) │
             └───────────┘                  └───────────┘
```

---

## Files You'll Create

| File                   | Purpose                           |
| ---------------------- | --------------------------------- |
| `app/Jobs/YourJob.php` | The job class with business logic |

---

## Prerequisites

Ensure the jobs table exists (for database queue driver):

```bash
php artisan make:queue-table
php artisan migrate
```

---

## Phase 1: Create the Job

```bash
php artisan make:job YourJobName
```

This creates `app/Jobs/YourJobName.php`.

---

## Phase 2: Define the Constructor

The constructor receives and stores data needed for processing. This data is serialized when the job is queued.

### Step-by-Step

| Step    | Task                    | What to Do                                  | Why                         |
| ------- | ----------------------- | ------------------------------------------- | --------------------------- |
| **2.1** | Accept dependencies     | Pass models or primitive data               | Job needs context to work   |
| **2.2** | Use typed properties    | `public User $user` or `public int $userId` | Clear, serializable data    |
| **2.3** | Keep constructor simple | Only store data, no logic                   | Logic belongs in `handle()` |

### Passing Models vs IDs

| Approach                      | Pros                        | Cons                                        |
| ----------------------------- | --------------------------- | ------------------------------------------- |
| **Pass Model** (`User $user`) | Convenient, auto-serializes | Larger payload, stale data if model changes |
| **Pass ID** (`int $userId`)   | Small payload, fresh data   | Must fetch in `handle()`                    |

**Recommendation:**

- Pass models for simple jobs where data freshness doesn't matter
- Pass IDs for jobs where you need latest data or model might be deleted

### Example: Passing Model

```php
public function __construct(
    public User $user
) {}
```

Laravel automatically serializes the model ID and refetches it when the job runs.

### Example: Passing IDs

```php
public function __construct(
    public int $userId,
    public int $orderId
) {}
```

---

## Phase 3: Implement the handle() Method

The `handle()` method contains your business logic. It runs when the worker processes the job.

### Step-by-Step

| Step    | Task                   | What to Do                               | Why                            |
| ------- | ---------------------- | ---------------------------------------- | ------------------------------ |
| **3.1** | Type-hint dependencies | `public function handle(Mailer $mailer)` | Laravel injects from container |
| **3.2** | Validate state         | Check if data still valid                | Model may be deleted           |
| **3.3** | Perform the work       | Your business logic                      | The actual task                |
| **3.4** | Handle edge cases      | Graceful failures                        | Robust operation               |

### Dependency Injection

The `handle()` method supports dependency injection:

```php
public function handle(Mailer $mailer, Logger $logger): void
{
    $mailer->to($this->user)->send(new WelcomeMail());
    $logger->info('Welcome email sent', ['user' => $this->user->id]);
}
```

### Template

```php
public function handle(): void
{
    // 1. Validate state (if using IDs)
    $model = YourModel::find($this->modelId);
    if (!$model) {
        return; // Model was deleted, skip gracefully
    }

    // 2. Perform the work
    // ... your business logic ...

    // 3. Optional: Log completion
    logger('Job completed', ['model_id' => $this->modelId]);
}
```

---

## Phase 4: Dispatch the Job

### From a Controller

```php
use App\Jobs\SendWelcomeEmail;

class RegisterController extends Controller
{
    public function store(Request $request)
    {
        $user = User::create($request->validated());

        // Dispatch job to queue
        SendWelcomeEmail::dispatch($user);

        return redirect('/dashboard');
    }
}
```

### From a Model Event

```php
// In User model
protected static function booted(): void
{
    static::created(function (User $user) {
        SendWelcomeEmail::dispatch($user);
    });
}
```

### From an Event Listener

```php
// In UserRegisteredListener
public function handle(UserRegistered $event): void
{
    SendWelcomeEmail::dispatch($event->user);
}
```

---

## Phase 5: Configure Job Options

### Retry Settings

```php
class YourJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;              // Max attempts
    public $backoff = [10, 60, 300]; // Seconds between retries
    public $timeout = 120;          // Max seconds to run
}
```

### Dynamic Backoff

```php
public function backoff(): array
{
    return [10, 60, 300]; // 10s, 1min, 5min
}
```

### Specify Queue

```php
SendWelcomeEmail::dispatch($user)->onQueue('emails');
```

Or in the job class:

```php
public $queue = 'emails';
```

### Delay Execution

```php
// Wait 5 minutes before processing
SendWelcomeEmail::dispatch($user)->delay(now()->addMinutes(5));
```

---

## Complete Job Example

```php
<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\WelcomeMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Retry configuration
     */
    public $tries = 3;
    public $backoff = [10, 60, 300];
    public $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check user still exists and wants emails
        if (!$this->user->exists || !$this->user->wants_emails) {
            return;
        }

        Mail::to($this->user)->send(new WelcomeMail($this->user));

        logger('Welcome email sent', ['user_id' => $this->user->id]);
    }

    /**
     * Handle job failure after all retries exhausted.
     */
    public function failed(Throwable $exception): void
    {
        logger('Failed to send welcome email', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

---

## Dispatch Methods Reference

| Method                                   | Description                         |
| ---------------------------------------- | ----------------------------------- |
| `Job::dispatch($data)`                   | Push to queue (async)               |
| `Job::dispatchSync($data)`               | Run immediately (sync, for testing) |
| `Job::dispatchNow($data)`                | Alias for dispatchSync              |
| `Job::dispatchAfterResponse($data)`      | Run after HTTP response sent        |
| `Job::dispatchIf($condition, $data)`     | Dispatch only if condition true     |
| `Job::dispatchUnless($condition, $data)` | Dispatch only if condition false    |

### Chaining Jobs

Run jobs sequentially:

```php
use Illuminate\Support\Facades\Bus;

Bus::chain([
    new ProcessUpload($file),
    new OptimizeImage($file),
    new NotifyUser($user),
])->dispatch();
```

If any job fails, remaining jobs are skipped.

### Conditional Dispatch

```php
SendWelcomeEmail::dispatchIf($user->wants_emails, $user);

SendMarketingEmail::dispatchUnless($user->unsubscribed, $user);
```

---

## Job Lifecycle

```
┌─────────────┐
│  dispatch() │ ─── Job serialized, pushed to queue
└──────┬──────┘
       ▼
┌─────────────┐
│   Queued    │ ─── Waiting in jobs table / Redis
└──────┬──────┘
       ▼ Worker picks up job
┌─────────────┐
│  handle()   │ ─── Business logic runs
└──────┬──────┘
       │
   ┌───┴───┐
   ▼       ▼
┌──────┐ ┌──────┐
│ Done │ │ Fail │
└──────┘ └──┬───┘
            ▼
     ┌──────────────┐
     │ Retry?       │
     │ (if tries    │
     │  remaining)  │
     └──────┬───────┘
            │
     ┌──────┴──────┐
     ▼             ▼
┌─────────┐  ┌──────────┐
│ Re-queue│  │ failed() │
│ (backoff)│ │ (logged) │
└─────────┘  └──────────┘
```

---

## Testing Jobs

### Fake the Queue

```php
use Illuminate\Support\Facades\Queue;

public function test_welcome_email_is_dispatched(): void
{
    Queue::fake();

    // Trigger action that dispatches job
    $this->post('/register', $userData);

    Queue::assertPushed(SendWelcomeEmail::class, function ($job) {
        return $job->user->email === 'test@example.com';
    });
}
```

### Test Job Logic Directly

```php
public function test_welcome_email_job_sends_email(): void
{
    Mail::fake();

    $user = User::factory()->create();
    $job = new SendWelcomeEmail($user);

    $job->handle();

    Mail::assertSent(WelcomeMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
}
```

### Synchronous Dispatch for Integration Tests

```php
public function test_full_registration_flow(): void
{
    config(['queue.default' => 'sync']);

    Mail::fake();

    $this->post('/register', $userData);

    // Job ran immediately
    Mail::assertSent(WelcomeMail::class);
}
```

---

## Implementation Checklist

### Job Class

- [ ] Job created with `php artisan make:job`
- [ ] `ShouldQueue` interface implemented
- [ ] `Queueable` trait used
- [ ] Constructor accepts necessary data
- [ ] `handle()` method contains business logic
- [ ] State validation (check model exists)
- [ ] `$tries`, `$backoff`, `$timeout` configured
- [ ] `failed()` method handles permanent failures

### Dispatch

- [ ] Job dispatched from controller/event/listener
- [ ] Correct queue specified (if using multiple queues)
- [ ] Delay set if needed

### Testing

- [ ] Queue worker running for manual testing
- [ ] Unit tests for job logic
- [ ] Feature tests with faked queue

---

## Common Job Patterns

### Email Job

```php
class SendOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public $queue = 'emails';

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        Mail::to($this->order->user)->send(new OrderConfirmationMail($this->order));
    }
}
```

### API Sync Job

```php
class SyncToExternalCRM implements ShouldQueue
{
    use Queueable;

    public $tries = 5;
    public $backoff = [60, 300, 900, 3600];

    public function __construct(public int $userId) {}

    public function handle(CRMClient $crm): void
    {
        $user = User::find($this->userId);
        if (!$user) return;

        $crm->upsertContact($user->toArray());
    }
}
```

### Image Processing Job

```php
class ProcessUploadedImage implements ShouldQueue
{
    use Queueable;

    public $timeout = 300; // Images can take time

    public function __construct(public string $path) {}

    public function handle(ImageProcessor $processor): void
    {
        $processor->resize($this->path, 800, 600);
        $processor->optimize($this->path);
        $processor->generateThumbnail($this->path);
    }
}
```

### Cleanup Job

```php
class CleanupOldRecords implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        ActivityLog::where('created_at', '<', now()->subMonths(6))->delete();

        logger('Cleanup completed');
    }
}
```

---

## Scheduling Jobs

Run jobs on a schedule using Laravel's scheduler.

In `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CleanupOldRecords)->daily();
Schedule::job(new GenerateDailyReport)->dailyAt('08:00');
Schedule::job(new SyncAllUsers, 'sync')->hourly();
```

Run the scheduler:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Quick Reference

### Job Properties

| Property         | Type       | Purpose                       |
| ---------------- | ---------- | ----------------------------- |
| `$tries`         | int        | Max attempts before failing   |
| `$backoff`       | int\|array | Seconds between retries       |
| `$timeout`       | int        | Max seconds per attempt       |
| `$queue`         | string     | Queue name to use             |
| `$connection`    | string     | Queue connection to use       |
| `$maxExceptions` | int        | Max exceptions before failing |

### Dispatch Modifiers

| Method                          | Purpose                 |
| ------------------------------- | ----------------------- |
| `->onQueue('name')`             | Specify queue           |
| `->onConnection('redis')`       | Specify connection      |
| `->delay(60)`                   | Delay in seconds        |
| `->delay(now()->addMinutes(5))` | Delay until time        |
| `->afterCommit()`               | Wait for DB transaction |

### Commands

| Command                        | Purpose                    |
| ------------------------------ | -------------------------- |
| `php artisan make:job JobName` | Create job class           |
| `php artisan queue:work`       | Start processing jobs      |
| `php artisan queue:listen`     | Process (restarts per job) |
| `php artisan queue:restart`    | Restart workers gracefully |
| `php artisan queue:retry all`  | Retry failed jobs          |
| `php artisan queue:flush`      | Delete failed jobs         |

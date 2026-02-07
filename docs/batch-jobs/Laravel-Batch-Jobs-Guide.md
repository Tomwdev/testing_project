# Laravel Batch Jobs — Implementation Guide

A reusable blueprint for implementing batch job processing with progress tracking, failure handling, and cancellation support.

---

## Why Job Batching?

| Approach                  | Problem                                                                       |
| ------------------------- | ----------------------------------------------------------------------------- |
| Single loop in controller | Blocks request for minutes; times out                                         |
| Single job with loop      | One failure kills everything; no progress visibility                          |
| Dispatch individual jobs  | No coordination; can't track overall progress                                 |
| **Job Batching**          | Parallel execution, progress tracking, partial failure handling, cancellation |

---

## Architecture Overview

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                    User triggers bulk operation                               │
└──────────────────────────────────┬───────────────────────────────────────────┘
                                   │ POST /your-endpoint
                                   ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                           Controller@store                                    │
│                                                                               │
│   1. Validate request                                                         │
│   2. Create array of individual jobs (one per item)                          │
│   3. Dispatch as batch with Bus::batch()                                     │
│   4. Return batch ID immediately — work happens async                        │
└──────────────────────────────────┬───────────────────────────────────────────┘
                                   │ Bus::batch([...])
                                   ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                              JOB BATCH                                        │
│                    Stored in: job_batches table                               │
│                                                                               │
│   Batch ID: 9a8b7c6d-5e4f-3a2b-1c0d-9e8f7a6b5c4d                            │
│   Total Jobs: 200                                                             │
│   Pending: 200 → 150 → 100 → 50 → 0                                          │
│   Failed: 0 → 0 → 1 → 1 → 2                                                  │
│                                                                               │
│   ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐                │
│   │  ProcessItem    │ │  ProcessItem    │ │  ProcessItem    │ ...            │
│   │    Item #1      │ │    Item #2      │ │    Item #3      │                │
│   └─────────────────┘ └─────────────────┘ └─────────────────┘                │
└──────────────────────────────────┬───────────────────────────────────────────┘
                                   │ workers process in parallel
                                   ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                         Individual Job                                        │
│                                                                               │
│   - Receives: model type, model ID, operation params                         │
│   - Checks if batch was cancelled                                            │
│   - Loads the model                                                           │
│   - Performs the operation                                                    │
│   - Optionally logs activity                                                  │
└──────────────────────────────────────────────────────────────────────────────┘
                                   │
         ┌─────────────────────────┼─────────────────────────┐
         ▼                         ▼                         ▼
   ┌───────────┐            ┌───────────┐            ┌───────────┐
   │  Success  │            │  Success  │            │  Failed   │
   └───────────┘            └───────────┘            └───────────┘
                                   │
                                   ▼ (when all jobs done)
┌──────────────────────────────────────────────────────────────────────────────┐
│                          Callbacks                                            │
│                                                                               │
│   then()    → All jobs succeeded                                             │
│   catch()   → First job failed (called once)                                 │
│   finally() → Batch finished (always runs)                                   │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## Files You'll Create

| File                                      | Purpose                             |
| ----------------------------------------- | ----------------------------------- |
| `app/Jobs/YourItemJob.php`                | Individual job — processes ONE item |
| `app/Http/Controllers/YourController.php` | Creates batch, returns batch ID     |
| `routes/web.php` or `routes/api.php`      | Routes for trigger, status, cancel  |

---

## Prerequisites

The `job_batches` table must exist. If not, run:

```bash
php artisan make:queue-batches-table
php artisan migrate
```

---

## Phase 1: Create the Individual Item Job

This job processes **ONE item** — it will be dispatched many times within a batch.

```bash
php artisan make:job YourItemJob
```

### Step-by-Step

| Step    | Task                    | What to Do                                         | Why                                                 |
| ------- | ----------------------- | -------------------------------------------------- | --------------------------------------------------- |
| **1.1** | Add Batchable trait     | `use Queueable, Batchable;`                        | Enables `$this->batch()` access                     |
| **1.2** | Define constructor      | Accept identifiers (type + ID), not full models    | Smaller serialization, handles deletions gracefully |
| **1.3** | Check cancellation      | `if ($this->batch()?->cancelled()) return;`        | Allows graceful batch cancellation                  |
| **1.4** | Resolve the model       | `$model = $this->modelType::find($this->modelId);` | Load fresh data at execution time                   |
| **1.5** | Handle missing model    | `if (!$model) return;`                             | Item may be deleted before job runs                 |
| **1.6** | Perform the work        | Your business logic here                           | The actual operation                                |
| **1.7** | Log activity (optional) | Dispatch `LogActivity` or similar                  | Audit trail                                         |

### Why Pass Type + ID, Not the Model?

| Concern                  | Model Approach            | Type+ID Approach                         |
| ------------------------ | ------------------------- | ---------------------------------------- |
| Serialization size       | Large (whole model)       | Small (just IDs)                         |
| Mixed types              | Need separate job classes | One job handles all types                |
| Model deleted before job | Deserialize fails         | `find()` returns null, handle gracefully |

### Template

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class YourItemJob implements ShouldQueue
{
    use Queueable, Batchable;

    public function __construct(
        public string $modelType,   // 'App\Models\Note'
        public int $modelId,        // 42
        // ... other operation parameters
    ) {
        // Constructor stores data only — no logic here
    }

    public function handle(): void
    {
        // 1. Check if batch was cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        // 2. Resolve the model dynamically
        $model = $this->modelType::find($this->modelId);

        // 3. Handle case where item was deleted before job ran
        if (!$model) {
            return;
        }

        // 4. Perform the actual work
        // ... your business logic here ...

        // 5. Optional: Log activity
        // LogActivity::dispatch(...);
    }
}
```

### The Batchable Trait Provides

| Method                        | Purpose                        |
| ----------------------------- | ------------------------------ |
| `$this->batch()`              | Access the parent batch object |
| `$this->batch()->cancelled()` | Check if batch was cancelled   |
| `$this->batch()->progress()`  | Current progress percentage    |

**Note:** Use `?->` (nullsafe operator) because `$this->batch()` returns null if job runs outside a batch (e.g., during testing).

---

## Phase 2: Create the Controller

```bash
php artisan make:controller YourBatchController
```

### Required Imports

```php
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use App\Jobs\YourItemJob;
use Throwable;
```

### Methods to Implement

| Method     | HTTP   | Purpose                                                  |
| ---------- | ------ | -------------------------------------------------------- |
| `store()`  | POST   | Validate → Build jobs → Dispatch batch → Return batch ID |
| `show()`   | GET    | Find batch → Return progress/status                      |
| `cancel()` | DELETE | Find batch → Cancel it                                   |

---

### 2.1: The store() Method

| Step      | Task             | Why                                 |
| --------- | ---------------- | ----------------------------------- |
| **2.1.1** | Validate request | Ensure valid data before processing |
| **2.1.2** | Build jobs array | Create one job instance per item    |
| **2.1.3** | Dispatch batch   | `Bus::batch($jobs)->dispatch()`     |
| **2.1.4** | Return batch ID  | Client uses this to poll for status |

```php
public function store(Request $request)
{
    // 1. Validate the request
    $validated = $request->validate([
        'item_ids' => ['required', 'array', 'min:1'],
        'item_ids.*' => ['integer'],
        // ... other validation rules
    ]);

    // 2. Build the jobs array
    $jobs = collect($validated['item_ids'])->map(function ($id) use ($validated) {
        return new YourItemJob(
            'App\\Models\\YourModel',
            $id,
            // ... other params from $validated
        );
    });

    // 3. Dispatch the batch with callbacks
    $batch = Bus::batch($jobs)
        ->name('Descriptive batch name')
        ->then(function (Batch $batch) {
            // ALL jobs succeeded (zero failures)
            logger('Batch completed successfully', ['batch_id' => $batch->id]);
        })
        ->catch(function (Batch $batch, Throwable $e) {
            // FIRST job failure (called once, not per failure)
            logger('Batch had a failure', ['batch_id' => $batch->id, 'error' => $e->getMessage()]);
        })
        ->finally(function (Batch $batch) {
            // Batch finished (regardless of success/failure)
            logger('Batch finished', [
                'batch_id' => $batch->id,
                'total' => $batch->totalJobs,
                'failed' => $batch->failedJobs,
            ]);
        })
        ->allowFailures()  // Don't cancel batch if one job fails
        ->dispatch();

    // 4. Return batch ID
    return response()->json([
        'batch_id' => $batch->id,
        'total_jobs' => $batch->totalJobs,
    ]);
}
```

### Callback Explanations

| Callback    | When Called                         | Use Case                                   |
| ----------- | ----------------------------------- | ------------------------------------------ |
| `then()`    | All jobs succeeded (zero failures)  | Send "completed successfully" notification |
| `catch()`   | First job fails                     | Log the error; called only once            |
| `finally()` | Batch finished (success or failure) | Cleanup, send summary notification         |

### About allowFailures()

By default, if one job fails, Laravel cancels remaining jobs. Adding `->allowFailures()` lets remaining jobs continue processing.

---

### 2.2: The show() Method

Allows clients to poll for batch progress.

```php
public function show(string $batchId)
{
    $batch = Bus::findBatch($batchId);

    if (!$batch) {
        return response()->json(['error' => 'Batch not found'], 404);
    }

    return response()->json([
        'id' => $batch->id,
        'name' => $batch->name,
        'total_jobs' => $batch->totalJobs,
        'pending_jobs' => $batch->pendingJobs,
        'failed_jobs' => $batch->failedJobs,
        'progress' => $batch->progress(),      // 0-100
        'finished' => $batch->finished(),      // boolean
        'cancelled' => $batch->cancelled(),    // boolean
        'created_at' => $batch->createdAt,
        'finished_at' => $batch->finishedAt,
    ]);
}
```

### Frontend Polling Example

```javascript
const poll = setInterval(async () => {
    const res = await fetch(`/your-endpoint/${batchId}`);
    const data = await res.json();
    updateProgressBar(data.progress);
    if (data.finished) clearInterval(poll);
}, 2000);
```

---

### 2.3: The cancel() Method

Allows users to cancel a running batch.

```php
public function cancel(string $batchId)
{
    $batch = Bus::findBatch($batchId);

    if (!$batch) {
        return response()->json(['error' => 'Batch not found'], 404);
    }

    $batch->cancel();

    return response()->json([
        'cancelled' => true,
        'pending_jobs_cancelled' => $batch->pendingJobs,
    ]);
}
```

### What Happens When Cancelled

- `cancelled_at` timestamp is set in database
- Pending jobs check `$this->batch()->cancelled()` and exit early
- Jobs already running finish their current work
- No new jobs from this batch are started

---

## Phase 3: Add Routes

```php
use App\Http\Controllers\YourBatchController;

Route::middleware('auth')->group(function () {
    Route::post('/your-endpoint', [YourBatchController::class, 'store']);
    Route::get('/your-endpoint/{batchId}', [YourBatchController::class, 'show']);
    Route::delete('/your-endpoint/{batchId}', [YourBatchController::class, 'cancel']);
});
```

---

## Phase 4: Handle CSRF (Web Routes Only)

If your batch endpoints are in `web.php` but receive JSON requests (not form submissions), exclude them from CSRF protection.

In `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: [
        'your-endpoint',
        'your-endpoint/*',
    ]);
})
```

**Alternative:** Put routes in `routes/api.php` instead (no CSRF by default).

---

## Phase 5: Test

| Step    | Command/Action                 | Purpose                                            |
| ------- | ------------------------------ | -------------------------------------------------- |
| **5.1** | `php artisan queue:work`       | Start queue worker (jobs won't process without it) |
| **5.2** | POST to `/your-endpoint`       | Trigger the batch                                  |
| **5.3** | GET `/your-endpoint/{batchId}` | Check progress                                     |
| **5.4** | Query database                 | Verify data was changed                            |
| **5.5** | Check `laravel.log`            | Verify callbacks fired                             |

### Example Test Request

```bash
curl -X POST http://localhost:8000/your-endpoint \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "item_ids": [1, 2, 3, 4, 5],
    "other_param": "value"
  }'
```

---

## Quick Reference

### Batch Object Properties

| Property/Method       | Returns                          |
| --------------------- | -------------------------------- |
| `$batch->id`          | UUID string                      |
| `$batch->name`        | Human-readable name              |
| `$batch->totalJobs`   | Total job count                  |
| `$batch->pendingJobs` | Jobs not yet processed           |
| `$batch->failedJobs`  | Failed job count                 |
| `$batch->progress()`  | 0-100 percentage                 |
| `$batch->finished()`  | Boolean — is batch complete?     |
| `$batch->cancelled()` | Boolean — was batch cancelled?   |
| `$batch->cancel()`    | Cancels the batch                |
| `$batch->createdAt`   | Timestamp                        |
| `$batch->finishedAt`  | Timestamp (null if not finished) |

### The job_batches Table

| Column           | Purpose                                     |
| ---------------- | ------------------------------------------- |
| `id`             | UUID identifying the batch                  |
| `name`           | Optional human-readable name                |
| `total_jobs`     | How many jobs in this batch                 |
| `pending_jobs`   | How many still waiting                      |
| `failed_jobs`    | How many have failed                        |
| `failed_job_ids` | Which specific jobs failed                  |
| `options`        | Serialized callbacks (then, catch, finally) |
| `cancelled_at`   | Timestamp if user cancelled                 |
| `created_at`     | When batch was created                      |
| `finished_at`    | When batch completed                        |

---

## Implementation Checklist

### Job

- [ ] Job created with `php artisan make:job`
- [ ] `Batchable` trait added alongside `Queueable`
- [ ] Constructor uses IDs, not full model instances
- [ ] `handle()` checks `$this->batch()?->cancelled()` first
- [ ] `handle()` handles missing models gracefully (`if (!$model) return`)
- [ ] Business logic implemented
- [ ] Activity logging added (optional)

### Controller

- [ ] Controller created
- [ ] `Bus`, `Batch`, `Throwable` imported
- [ ] `store()` validates input
- [ ] `store()` builds job array from validated data
- [ ] `store()` dispatches batch with callbacks
- [ ] `store()` uses `allowFailures()` if partial success is OK
- [ ] `store()` returns batch ID
- [ ] `show()` returns progress data
- [ ] `show()` handles missing batch (404)
- [ ] `cancel()` cancels the batch
- [ ] `cancel()` handles missing batch (404)

### Routes & Config

- [ ] POST route registered (trigger batch)
- [ ] GET route registered (check progress)
- [ ] DELETE route registered (cancel batch)
- [ ] CSRF excluded if using web routes for JSON API
- [ ] Auth middleware applied

### Testing

- [ ] Queue worker running (`php artisan queue:work`)
- [ ] Test data exists in database
- [ ] POST request returns batch ID
- [ ] GET request shows progress
- [ ] Database reflects expected changes
- [ ] Log shows callback messages

---

## Common Patterns

### Dynamic Model Type

When handling multiple model types (Notes, Projects, Concepts):

```php
// Controller
$modelClass = 'App\\Models\\' . ucfirst($validated['item_type']);

// Job
$model = $this->modelType::find($this->modelId);
```

### Retry Configuration

```php
class YourItemJob implements ShouldQueue
{
    public $tries = 3;                    // Max attempts
    public $backoff = [10, 30, 60];       // Seconds between retries
    public $timeout = 120;                // Max seconds per attempt

    public function failed(Throwable $e): void
    {
        // Called after all retries exhausted
        logger('Job permanently failed', ['error' => $e->getMessage()]);
    }
}
```

### Unique Jobs (Prevent Duplicates)

```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class YourItemJob implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return $this->modelType . ':' . $this->modelId;
    }
}
```

---

## Example Implementations

See these completed examples in this codebase:

| Feature             | Job                            | Controller                                   |
| ------------------- | ------------------------------ | -------------------------------------------- |
| Bulk Tag Assignment | `app/Jobs/AssignTagToItem.php` | `app/Http/Controllers/BulkTagController.php` |

---

## See Also

- [Batch Jobs Production Reference](Laravel-Batch-Jobs-Production-Reference.md) — Infrastructure, monitoring, advanced patterns
- [Standard Jobs Guide](../standard-jobs/Laravel-Jobs-Guide.md) — Non-batched job implementation
- [Standard Jobs Production Reference](../standard-jobs/Laravel-Jobs-Production-Reference.md) — Production patterns for all jobs

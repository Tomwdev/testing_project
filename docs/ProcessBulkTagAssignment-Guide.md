# Job: ProcessBulkTagAssignment — Complete Guide

## What You're Building

A batch processing system that lets users add or remove tags from hundreds of items at once. Uses Laravel's **Job Batching** to process items in parallel with progress tracking, failure handling, and the ability to cancel mid-operation.

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
│                User selects 200 notes + clicks "Add Tag: Laravel"            │
└──────────────────────────────────┬───────────────────────────────────────────┘
                                   │ POST /bulk-tags
                                   ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                        BulkTagController@store                                │
│                                                                               │
│   1. Validate request (item IDs, tag ID, action: add/remove)                 │
│   2. Create a batch of individual jobs (one per item)                        │
│   3. Store batch ID in response                                              │
│   4. Return immediately — work happens async                                 │
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
│   │ AssignTagToItem │ │ AssignTagToItem │ │ AssignTagToItem │ ...            │
│   │   Note #1       │ │   Note #2       │ │   Note #3       │                │
│   └─────────────────┘ └─────────────────┘ └─────────────────┘                │
└──────────────────────────────────┬───────────────────────────────────────────┘
                                   │ workers process in parallel
                                   ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                        AssignTagToItem Job                                    │
│                                                                               │
│   - Receives: model type, model ID, tag ID, action                           │
│   - Loads the model                                                           │
│   - Attaches or detaches the tag                                             │
│   - Logs the activity                                                         │
└──────────────────────────────────────────────────────────────────────────────┘
                                   │
         ┌─────────────────────────┼─────────────────────────┐
         ▼                         ▼                         ▼
   ┌───────────┐            ┌───────────┐            ┌───────────┐
   │  Success  │            │  Success  │            │  Failed   │
   │  Callback │            │  Callback │            │  Callback │
   └───────────┘            └───────────┘            └───────────┘
                                   │
                                   ▼ (when all jobs done)
┌──────────────────────────────────────────────────────────────────────────────┐
│                          then() / finally()                                   │
│                                                                               │
│   - Notify user batch is complete                                            │
│   - Log summary: "198 succeeded, 2 failed"                                   │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## Files You'll Create or Edit

| File                                                   | Purpose                                   |
| ------------------------------------------------------ | ----------------------------------------- |
| `app/Jobs/AssignTagToItem.php`                         | Individual job — processes ONE item       |
| `app/Http/Controllers/BulkTagController.php`           | Creates the batch, returns batch ID       |
| `routes/web.php`                                       | Routes for triggering and checking status |
| `database/migrations/..._create_job_batches_table.php` | Already exists from your setup            |

---

## Phase A: Understanding Your Existing Setup (2 steps)

| Step   | Task                              | What to Do                                                     | Why                                                  |
| ------ | --------------------------------- | -------------------------------------------------------------- | ---------------------------------------------------- |
| **A1** | Verify job_batches table exists   | Run `php artisan migrate:status`                               | Batching stores metadata in this table — you need it |
| **A2** | Review your tagging relationships | Check Note, Project, Concept models have `tags()` relationship | You'll call `->tags()->attach()` and `->detach()`    |

### A1: Why the job_batches Table?

Laravel stores batch state here:

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

The controller can query this table to give the frontend progress updates.

---

## Phase B: Create the Individual Item Job (6 steps)

This job processes **ONE item** — it's dispatched hundreds of times within a batch.

| Step   | Task                     | What to Do                                                                     | Why                                                                     |
| ------ | ------------------------ | ------------------------------------------------------------------------------ | ----------------------------------------------------------------------- |
| **B1** | Generate job             | Run: `php artisan make:job AssignTagToItem`                                    | Creates the job skeleton                                                |
| **B2** | Add Batchable trait      | Add `use Batchable;` inside the class                                          | Allows job to participate in batches — gives access to `$this->batch()` |
| **B3** | Define constructor       | Accept `string $modelType`, `int $modelId`, `int $tagId`, `string $action`     | Job needs to know what to process                                       |
| **B4** | Resolve the model        | Use `$modelType::find($modelId)` to load the actual model                      | We pass type+ID, not the model itself, for serialization efficiency     |
| **B5** | Perform tag operation    | Call `$model->tags()->attach($tagId)` or `->detach($tagId)` based on `$action` | The actual work                                                         |
| **B6** | Check if batch cancelled | Add `if ($this->batch()->cancelled()) { return; }` at start of handle()        | Allows graceful cancellation                                            |

### B2: What is the Batchable Trait?

```php
use Illuminate\Bus\Batchable;

class AssignTagToItem implements ShouldQueue
{
    use Queueable, Batchable;  // ← Add Batchable here
```

This trait gives your job:

- `$this->batch()` — Access the parent batch object
- `$this->batch()->cancelled()` — Check if batch was cancelled
- `$this->batch()->progress()` — Current progress percentage
- Automatic progress tracking — Laravel updates pending_jobs count

### B3: Why Pass Type + ID, Not the Model?

```php
// Option A: Pass the model directly
public function __construct(public Note $note) { }

// Option B: Pass type + ID (BETTER for batching)
public function __construct(
    public string $modelType,  // 'App\Models\Note'
    public int $modelId,       // 42
    public int $tagId,
    public string $action      // 'attach' or 'detach'
) { }
```

**Why Option B is better:**

| Concern                        | Model Approach            | Type+ID Approach                         |
| ------------------------------ | ------------------------- | ---------------------------------------- |
| Serialization size             | Large (whole model)       | Small (just IDs)                         |
| Mixed types (notes + projects) | Need separate job classes | One job class handles all                |
| Model deleted before job runs  | Deserialize fails         | `find()` returns null, handle gracefully |

### B4: Resolving the Model Dynamically

```php
public function handle(): void
{
    // $this->modelType is 'App\Models\Note' or 'App\Models\Project', etc.
    $model = $this->modelType::find($this->modelId);

    if (!$model) {
        // Item was deleted before job ran — skip silently or throw
        return;
    }

    // Now $model is the actual Note/Project/Concept instance
}
```

### B5: Attach vs Detach

Your models already have `tags()` relationships (many-to-many via pivot tables):

```php
// Adding a tag
$model->tags()->attach($this->tagId);

// Removing a tag
$model->tags()->detach($this->tagId);

// Sync without duplicates (safer for attach)
$model->tags()->syncWithoutDetaching([$this->tagId]);
```

Use `syncWithoutDetaching()` for attach to prevent duplicate pivot records if tag already attached.

### B6: Checking for Cancellation

```php
public function handle(): void
{
    // First thing: check if batch was cancelled
    if ($this->batch()?->cancelled()) {
        return;  // Exit early, don't process
    }

    // ... rest of job
}
```

Why `?->` (nullsafe operator)? If job runs outside a batch (for testing), `$this->batch()` returns null.

### Complete Job File

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AssignTagToItem implements ShouldQueue
{
    use Queueable, Batchable;

    public function __construct(
        public string $modelType,
        public int $modelId,
        public int $tagId,
        public string $action
    ) {
        // Constructor is EMPTY - just stores data
    }

    public function handle(): void
    {
        // Check if batch was cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Resolve the model dynamically
        $model = $this->modelType::find($this->modelId);

        // Handle case where item was deleted before job ran
        if (!$model) {
            return;
        }

        // Perform the tag operation
        if ($this->action === 'attach') {
            $model->tags()->syncWithoutDetaching([$this->tagId]);
        } else {
            $model->tags()->detach($this->tagId);
        }
    }
}
```

---

## Phase C: Create the Batch Controller (7 steps)

This controller receives the request, creates the batch, and returns the batch ID.

| Step   | Task                  | What to Do                                                  | Why                                 |
| ------ | --------------------- | ----------------------------------------------------------- | ----------------------------------- |
| **C1** | Generate controller   | Run: `php artisan make:controller BulkTagController`        | Creates controller skeleton         |
| **C2** | Import Bus facade     | Add `use Illuminate\Support\Facades\Bus;`                   | `Bus::batch()` creates batches      |
| **C3** | Import the job        | Add `use App\Jobs\AssignTagToItem;`                         | You'll instantiate this job         |
| **C4** | Create store() method | Accepts request, validates, creates batch                   | Main entry point                    |
| **C5** | Validate the request  | Require `item_ids` (array), `item_type`, `tag_id`, `action` | Ensure valid data before processing |
| **C6** | Build jobs array      | Loop through item_ids, create job instance for each         | Each item becomes one job           |
| **C7** | Dispatch the batch    | `Bus::batch($jobs)->then(...)->catch(...)->dispatch()`      | Launches all jobs with callbacks    |

### C2: What is Bus::batch()?

```php
use Illuminate\Support\Facades\Bus;

$batch = Bus::batch([
    new AssignTagToItem('App\Models\Note', 1, 5, 'attach'),
    new AssignTagToItem('App\Models\Note', 2, 5, 'attach'),
    new AssignTagToItem('App\Models\Note', 3, 5, 'attach'),
    // ... hundreds more
])->dispatch();
```

This:

1. Creates a batch record in `job_batches` table
2. Dispatches all jobs to the queue
3. Returns a `Batch` object with the batch ID
4. Queue workers process jobs in parallel

### C5: Request Validation

```php
$validated = $request->validate([
    'item_ids' => ['required', 'array', 'min:1'],
    'item_ids.*' => ['integer'],
    'item_type' => ['required', 'in:note,project,concept'],
    'tag_id' => ['required', 'exists:tags,id'],
    'action' => ['required', 'in:attach,detach'],
]);
```

This ensures:

- At least one item selected
- All IDs are integers
- Item type is one of your valid models
- Tag actually exists
- Action is valid

### C6: Building the Jobs Array

```php
$modelClass = 'App\\Models\\' . ucfirst($validated['item_type']); // 'App\Models\Note'

$jobs = collect($validated['item_ids'])->map(function ($id) use ($modelClass, $validated) {
    return new AssignTagToItem(
        $modelClass,
        $id,
        $validated['tag_id'],
        $validated['action']
    );
});
```

If user selected 200 notes, this creates 200 job instances.

### C7: Batch Callbacks

```php
$batch = Bus::batch($jobs)
    ->name('Bulk tag assignment')  // Human-readable name for debugging
    ->then(function (Batch $batch) {
        // ALL jobs succeeded
        logger('Batch completed successfully', ['batch_id' => $batch->id]);
    })
    ->catch(function (Batch $batch, Throwable $e) {
        // FIRST job failure (called once, not per failure)
        logger('Batch had a failure', ['batch_id' => $batch->id, 'error' => $e->getMessage()]);
    })
    ->finally(function (Batch $batch) {
        // Batch finished (success OR failure)
        logger('Batch finished', [
            'batch_id' => $batch->id,
            'total' => $batch->totalJobs,
            'failed' => $batch->failedJobs,
        ]);
    })
    ->allowFailures()  // Don't cancel batch if one job fails
    ->dispatch();

return response()->json([
    'batch_id' => $batch->id,
    'total_jobs' => $batch->totalJobs,
]);
```

**Callback explanations:**

| Callback    | When Called                                    | Use Case                                   |
| ----------- | ---------------------------------------------- | ------------------------------------------ |
| `then()`    | All jobs succeeded (zero failures)             | Send "completed successfully" notification |
| `catch()`   | First job fails                                | Log the error; still called only once      |
| `finally()` | Batch finished (regardless of success/failure) | Cleanup, send summary notification         |

**`allowFailures()`** — By default, if one job fails, Laravel cancels remaining jobs. This flag lets remaining jobs continue.

### Complete store() Method

```php
public function store(Request $request)
{
    // Validate the request
    $validated = $request->validate([
        'item_ids' => ['required', 'array', 'min:1'],
        'item_ids.*' => ['integer'],
        'item_type' => ['required', 'in:note,project,concept'],
        'tag_id' => ['required', 'exists:tags,id'],
        'action' => ['required', 'in:attach,detach'],
    ]);

    // Build the model class name and jobs array
    $modelClass = 'App\\Models\\' . ucfirst($validated['item_type']);

    $jobs = collect($validated['item_ids'])->map(function ($id) use ($modelClass, $validated) {
        return new AssignTagToItem(
            $modelClass,
            $id,
            $validated['tag_id'],
            $validated['action']
        );
    })->toArray();

    // Dispatch the batch with callbacks
    $batch = Bus::batch($jobs)
        ->name('Bulk tag ' . $validated['action'])
        ->then(function ($batch) {
            logger('Bulk tag batch completed', ['batch_id' => $batch->id]);
        })
        ->catch(function ($batch, $e) {
            logger('Bulk tag batch had failures', ['batch_id' => $batch->id]);
        })
        ->finally(function ($batch) {
            logger('Bulk tag batch finished', [
                'batch_id' => $batch->id,
                'failed' => $batch->failedJobs,
            ]);
        })
        ->allowFailures()
        ->dispatch();

    return response()->json([
        'batch_id' => $batch->id,
        'total_jobs' => $batch->totalJobs,
    ]);
}
```

---

## Phase D: Add Progress Checking Endpoint (4 steps)

Frontend needs to poll for progress. Create an endpoint that returns batch status.

| Step   | Task                 | What to Do                                 | Why                            |
| ------ | -------------------- | ------------------------------------------ | ------------------------------ |
| **D1** | Create show() method | Query batch by ID, return status           | Frontend polls this endpoint   |
| **D2** | Import Bus facade    | Already done in Phase C                    | Need `Bus::findBatch()`        |
| **D3** | Return progress data | Include progress %, pending, failed counts | Frontend displays progress bar |
| **D4** | Handle missing batch | Return 404 if batch not found              | Batch ID might be invalid      |

### Complete show() Method

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

Frontend can poll every 2 seconds:

```javascript
const poll = setInterval(async () => {
    const res = await fetch(`/bulk-tags/${batchId}`);
    const data = await res.json();
    updateProgressBar(data.progress);
    if (data.finished) clearInterval(poll);
}, 2000);
```

---

## Phase E: Add Cancellation Endpoint (3 steps)

Let users cancel a running batch.

| Step   | Task                   | What to Do                                         | Why                                |
| ------ | ---------------------- | -------------------------------------------------- | ---------------------------------- |
| **E1** | Create cancel() method | Find batch, call `->cancel()`                      | Stops pending jobs from running    |
| **E2** | Check authorization    | Verify user owns this batch (optional enhancement) | Prevent cancelling others' batches |
| **E3** | Return confirmation    | Return cancelled status                            | Frontend updates UI                |

### Complete cancel() Method

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

**What happens when cancelled:**

- `cancelled_at` timestamp is set
- Pending jobs check `$this->batch()->cancelled()` and exit early
- Jobs already running finish their current work
- No new jobs from this batch are started

---

## Phase F: Add Routes (3 steps)

| Step   | Task         | What to Do                                                                    | Why            |
| ------ | ------------ | ----------------------------------------------------------------------------- | -------------- |
| **F1** | POST route   | `Route::post('/bulk-tags', [BulkTagController::class, 'store'])`              | Trigger batch  |
| **F2** | GET route    | `Route::get('/bulk-tags/{batchId}', [BulkTagController::class, 'show'])`      | Check progress |
| **F3** | DELETE route | `Route::delete('/bulk-tags/{batchId}', [BulkTagController::class, 'cancel'])` | Cancel batch   |

### Complete Routes

```php
use App\Http\Controllers\BulkTagController;

Route::middleware('auth')->group(function () {
    Route::post('/bulk-tags', [BulkTagController::class, 'store']);
    Route::get('/bulk-tags/{batchId}', [BulkTagController::class, 'show']);
    Route::delete('/bulk-tags/{batchId}', [BulkTagController::class, 'cancel']);
});
```

---

## Phase G: Add Activity Logging (2 steps)

| Step   | Task                           | What to Do                                 | Why                  |
| ------ | ------------------------------ | ------------------------------------------ | -------------------- |
| **G1** | Log in individual job          | Dispatch `LogActivity` after tag operation | Track what changed   |
| **G2** | Include tag info in properties | Pass tag name and action to activity log   | Context for the user |

### Logging Each Tag Operation

In `AssignTagToItem::handle()`, after attaching/detaching:

```php
use App\Jobs\LogActivity;

// After the tag operation...
LogActivity::dispatch(
    $model->user,
    $this->action === 'attach' ? 'tagged' : 'untagged',
    class_basename($this->modelType),
    $this->modelId
);
```

---

## Phase H: Test the Flow (5 steps)

| Step   | Task                 | What to Do                                | Why                                 |
| ------ | -------------------- | ----------------------------------------- | ----------------------------------- |
| **H1** | Start queue worker   | `php artisan queue:work`                  | Jobs won't process without a worker |
| **H2** | Trigger via API      | POST to `/bulk-tags` with test data       | Creates the batch                   |
| **H3** | Check batch status   | GET `/bulk-tags/{batchId}`                | Watch progress increment            |
| **H4** | Verify tags attached | Check database pivot tables               | Confirm work was done               |
| **H5** | Check logs           | Look in `laravel.log` for batch callbacks | Verify callbacks fired              |

### Test Request

```bash
curl -X POST http://localhost:8000/bulk-tags \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "item_ids": [1, 2, 3, 4, 5],
    "item_type": "note",
    "tag_id": 1,
    "action": "attach"
  }'
```

Response:

```json
{
    "batch_id": "9a8b7c6d-5e4f-3a2b-1c0d-9e8f7a6b5c4d",
    "total_jobs": 5
}
```

### Check Progress

```bash
curl http://localhost:8000/bulk-tags/9a8b7c6d-5e4f-3a2b-1c0d-9e8f7a6b5c4d
```

Response:

```json
{
    "id": "9a8b7c6d-5e4f-3a2b-1c0d-9e8f7a6b5c4d",
    "name": "Bulk tag attach",
    "total_jobs": 5,
    "pending_jobs": 2,
    "failed_jobs": 0,
    "progress": 60,
    "finished": false,
    "cancelled": false
}
```

---

## Complete Controller File

```php
<?php

namespace App\Http\Controllers;

use App\Jobs\AssignTagToItem;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Throwable;

class BulkTagController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
            'item_type' => ['required', 'in:note,project,concept'],
            'tag_id' => ['required', 'exists:tags,id'],
            'action' => ['required', 'in:attach,detach'],
        ]);

        $modelClass = 'App\\Models\\' . ucfirst($validated['item_type']);

        $jobs = collect($validated['item_ids'])->map(function ($id) use ($modelClass, $validated) {
            return new AssignTagToItem(
                $modelClass,
                $id,
                $validated['tag_id'],
                $validated['action']
            );
        })->toArray();

        $batch = Bus::batch($jobs)
            ->name('Bulk tag ' . $validated['action'])
            ->then(function (Batch $batch) {
                logger('Bulk tag batch completed', ['batch_id' => $batch->id]);
            })
            ->catch(function (Batch $batch, Throwable $e) {
                logger('Bulk tag batch had failures', ['batch_id' => $batch->id, 'error' => $e->getMessage()]);
            })
            ->finally(function (Batch $batch) {
                logger('Bulk tag batch finished', [
                    'batch_id' => $batch->id,
                    'total' => $batch->totalJobs,
                    'failed' => $batch->failedJobs,
                ]);
            })
            ->allowFailures()
            ->dispatch();

        return response()->json([
            'batch_id' => $batch->id,
            'total_jobs' => $batch->totalJobs,
        ]);
    }

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
            'progress' => $batch->progress(),
            'finished' => $batch->finished(),
            'cancelled' => $batch->cancelled(),
            'created_at' => $batch->createdAt,
            'finished_at' => $batch->finishedAt,
        ]);
    }

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
}
```

---

## Phase Summary & Order

```
A1 → A2 (verify setup)
    ↓
B1 → B2 → B3 → B4 → B5 → B6 (individual job)
    ↓
C1 → C2 → C3 → C4 → C5 → C6 → C7 (controller + batch creation)
    ↓
D1 → D2 → D3 → D4 (progress endpoint)
    ↓
E1 → E2 → E3 (cancellation endpoint)
    ↓
F1 → F2 → F3 (routes)
    ↓
G1 → G2 (activity logging)
    ↓
H1 → H2 → H3 → H4 → H5 (testing)
```

---

## Files Touched Per Phase

| Phase   | Files                                              |
| ------- | -------------------------------------------------- |
| A       | Verify existing migrations and models              |
| B       | `app/Jobs/AssignTagToItem.php` (new)               |
| C, D, E | `app/Http/Controllers/BulkTagController.php` (new) |
| F       | `routes/web.php`                                   |
| G       | `app/Jobs/AssignTagToItem.php` (add logging)       |
| H       | Testing only                                       |

---

## Key Concepts Learned

| Concept                      | Where Used                                   |
| ---------------------------- | -------------------------------------------- |
| **Job Batching**             | `Bus::batch()` in controller                 |
| **Batchable trait**          | Job class                                    |
| **Dynamic model resolution** | `$modelType::find()`                         |
| **Batch callbacks**          | `then()`, `catch()`, `finally()`             |
| **Progress tracking**        | `$batch->progress()`                         |
| **Graceful cancellation**    | `$batch->cancelled()` check                  |
| **Parallel processing**      | Multiple workers process jobs simultaneously |

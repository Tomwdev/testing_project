<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use App\Jobs\AssignTagToItem;
use Illuminate\Bus\Batch;
use Throwable;

class BulkTagController extends Controller
{
    public function store(Request $request)
    {
        // vaidation
        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
            'item_type' => ['required', 'in:note,project,concept'],
            'tag_id' => ['required', 'exists:tags,id'],
            'action' => ['required', 'in:attach,detach'],
        ]);

        // build model class name and jobs array
        $modelClass = 'App\\Models\\' . ucfirst($validated['item_type']);

        $jobs = collect($validated['item_ids'])->map(function ($id) use ($modelClass, $validated) {
            return new AssignTagToItem(
                $modelClass,
                $id,
                $validated['tag_id'],
                $validated['action']
            );
        });

        $batch = Bus::batch($jobs)
            ->name('Bulk tag assignment')
            ->then(function (Batch $batch) {
                logger('Batch completed successfully', ['batch_id' => $batch->id]);
            })
            ->catch(function (Batch $batch, Throwable $e) {
                logger('Batch had a failure', ['batch_id' => $batch->id, 'error' => $e->getMessage()]);
            })
            ->finally(function (Batch $batch) {
                logger('Batch finished', [
                    'batch_id' => $batch->id,
                    'total' => $batch->totalJobs,
                    'failed' => $batch->failedJobs,
                ]);
            })
            ->allowFailures()
            ->dispatch();

        return response()->json([
            'batch_id' => $batch->id,
            'total_jobs' => $batch->totaljobs,
        ]);
    }
}

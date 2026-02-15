<?php

namespace App\Models;

use App\Exceptions\InvalidProjectStatusTransitionException;
use App\Jobs\ArchiveProjectResources;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
    ];

    /**
     * The "Booted" method acts as our internal Observer.
     */
    protected static function booted(): void
    {
        static::updating(function (Project $project) {
            // Guard: Enforce State Machine Rules
            if ($project->isDirty('status')) {
                $oldStatus = $project->getOriginal('status');
                $newStatus = $project->status;

                // Rule: Cannot go from 'archived' -> 'active' directly
                if ($oldStatus === 'archived' && $newStatus === 'active') {
                    throw new InvalidProjectStatusTransitionException("Archived projects must go to 'review' before becoming active.");
                }
            }
        });

        static::updated(function (Project $project) {
            // Check: Did status change? AND is it now 'archived'?
            if ($project->wasChanged('status') && $project->status === 'archived') {
                ArchiveProjectResources::dispatch($project);
            }
        });
    }

    /**
     * Get the user that owns the project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tags for the project.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    /**
     * Transition the project to the 'review' state.
     */
    public function markAsReview(): void
    {
        $this->update(['status' => 'review']);
    }

    /**
     * Transition the project to the 'active' state.
     */
    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }
}

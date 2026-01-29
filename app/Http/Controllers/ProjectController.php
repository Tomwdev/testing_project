<?php

namespace App\Http\Controllers;

use App\Jobs\LogActivity;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $projects = Auth::check()
            ? Auth::user()->projects()->with('tags')->latest()->get()
            : collect();

        return view('projects.index', compact('projects'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $tags = Tag::all();

        return view('projects.create', compact('tags'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,completed,archived'],
            'tags' => ['array'],
            'tags.*' => ['exists:tags,id'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $project = $user->projects()->create([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'status' => $attributes['status'],
        ]);

        if (isset($attributes['tags'])) {
            $project->tags()->attach($attributes['tags']);
        }

        LogActivity::dispatch($user, 'created', 'project', $project->id);

        return redirect('/projects')->with('success', 'Project created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        return view('projects.show', compact('project'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project)
    {
        Gate::authorize('update', $project);

        $tags = Tag::all();

        return view('projects.edit', compact('project', 'tags'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        Gate::authorize('update', $project);

        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,completed,archived'],
            'tags' => ['array'],
            'tags.*' => ['exists:tags,id'],
        ]);

        $project->update([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'status' => $attributes['status'],
        ]);

        $project->tags()->sync($attributes['tags'] ?? []);

        /** @var User $user */
        $user = Auth::user();
        LogActivity::dispatch($user, 'updated', 'project', $project->id);

        return redirect('/projects/' . $project->id)->with('success', 'Project updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        Gate::authorize('delete', $project);

        /** @var User $user */
        $user = Auth::user();
        LogActivity::dispatch($user, 'deleted', 'project', $project->id);

        $project->delete();

        return redirect('/projects')->with('success', 'Project deleted successfully.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\LogActivity;
use App\Models\Note;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class NoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $notes = Auth::check()
            ? Auth::user()->notes()->with('tags')->latest()->get()
            : collect();

        return view('notes.index', compact('notes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $tags = Tag::all();
        $projects = Auth::user()
            ->projects()
            ->select('id', 'title')
            ->get();

        return view('notes.create', compact('tags', 'projects'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'tags' => ['array'],
            'tags.*' => ['exists:tags,id'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $note = $user->notes()->create([
            'title' => $attributes['title'],
            'body' => $attributes['body'],
            'project_id' => $attributes['project_id'],
        ]);

        if (isset($attributes['tags'])) {
            $note->tags()->attach($attributes['tags']);
        }

        LogActivity::dispatch($user, 'created', 'note', $note->id);

        return redirect('/notes')->with('success', 'Note created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Note $note)
    {
        return view('notes.show', compact('note'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Note $note)
    {
        Gate::authorize('update', $note);

        $tags = Tag::all();
        $projects = Auth::user()
            ->projects()
            ->select('id', 'title')
            ->get();

        return view('notes.edit', compact('note', 'tags', 'projects'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Note $note)
    {
        Gate::authorize('update', $note);

        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'tags' => ['array'],
            'tags.*' => ['exists:tags,id'],
        ]);

        $note->update([
            'title' => $attributes['title'],
            'body' => $attributes['body'],
            'project_id' => $attributes['project_id'],
        ]);

        $note->tags()->sync($attributes['tags'] ?? []);

        /** @var User $user */
        $user = Auth::user();
        LogActivity::dispatch($user, 'updated', 'note', $note->id);

        return redirect('/notes/' . $note->id)->with('success', 'Note updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Note $note)
    {
        Gate::authorize('delete', $note);

        /** @var User $user */
        $user = Auth::user();
        LogActivity::dispatch($user, 'deleted', 'note', $note->id);

        $note->delete();

        return redirect('/notes')->with('success', 'Note deleted successfully.');
    }
}

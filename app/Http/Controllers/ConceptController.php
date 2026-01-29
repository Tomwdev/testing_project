<?php

namespace App\Http\Controllers;

use App\Jobs\LogActivity;
use App\Models\Concept;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ConceptController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $concepts = Auth::check()
            ? Auth::user()->concepts()->with('tags')->latest()->get()
            : collect();

        return view('concepts.index', compact('concepts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $tags = Tag::all();

        return view('concepts.create', compact('tags'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'tags' => ['array'],
            'tags.*' => ['exists:tags,id'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $concept = $user->concepts()->create([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
        ]);

        if (isset($attributes['tags'])) {
            $concept->tags()->attach($attributes['tags']);
        }

        LogActivity::dispatch($user, 'created', 'concept', $concept->id);

        return redirect('/concepts')->with('success', 'Concept created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Concept $concept)
    {
        return view('concepts.show', compact('concept'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Concept $concept)
    {
        Gate::authorize('update', $concept);

        $tags = Tag::all();

        return view('concepts.edit', compact('concept', 'tags'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Concept $concept)
    {
        Gate::authorize('update', $concept);

        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'tags' => ['array'],
            'tags.*' => ['exists:tags,id'],
        ]);

        $concept->update([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
        ]);

        $concept->tags()->sync($attributes['tags'] ?? []);

        /** @var User $user */
        $user = Auth::user();
        LogActivity::dispatch($user, 'updated', 'concept', $concept->id);

        return redirect('/concepts/' . $concept->id)->with('success', 'Concept updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Concept $concept)
    {
        Gate::authorize('delete', $concept);

        /** @var User $user */
        $user = Auth::user();
        LogActivity::dispatch($user, 'deleted', 'concept', $concept->id);

        $concept->delete();

        return redirect('/concepts')->with('success', 'Concept deleted successfully.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Display a listing of all tags (tech stack).
     */
    public function index()
    {
        $tags = Tag::withCount(['notes', 'projects', 'concepts'])->get();

        return view('tags.index', compact('tags'));
    }

    /**
     * Display content filtered by a specific tag.
     */
    public function show(Tag $tag)
    {
        $tag->load(['notes', 'projects', 'concepts']);

        return view('tags.show', compact('tag'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\Note;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with recent content.
     */
    public function __invoke()
    {
        $recentNotes = collect();
        $recentProjects = collect();
        $recentConcepts = collect();

        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            $recentNotes = $user->notes()->with('tags')->latest()->take(5)->get();
            $recentProjects = $user->projects()->with('tags')->latest()->take(5)->get();
            $recentConcepts = $user->concepts()->with('tags')->latest()->take(5)->get();
        }

        $tags = Tag::all();

        return view('dashboard', compact('recentNotes', 'recentProjects', 'recentConcepts', 'tags'));
    }
}

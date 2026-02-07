<?php

use App\Http\Controllers\ConceptController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BulkTagController;

// Dashboard / Home
Route::get('/', DashboardController::class);

// Notes Routes
Route::get('/notes', [NoteController::class, 'index']);
Route::get('/notes/create', [NoteController::class, 'create'])->middleware('auth');
Route::post('/notes', [NoteController::class, 'store'])->middleware('auth');
Route::get('/notes/{note}', [NoteController::class, 'show']);
Route::get('/notes/{note}/edit', [NoteController::class, 'edit'])->middleware('auth');
Route::put('/notes/{note}', [NoteController::class, 'update'])->middleware('auth');
Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->middleware('auth');

// Projects Routes
Route::get('/projects', [ProjectController::class, 'index']);
Route::get('/projects/create', [ProjectController::class, 'create'])->middleware('auth');
Route::post('/projects', [ProjectController::class, 'store'])->middleware('auth');
Route::get('/projects/{project}', [ProjectController::class, 'show']);
Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->middleware('auth');
Route::put('/projects/{project}', [ProjectController::class, 'update'])->middleware('auth');
Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('auth');

// Concepts Routes
Route::get('/concepts', [ConceptController::class, 'index']);
Route::get('/concepts/create', [ConceptController::class, 'create'])->middleware('auth');
Route::post('/concepts', [ConceptController::class, 'store'])->middleware('auth');
Route::get('/concepts/{concept}', [ConceptController::class, 'show']);
Route::get('/concepts/{concept}/edit', [ConceptController::class, 'edit'])->middleware('auth');
Route::put('/concepts/{concept}', [ConceptController::class, 'update'])->middleware('auth');
Route::delete('/concepts/{concept}', [ConceptController::class, 'destroy'])->middleware('auth');

// Tags / Tech Stack Routes
Route::get('/tags', [TagController::class, 'index']);
Route::get('/tags/{tag:slug}', [TagController::class, 'show']);

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create']);
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);
});

Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth');

// Bulk Tag Assignment Routes (auth temporarily disabled for testing)
Route::middleware('auth')->group(function () {
    Route::post('/bulk-tags', [BulkTagController::class, 'store']);
    Route::get('/bulk-tags/{batchId}', [BulkTagController::class, 'show']);
    Route::delete('/bulk-tags/{batchId}', [BulkTagController::class, 'cancel']);
});

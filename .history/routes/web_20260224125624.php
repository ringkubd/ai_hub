<?php

use App\Http\Controllers\Ai\ProjectsDatabaseAgentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::post('ai/projects/ask', ProjectsDatabaseAgentController::class)
    ->middleware(['auth', 'verified'])
    ->name('ai.projects.ask');

require __DIR__.'/settings.php';

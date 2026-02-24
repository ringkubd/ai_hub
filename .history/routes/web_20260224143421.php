<?php

use App\Http\Controllers\Ai\ProjectsDatabaseAgentController;
use App\Http\Controllers\ApiManagementController;
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

Route::get('ai/projects', function () {
    return Inertia::render('ai/projects');
})->middleware(['auth', 'verified'])->name('ai.projects.page');

Route::post('ai/projects/ask', ProjectsDatabaseAgentController::class)
    ->middleware(['auth', 'verified'])
    ->name('ai.projects.ask');

// API Management Dashboard
Route::get('api-management', [ApiManagementController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('api-management.index');

require __DIR__ . '/settings.php';

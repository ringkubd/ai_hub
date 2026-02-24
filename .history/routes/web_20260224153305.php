<?php

use App\Http\Controllers\Ai\ConversationController;
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

// Conversation Management
Route::middleware(['auth', 'verified'])->prefix('ai/conversations')->group(function () {
    Route::get('/', [ConversationController::class, 'index'])->name('ai.conversations.index');
    Route::get('{conversationId}', [ConversationController::class, 'show'])->name('ai.conversations.show');
    Route::delete('{conversationId}', [ConversationController::class, 'destroy'])->name('ai.conversations.destroy');
});

// API Management Dashboard
Route::get('api-management', [ApiManagementController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('api-management.index');

// web routes for API key CRUD so dashboard can call them without stateless API guard
Route::middleware(['auth', 'verified'])->prefix('api-keys')->group(function () {
    Route::post('/', [\App\Http\Controllers\Api\ApiKeyController::class, 'store']);
    Route::delete('{apiKey}', [\App\Http\Controllers\Api\ApiKeyController::class, 'destroy']);
    Route::post('{apiKey}/regenerate', [\App\Http\Controllers\Api\ApiKeyController::class, 'regenerate']);
});

require __DIR__ . '/settings.php';

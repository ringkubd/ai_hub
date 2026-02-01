<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AiHubController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\OllamaAdminController;
use App\Http\Controllers\ProjectController;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('ollama/chat', function () {
    return Inertia::render('ollama/chat');
})->middleware(['auth', 'verified'])->name('ollama.chat');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('ai/chat', [AiChatController::class, 'index'])->name('ai.chat');
    Route::get('ai/chat/sessions', [AiChatController::class, 'sessions'])->name('ai.chat.sessions');
    Route::get('ai/chat/sessions/{session}/messages', [AiChatController::class, 'messages'])->name('ai.chat.messages');
    Route::post('ai/chat/send', [AiChatController::class, 'send'])->name('ai.chat.send');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('ai')->group(function () {
    Route::get('hub', [AiHubController::class, 'index'])->name('ai.hub');

    Route::post('projects', [ProjectController::class, 'store'])->name('ai.projects.store');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('ai.projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('ai.projects.destroy');

    Route::get('ollama/tags', [OllamaAdminController::class, 'tags'])->name('ai.ollama.tags');
    Route::post('ollama/show', [OllamaAdminController::class, 'show'])->name('ai.ollama.show');
    Route::post('ollama/pull', [OllamaAdminController::class, 'pull'])->name('ai.ollama.pull');
    Route::post('ollama/rm', [OllamaAdminController::class, 'remove'])->name('ai.ollama.remove');

    Route::get('api-keys', [ApiKeyController::class, 'index'])->name('ai.api-keys.index');
    Route::post('api-keys', [ApiKeyController::class, 'store'])->name('ai.api-keys.store');
    Route::post('api-keys/{apiKey}/revoke', [ApiKeyController::class, 'revoke'])->name('ai.api-keys.revoke');
});

require __DIR__.'/settings.php';

<?php

use App\Http\Controllers\Ai\ProjectsDatabaseAgentController;
use App\Http\Controllers\Api\OllamaProxyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('ai/projects/ask', ProjectsDatabaseAgentController::class)
        ->name('api.ai.projects.ask');

    Route::get('ai/projects/ping', function (Request $request) {
        return response()->json([
            'ok' => true,
            'user_id' => $request->user()?->id,
        ]);
    })->name('api.ai.projects.ping');
});

// Ollama Proxy API - Protected with authentication
Route::prefix('ollama')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    // Chat & Generation
    Route::post('chat', [OllamaProxyController::class, 'chat'])->name('api.ollama.chat');
    Route::post('generate', [OllamaProxyController::class, 'generate'])->name('api.ollama.generate');
    
    // Models Management
    Route::get('tags', [OllamaProxyController::class, 'tags'])->name('api.ollama.tags');
    Route::post('show', [OllamaProxyController::class, 'show'])->name('api.ollama.show');
    Route::post('pull', [OllamaProxyController::class, 'pull'])->name('api.ollama.pull');
    Route::post('push', [OllamaProxyController::class, 'push'])->name('api.ollama.push');
    Route::delete('delete', [OllamaProxyController::class, 'delete'])->name('api.ollama.delete');
    Route::post('copy', [OllamaProxyController::class, 'copy'])->name('api.ollama.copy');
    Route::post('create', [OllamaProxyController::class, 'create'])->name('api.ollama.create');
    
    // Embeddings
    Route::post('embeddings', [OllamaProxyController::class, 'embeddings'])->name('api.ollama.embeddings');
});

// Health check - Public endpoint
Route::get('ollama/health', [OllamaProxyController::class, 'health'])->name('api.ollama.health');

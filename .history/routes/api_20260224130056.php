<?php

use App\Http\Controllers\Ai\ProjectsDatabaseAgentController;
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

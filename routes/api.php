<?php

use App\Http\Controllers\OllamaProxyController;
use Illuminate\Support\Facades\Route;

Route::any('/{path}', OllamaProxyController::class)
    ->where('path', '.*')
    ->middleware('ollama.gateway');

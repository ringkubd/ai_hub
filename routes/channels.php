<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('projects-ai.{sessionId}', function ($user, $sessionId) {
    // Users can only listen to their own session
    return $user !== null;
});

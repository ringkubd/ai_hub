<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\SyncProjectToQdrant;
use App\Models\Project;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ai:sync-project {slug}', function (string $slug) {
    $project = Project::where('slug', $slug)->firstOrFail();

    SyncProjectToQdrant::dispatch($project);

    $this->info("Queued Qdrant sync for {$project->name}.");
})->purpose('Queue a Qdrant sync for a project.');

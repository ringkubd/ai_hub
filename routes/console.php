<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\SyncProjectToQdrant;
use App\Models\Project;
use App\Services\ProjectSyncService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ai:sync-project {slug} {--queue}', function (string $slug) {
    $project = Project::where('slug', $slug)->firstOrFail();

    if ($this->option('queue')) {
        SyncProjectToQdrant::dispatch($project);
        $this->info("Queued Qdrant sync for {$project->name}.");
        return;
    }

    app(ProjectSyncService::class)->sync($project);
    $this->info("Completed Qdrant sync for {$project->name}.");
})->purpose('Queue a Qdrant sync for a project.');

Artisan::command('ai:sync-all {--queue}', function () {
    $projects = Project::where('is_active', true)->get();
    foreach ($projects as $project) {
        if ($this->option('queue')) {
            SyncProjectToQdrant::dispatch($project);
        } else {
            app(ProjectSyncService::class)->sync($project);
        }
    }

    $this->info($this->option('queue')
        ? 'Queued Qdrant sync for all active projects.'
        : 'Completed Qdrant sync for all active projects.');
})->purpose('Queue Qdrant sync for all active projects.');

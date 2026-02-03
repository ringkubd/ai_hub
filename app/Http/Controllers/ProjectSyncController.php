<?php

namespace App\Http\Controllers;

use App\Jobs\SyncProjectToQdrant;
use App\Models\Project;
use App\Services\ProjectSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectSyncController
{
    public function sync(Project $project, Request $request): JsonResponse
    {
        $queue = $request->boolean('queue');

        if ($queue) {
            SyncProjectToQdrant::dispatch($project);
        } else {
            app(ProjectSyncService::class)->sync($project);
        }

        return response()->json([
            'ok' => true,
            'queued' => $queue,
            'project_id' => $project->id,
            'last_synced_at' => optional($project->fresh()->last_synced_at)->toISOString(),
        ]);
    }

    public function syncAll(Request $request): JsonResponse
    {
        $queue = $request->boolean('queue');
        $projects = Project::where('is_active', true)->get();

        foreach ($projects as $project) {
            if ($queue) {
                SyncProjectToQdrant::dispatch($project);
            } else {
                app(ProjectSyncService::class)->sync($project);
            }
        }

        return response()->json([
            'ok' => true,
            'queued' => $queue,
            'count' => $projects->count(),
        ]);
    }
}

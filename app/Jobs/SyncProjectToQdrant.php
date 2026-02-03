<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\ProjectSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProjectToQdrant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Project $project)
    {
    }

    public function handle(): void
    {
        app(ProjectSyncService::class)->sync($this->project);
    }
}

<?php

namespace App\Jobs;

use App\Models\Project;
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
        // Placeholder: pull project data, build embeddings, upsert into Qdrant.
        $this->project->forceFill(['last_synced_at' => now()])->save();
    }
}

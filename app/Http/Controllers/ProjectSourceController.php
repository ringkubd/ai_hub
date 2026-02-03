<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectSourceController
{
    public function index(Project $project): JsonResponse
    {
        $sources = $project->sources()
            ->orderBy('name')
            ->get()
            ->map(fn (ProjectSource $source) => [
                'id' => $source->id,
                'project_id' => $source->project_id,
                'name' => $source->name,
                'table' => $source->table,
                'primary_key' => $source->primary_key,
                'fields' => $source->fields ?? [],
                'is_active' => $source->is_active,
                'last_synced_at' => optional($source->last_synced_at)->toISOString(),
                'created_at' => optional($source->created_at)->toISOString(),
            ]);

        return response()->json(['sources' => $sources]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $this->validated($request);

        $source = $project->sources()->create($data);

        return response()->json(['source' => $source->fresh()], 201);
    }

    public function update(Request $request, Project $project, ProjectSource $source): JsonResponse
    {
        abort_unless($source->project_id === $project->id, 404);

        $data = $this->validated($request);
        $source->update($data);

        return response()->json(['source' => $source->fresh()]);
    }

    public function destroy(Project $project, ProjectSource $source): JsonResponse
    {
        abort_unless($source->project_id === $project->id, 404);

        $source->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'table' => ['required', 'string', 'max:255'],
            'primary_key' => ['nullable', 'string', 'max:255'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (empty($data['primary_key'])) {
            $data['primary_key'] = 'id';
        }

        return $data;
    }
}

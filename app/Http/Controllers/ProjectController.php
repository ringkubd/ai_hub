<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectController
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $project = Project::create($data);

        return response()->json([
            'project' => $project,
        ], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $data = $this->validated($request, $project->id);

        $project->update($data);

        return response()->json([
            'project' => $project->fresh(),
        ]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?int $projectId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:projects,slug'.($projectId ? ','.$projectId : '')],
            'description' => ['nullable', 'string'],
            'env_key' => ['nullable', 'string', 'max:80'],
            'qdrant_collection' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'connection' => ['nullable', 'array'],
            'connection.driver' => ['nullable', 'string', 'max:40'],
            'connection.host' => ['nullable', 'string', 'max:255'],
            'connection.port' => ['nullable', 'string', 'max:10'],
            'connection.database' => ['nullable', 'string', 'max:255'],
            'connection.username' => ['nullable', 'string', 'max:255'],
            'connection.password' => ['nullable', 'string', 'max:255'],
        ]);

        if (! isset($data['slug']) || $data['slug'] === '') {
            $data['slug'] = Str::slug($data['name']);
        }

        if (! empty($data['env_key'])) {
            $data['connection'] = null;
        }

        return $data;
    }
}

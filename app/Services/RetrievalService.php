<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class RetrievalService
{
    public function retrieve(Project $project, string $query): array
    {
        $cacheKey = 'aihub:retrieve:'.hash('sha256', $project->id.'|'.$query);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $embedding = app(EmbeddingService::class)->embed($query);
        if ($embedding === []) {
            return [];
        }

        $collection = $project->qdrantCollectionName();
        $topK = (int) config('aihub.retrieval.top_k');
        $response = app(QdrantClient::class)->search($collection, $embedding, $topK);

        if (! $response->successful()) {
            return [];
        }

        $results = collect($response->json('result', []))
            ->map(fn (array $item) => [
                'score' => $item['score'] ?? null,
                'text' => $item['payload']['text'] ?? '',
                'source' => $item['payload']['source'] ?? null,
                'source_table' => $item['payload']['source_table'] ?? null,
                'source_id' => $item['payload']['source_id'] ?? null,
            ])
            ->filter(fn (array $row) => $row['text'] !== '')
            ->values()
            ->all();

        Cache::put($cacheKey, $results, 60);

        return $results;
    }

    public function retrieveAcrossProjects(Collection $projects, string $query): array
    {
        $projects = $projects->filter(fn (Project $project) => $project->is_active)->values();
        if ($projects->isEmpty()) {
            return [];
        }

        $projectKey = $projects->pluck('id')->implode(',');
        $cacheKey = 'aihub:retrieve:all:'.hash('sha256', $projectKey.'|'.$query);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $embedding = app(EmbeddingService::class)->embed($query);
        if ($embedding === []) {
            return [];
        }

        $topK = (int) config('aihub.retrieval.top_k');
        $results = [];

        foreach ($projects as $project) {
            $collection = $project->qdrantCollectionName();
            $response = app(QdrantClient::class)->search($collection, $embedding, $topK);
            if (! $response->successful()) {
                continue;
            }

            foreach ($response->json('result', []) as $item) {
                $results[] = [
                    'score' => $item['score'] ?? null,
                    'text' => $item['payload']['text'] ?? '',
                    'source' => $item['payload']['source'] ?? null,
                    'source_table' => $item['payload']['source_table'] ?? null,
                    'source_id' => $item['payload']['source_id'] ?? null,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'project_slug' => $project->slug,
                ];
            }
        }

        $results = collect($results)
            ->filter(fn (array $row) => $row['text'] !== '')
            ->sortByDesc(fn (array $row) => $row['score'] ?? 0)
            ->take($topK)
            ->values()
            ->all();

        Cache::put($cacheKey, $results, 60);

        return $results;
    }
}

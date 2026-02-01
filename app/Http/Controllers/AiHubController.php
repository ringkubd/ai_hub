<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\OllamaClient;
use Inertia\Inertia;
use Inertia\Response;

class AiHubController
{
    public function index(OllamaClient $client): Response
    {
        $projects = Project::orderBy('name')->get();
        $models = [];
        $ollamaOnline = false;

        $response = $client->tags();
        if ($response->successful()) {
            $models = $response->json('models', []);
            $ollamaOnline = true;
        }

        return Inertia::render('ai/hub', [
            'projects' => $projects,
            'models' => $models,
            'ollamaOnline' => $ollamaOnline,
            'qdrant' => [
                'url' => config('aihub.qdrant.url'),
            ],
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\ApiPackage;
use Illuminate\Database\Seeder;

class ApiPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Perfect for testing and small projects',
                'rate_limit_per_minute' => 30,
                'rate_limit_per_day' => 1000,
                'rate_limit_per_month' => 10000,
                'price' => 0,
                'features' => [
                    'ollama_chat',
                    'ollama_generate',
                    'ollama_embed',
                ],
                'allowed_endpoints' => [
                    'api/ollama/chat',
                    'api/ollama/generate',
                    'api/ollama/embed',
                    'api/ollama/tags',
                    'api/ollama/health',
                ],
                'max_api_keys' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For growing applications and small teams',
                'rate_limit_per_minute' => 60,
                'rate_limit_per_day' => 5000,
                'rate_limit_per_month' => 100000,
                'price' => 29.00,
                'features' => [
                    'ollama_chat',
                    'ollama_generate',
                    'ollama_embed',
                    'model_management',
                    'priority_support',
                ],
                'allowed_endpoints' => [
                    'api/ollama/*',
                ],
                'max_api_keys' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For production applications with high traffic',
                'rate_limit_per_minute' => 120,
                'rate_limit_per_day' => 20000,
                'rate_limit_per_month' => 500000,
                'price' => 99.00,
                'features' => [
                    'ollama_chat',
                    'ollama_generate',
                    'ollama_embed',
                    'model_management',
                    'projects_ai',
                    'priority_support',
                    'custom_models',
                    'webhooks',
                ],
                'allowed_endpoints' => [
                    'api/*',
                ],
                'max_api_keys' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Unlimited access for large organizations',
                'rate_limit_per_minute' => 500,
                'rate_limit_per_day' => null,
                'rate_limit_per_month' => null,
                'price' => 499.00,
                'features' => [
                    'ollama_chat',
                    'ollama_generate',
                    'ollama_embed',
                    'model_management',
                    'projects_ai',
                    'priority_support',
                    'custom_models',
                    'webhooks',
                    'dedicated_support',
                    'sla_guarantee',
                    'custom_deployment',
                ],
                'allowed_endpoints' => [
                    'api/*',
                ],
                'max_api_keys' => 100,
                'is_active' => true,
            ],
        ];

        foreach ($packages as $packageData) {
            ApiPackage::updateOrCreate(
                ['slug' => $packageData['slug']],
                $packageData
            );
        }
    }
}

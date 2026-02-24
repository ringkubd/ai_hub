<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\ApiPackage;
use App\Models\ApiUsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ApiManagementController extends Controller
{
    /**
     * Show the API management dashboard
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get user's API keys with packages
        $apiKeys = ApiKey::where('user_id', $user->id)
            ->with('package')
            ->latest()
            ->get()
            ->map(function ($key) {
                return [
                    'id' => $key->id,
                    'name' => $key->name,
                    'masked_key' => $key->getMaskedKey(),
                    'package' => $key->package?->name,
                    'is_active' => $key->is_active,
                    'usage_count' => $key->usage_count,
                    'last_used_at' => $key->last_used_at?->diffForHumans(),
                    'expires_at' => $key->expires_at?->format('Y-m-d'),
                    'created_at' => $key->created_at->format('Y-m-d'),
                ];
            });

        // Get available packages
        $packages = ApiPackage::where('is_active', true)
            ->get()
            ->map(function ($package) {
                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'slug' => $package->slug,
                    'description' => $package->description,
                    'price' => $package->price,
                    'rate_limit_per_minute' => $package->rate_limit_per_minute,
                    'rate_limit_per_day' => $package->rate_limit_per_day,
                    'rate_limit_per_month' => $package->rate_limit_per_month,
                    'features' => $package->features,
                    'max_api_keys' => $package->max_api_keys,
                ];
            });

        // Usage statistics for the last 30 days
        $usageStats = ApiUsageLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total_requests'),
                DB::raw('AVG(response_time) as avg_response_time'),
                DB::raw('SUM(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 ELSE 0 END) as successful_requests'),
                DB::raw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as failed_requests'),
            ])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Endpoint usage breakdown
        $endpointStats = ApiUsageLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->select('endpoint', DB::raw('COUNT(*) as count'))
            ->groupBy('endpoint')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Recent activity
        $recentActivity = ApiUsageLog::where('user_id', $user->id)
            ->with('apiKey:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'api_key_name' => $log->apiKey?->name,
                    'endpoint' => $log->endpoint,
                    'method' => $log->method,
                    'status_code' => $log->status_code,
                    'response_time' => $log->response_time,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'is_successful' => $log->isSuccessful(),
                ];
            });

        return Inertia::render('api-management/index', [
            'apiKeys' => $apiKeys,
            'packages' => $packages,
            'usageStats' => $usageStats,
            'endpointStats' => $endpointStats,
            'recentActivity' => $recentActivity,
            'summary' => [
                'total_keys' => $apiKeys->count(),
                'active_keys' => $apiKeys->where('is_active', true)->count(),
                'total_requests_30d' => $usageStats->sum('total_requests'),
                'avg_response_time' => round($usageStats->avg('avg_response_time'), 2),
            ],
        ]);
    }
}

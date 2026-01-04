<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes are automatically prefixed with '/api' by Laravel.
| Version-specific routes are organized in separate files.
|
*/

// API Versioning - Current version is v1
Route::prefix('v1')->group(function () {
    include base_path('routes/api/v1.php');
});

// API Version endpoint
Route::get('/version', function (Request $request) {
    return response()->json([
        'api' => 'Training Platform API',
        'version' => '1.0.0',
        'latest_version' => '1.0.0',
        'min_supported_version' => '1.0.0',
        'endpoints' => [
            'current' => url('/api/v1'),
            'documentation' => url('/api/documentation'),
        ],
    ]);
});

// API Health check (no version required)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'services' => [
            'database' => \Illuminate\Support\Facades\DB::connection()->getPdo() ? 'up' : 'down',
            'cache' => \Illuminate\Support\Facades\Cache::getStore() ? 'up' : 'down',
        ],
    ]);
});

<?php

use App\Http\Controllers\Api\IngestController;
use App\Models\Polda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', function () {
    return response()->json([
        'status'    => 'ok',
        'system'    => 'SIMOD Enterprise Intelligence API',
        'version'   => 'v2.0-multi-tenant',
        'framework' => 'Laravel 12',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/v1/poldas', function () {
    return response()->json([
        'data' => Polda::with('polres')->where('is_active', true)->get(),
    ]);
});

// Python Background Scraper Ingestion Endpoints
Route::post('/v1/ingest/article', [IngestController::class, 'ingestArticle']);
Route::post('/v1/ingest/bulk', [IngestController::class, 'bulkIngestArticles']);


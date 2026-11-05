<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Melindungi endpoint API ingest (worker Python) dengan token statis.
 *
 * Token dibaca dari `config('simod.ingest_token')` (env `INGEST_API_TOKEN`) dan dikirim klien
 * melalui header `Authorization: Bearer <token>` atau `X-Ingest-Token`.
 * Bila token belum dikonfigurasi, akses DITOLAK (fail-closed, HTTP 503).
 */
class EnsureIngestToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('simod.ingest_token');

        if ($expected === '') {
            return response()->json([
                'status'  => 'error',
                'message' => 'API ingest belum dikonfigurasi (INGEST_API_TOKEN kosong).',
            ], 503);
        }

        $provided = (string) ($request->bearerToken() ?: $request->header('X-Ingest-Token', ''));

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token ingest tidak valid.',
            ], 401);
        }

        return $next($request);
    }
}

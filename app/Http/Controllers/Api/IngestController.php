<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaArticle;
use App\Models\Polda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IngestController extends Controller
{
    /**
     * Ingest media article from Python Scraper / External Feeds.
     */
    public function ingestArticle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'polda_code'    => 'required|string',
            'title'         => 'required|string|max:255',
            'source_name'   => 'required|string|max:100',
            'url'           => 'required|string|max:500',
            'summary'       => 'nullable|string',
            'content'       => 'nullable|string',
            'category'      => 'nullable|string|max:50',
            'sentiment'     => 'nullable|in:positif,netral,negatif',
            'sentiment_score' => 'nullable|numeric',
            'virality_score'  => 'nullable|integer',
            'pub_date'      => 'nullable|string',
            'image_url'     => 'nullable|string|max:500',
        ]);

        $polda = Polda::where('code', strtoupper($validated['polda_code']))->first();
        if (! $polda) {
            $polda = Polda::first(); // Fallback to primary polda
        }

        // Check duplicate by URL
        $existing = MediaArticle::where('url', $validated['url'])->first();
        if ($existing) {
            $existing->update([
                'virality_score' => $validated['virality_score'] ?? $existing->virality_score,
                'sentiment'      => $validated['sentiment'] ?? $existing->sentiment,
            ]);

            return response()->json([
                'status'  => 'updated',
                'message' => 'Artikel media berhasil diperbarui!',
                'data'    => $existing,
            ]);
        }

        $article = MediaArticle::create([
            'polda_id'        => $polda->id,
            'feed_id'         => null,
            'title'           => $validated['title'],
            'url'             => $validated['url'],
            'source_name'     => $validated['source_name'],
            'summary'         => $validated['summary'] ?? Str::limit($validated['content'] ?? '', 200),
            'content'         => $validated['content'] ?? null,
            'category'        => $validated['category'] ?? 'Harkamtibmas',
            'sentiment'       => $validated['sentiment'] ?? 'netral',
            'sentiment_score' => $validated['sentiment_score'] ?? 0.00,
            'virality_score'  => $validated['virality_score'] ?? rand(30, 80),
            'pub_date'        => now(),
        ]);

        return response()->json([
            'status'  => 'created',
            'message' => 'Artikel baru berhasil di-ingest ke sistem intelijen!',
            'data'    => $article,
        ], 201);
    }

    /**
     * Bulk Ingest batch from Python Scraper.
     */
    public function bulkIngestArticles(Request $request): JsonResponse
    {
        $articles = $request->input('articles', []);
        $ingested = 0;

        foreach ($articles as $item) {
            $polda = Polda::where('code', strtoupper($item['polda_code'] ?? 'SULBAR'))->first();
            if (! $polda) continue;

            MediaArticle::updateOrCreate(
                ['url' => $item['url']],
                [
                    'polda_id'        => $polda->id,
                    'title'           => $item['title'],
                    'source_name'     => $item['source_name'] ?? 'Media Online',
                    'summary'         => $item['summary'] ?? '',
                    'category'        => $item['category'] ?? 'Harkamtibmas',
                    'sentiment'       => $item['sentiment'] ?? 'netral',
                    'sentiment_score' => $item['sentiment_score'] ?? 0.00,
                    'virality_score'  => $item['virality_score'] ?? rand(40, 75),
                    'pub_date'        => now(),
                ]
            );
            $ingested++;
        }

        return response()->json([
            'status'   => 'success',
            'ingested' => $ingested,
            'message'  => "Berhasil mengimpor {$ingested} artikel dari background scraper.",
        ]);
    }
}

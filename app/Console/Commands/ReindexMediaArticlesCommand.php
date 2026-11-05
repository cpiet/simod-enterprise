<?php

namespace App\Console\Commands;

use App\Models\Polda;
use App\Support\NewsSearch;
use App\Support\NewsSentiment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hitung ulang sentimen (aturan konteks Polri) dan sidik judul (`title_hash`) untuk artikel yang sudah tersimpan.
 * Aman dijalankan berulang; hanya menulis baris yang nilainya berubah. Tidak menghapus apa pun.
 */
class ReindexMediaArticlesCommand extends Command
{
    protected $signature = 'media:reindex
        {--polda= : Kode Polda (kosong = semua Polda)}
        {--only= : "sentiment" atau "hash" (kosong = keduanya)}';

    protected $description = 'Hitung ulang sentimen berita (aturan konteks Polri) dan sidik judul untuk penggabungan berita ganda';

    public function handle(): int
    {
        $only = $this->option('only');
        if ($only !== null && ! in_array($only, ['sentiment', 'hash'], true)) {
            $this->error('Opsi --only hanya boleh "sentiment" atau "hash".');

            return self::FAILURE;
        }

        $query = DB::table('media_articles')->orderBy('id');

        if ($code = $this->option('polda')) {
            $polda = Polda::where('code', strtoupper((string) $code))->first();
            if (! $polda) {
                $this->error("Polda dengan kode {$code} tidak ditemukan.");

                return self::FAILURE;
            }
            $query->where('polda_id', $polda->id);
        }

        $doSentiment = $only !== 'hash';
        $doHash = $only !== 'sentiment';
        $seen = 0;
        $changedSentiment = 0;
        $changedHash = 0;
        $after = ['positif' => 0, 'netral' => 0, 'negatif' => 0];

        $query->select(['id', 'title', 'summary', 'sentiment', 'title_hash'])->chunkById(500, function ($rows) use (&$seen, &$changedSentiment, &$changedHash, &$after, $doSentiment, $doHash) {
            foreach ($rows as $row) {
                $seen++;
                $update = [];

                if ($doSentiment) {
                    $new = NewsSentiment::detect($row->title, $row->summary);
                    $after[$new]++;
                    if ($new !== $row->sentiment) {
                        $update['sentiment'] = $new;
                        $changedSentiment++;
                    }
                }

                if ($doHash) {
                    $hash = NewsSearch::titleHash($row->title);
                    if ($hash !== $row->title_hash) {
                        $update['title_hash'] = $hash;
                        $changedHash++;
                    }
                }

                if ($update !== []) {
                    DB::table('media_articles')->where('id', $row->id)->update($update);
                }
            }
        });

        $this->info("Diperiksa: {$seen} artikel.");
        if ($doSentiment) {
            $this->line("Sentimen diperbarui: {$changedSentiment} (sesudah: positif {$after['positif']}, netral {$after['netral']}, negatif {$after['negatif']}).");
        }
        if ($doHash) {
            $this->line("Sidik judul diperbarui: {$changedHash}.");
        }

        return self::SUCCESS;
    }
}

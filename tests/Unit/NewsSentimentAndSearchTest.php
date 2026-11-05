<?php

namespace Tests\Unit;

use App\Support\NewsSearch;
use App\Support\NewsSentiment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NewsSentimentAndSearchTest extends TestCase
{
    public static function headlines(): array
    {
        return [
            'kinerja polisi bukan negatif' => ['Polisi Tangkap Pengedar Narkoba di Mamuju', 'positif'],
            'ungkap kasus' => ['Polres Majene Ungkap Kasus Pencurian Motor', 'positif'],
            'kegiatan sosial' => ['Kapolda Sulbar Gelar Baksos dan Bagikan Sembako', 'positif'],
            'oknum polisi' => ['Oknum Polisi Diduga Peras Warga, Propam Turun Tangan', 'negatif'],
            'jenderal ditangkap' => ['Isu Jenderal Bintang 4 Ditangkap soal Penyadapan, Publik Tunggu Jawaban', 'negatif'],
            'anggota diperiksa' => ['Anggota Polres Diperiksa Terkait Dugaan Penganiayaan Tahanan', 'negatif'],
            'pernyataan reformasi bukan negatif' => ['Kapolri tegaskan reformasi Polri berjalan sesuai rencana', 'netral'],
            'desakan reformasi' => ['Mahasiswa desak reformasi Polri usai kasus penembakan', 'negatif'],
            'kritik terhadap polri' => ['DPR Kritik Polri Soal Penanganan Demo', 'negatif'],
            'bentrok' => ['Bentrok Antarwarga di Polewali, Dua Rumah Dibakar', 'negatif'],
            'kamtibmas seimbang dengan kinerja' => ['Polisi Ungkap Kasus Pembunuhan Mahasiswa', 'netral'],
            'netral biasa' => ['Harga Cabai di Pasar Mamuju Stabil Pekan Ini', 'netral'],
            'aman bukan di dalam pengamanan' => ['Rapat Koordinasi Pengamanan Pemilu Digelar Kemarin', 'netral'],
            'kosong' => ['', 'netral'],
        ];
    }

    #[DataProvider('headlines')]
    public function test_sentiment_follows_police_communication_context(string $title, string $expected): void
    {
        $this->assertSame($expected, NewsSentiment::detect($title));
    }

    public function test_summary_is_used_only_when_title_is_neutral(): void
    {
        $this->assertSame('negatif', NewsSentiment::detect('Situasi Terkini di Lokasi', 'Terjadi bentrok antarkelompok sejak pagi.'));
        $this->assertSame('positif', NewsSentiment::detect('Polisi Tangkap Pelaku Curanmor', 'Terjadi bentrok kecil yang segera reda.'));
    }

    public function test_search_parser_understands_phrases_and_exclusions(): void
    {
        $p = NewsSearch::parse('kapolri "jenderal bintang 4" -hoaks -"berita lama"');

        $this->assertSame(['kapolri', 'jenderal bintang 4'], $p['include']);
        $this->assertSame(['hoaks', 'berita lama'], $p['exclude']);
        $this->assertSame(['include' => [], 'exclude' => []], NewsSearch::parse('   '));
    }

    public function test_search_parser_limits_terms_and_ignores_one_letter_words(): void
    {
        $p = NewsSearch::parse('a b c1 c2 c3 c4 c5 c6 c7 c8 c9');

        $this->assertNotContains('a', $p['include']);
        $this->assertCount(8, $p['include']);
    }

    public function test_like_escapes_wildcards(): void
    {
        $this->assertSame('%100!% asli!_ok%', NewsSearch::like('100% asli_ok'));
    }

    public function test_title_hash_merges_same_story_across_portals(): void
    {
        $a = NewsSearch::titleHash('Kapolri Tegaskan Komitmen Reformasi Polri - Detikcom');
        $b = NewsSearch::titleHash('Kapolri tegaskan komitmen reformasi Polri | CNN Indonesia');
        $c = NewsSearch::titleHash('KAPOLRI TEGASKAN KOMITMEN REFORMASI POLRI!');

        $this->assertNotNull($a);
        $this->assertSame($a, $b);
        $this->assertSame($a, $c);
        $this->assertNull(NewsSearch::titleHash('Pendek'));
        $this->assertNotSame($a, NewsSearch::titleHash('Kapolri Lantik Kapolda Baru di Jakarta Hari Ini'));
    }
}

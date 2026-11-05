<?php

namespace App\Support;

/**
 * Penentu sentimen berita berbasis aturan untuk konteks kehumasan Polri (tanpa AI, tanpa biaya).
 *
 * Prinsip: sentimen dinilai dari sudut pandang CITRA & KEWASPADAAN POLRI, bukan "baik/buruk" suatu peristiwa.
 *  - "Polisi tangkap pengedar narkoba" = kinerja Polri -> positif/netral, BUKAN negatif.
 *  - "Oknum polisi diduga peras warga" atau "Jenderal ditangkap" = isu sensitif untuk Polri -> negatif.
 *  - "Bentrok", "ricuh", "teror" = gangguan kamtibmas serius -> negatif (perlu dipantau).
 *  - Selain itu -> netral.
 *
 * Keluaran selalu salah satu dari: `positif`, `netral`, `negatif` (sesuai kolom `media_articles.sentiment`).
 * Dipakai oleh RssFeedService, GoogleNewsCrawlerService, API ingest, dan perintah `media:reindex`.
 */
class NewsSentiment
{
    /** Kata yang menandakan berita menyangkut institusi/anggota Polri. */
    private const POLICE_CONTEXT = [
        'polri', 'polisi', 'kepolisian', 'polda', 'polres', 'polresta', 'polrestabes', 'polsek', 'kapolri', 'kapolda',
        'kapolres', 'kapolsek', 'brimob', 'densus', 'bareskrim', 'propam', 'divpropam', 'satlantas', 'satreskrim',
        'reskrim', 'bhayangkara', 'jenderal', 'perwira', 'bintara', 'brigadir', 'bripka', 'bripda', 'bharada', 'oknum',
    ];

    /** Isu sensitif bagi citra Polri (dihitung hanya bila ada konteks kepolisian). */
    private const NEGATIVE_POLRI = [
        'oknum', 'pungli', 'peras', 'pemerasan', 'penyalahgunaan wewenang', 'kode etik', 'sidang etik', 'pelanggaran etik',
        'dipecat', 'pdth', 'dicopot', 'salah tangkap', 'kriminalisasi', 'arogan', 'represif', 'kekerasan aparat',
        'kekerasan polisi', 'penyiksaan', 'penyadapan', 'desak reformasi', 'tuntut reformasi', 'kritik polri', 'kritik terhadap polri',
        'copot kapolri', 'desak kapolri', 'mosi tidak percaya', 'tidak profesional', 'dugaan pelanggaran', 'minta maaf',
        'permohonan maaf', 'tembak warga', 'menembak warga', 'ditembak polisi', 'dianiaya polisi', 'dipukul polisi',
        'extrajudicial', 'obstruction of justice', 'cacat prosedur', 'gratifikasi', 'suap',
    ];

    /** Pola: anggota/pejabat Polri yang sendiri ditangkap/diperiksa/dilaporkan (subjek = aparat). */
    private const NEGATIVE_POLRI_REGEX = '/\b(?:polisi|polri|jenderal|perwira|anggota|oknum|kapolres|kapolsek|brigadir|bripka|bripda|aipda|aiptu|kasat\w*)\b[\s\p{P}]+(?:[\p{L}\d\-]+[\s\p{P}]+){0,6}?(?:ditangkap|ditahan|dipecat|dicopot|diperiksa|dilaporkan|terseret|jadi tersangka|ditetapkan tersangka|dijatuhi sanksi)\b/u';

    /** Gangguan kamtibmas serius: tetap dipantau sebagai "Zona Merah" walau tanpa konteks Polri. */
    private const NEGATIVE_KAMTIBMAS = [
        'bentrok', 'ricuh', 'kerusuhan', 'tawuran', 'teror', 'bom', 'ledakan', 'penyerangan', 'anarkis', 'korban jiwa',
        'pembunuhan', 'pembegalan', 'begal', 'penculikan', 'pemerkosaan', 'penjarahan', 'pembakaran', 'tewas',
    ];

    private const POSITIVE = [
        'apresiasi', 'penghargaan', 'prestasi', 'sukses', 'humanis', 'baksos', 'bakti sosial', 'donor darah', 'bantuan',
        'santunan', 'bansos', 'kondusif', 'tertib', 'aman', 'wujudkan', 'kolaborasi', 'inovasi', 'berhasil', 'keberhasilan',
        'peduli', 'sinergi', 'edukasi', 'sosialisasi', 'syukuran', 'hut bhayangkara', 'pelayanan prima', 'lancar',
        'dukungan masyarakat', 'terpercaya', 'transparan', 'pahlawan', 'membantu warga', 'bagikan',
    ];

    /** Pola: aparat sebagai pelaku penindakan (kinerja) -> positif. */
    private const POSITIVE_REGEX = '/\b(?:polisi|polri|polres\w*|polda\w*|polsek\w*|satreskrim|reskrim|satnarkoba|brimob|petugas|tim gabungan|tim)\b[\s\p{P}]+(?:[\p{L}\d\-]+[\s\p{P}]+){0,2}?(?:tangkap|amankan|ungkap|gagalkan|bekuk|sita|selamatkan|evakuasi|bubarkan)\w*/u';

    /** @var array<string, string> cache pola gabungan per daftar kata */
    private static array $compiled = [];

    /**
     * Tentukan sentimen dari judul (prioritas) dan ringkasan.
     * Judul dinilai lebih dulu; ringkasan hanya dipakai bila judul sendiri netral (mengurangi derau).
     */
    public static function detect(?string $title, ?string $summary = null): string
    {
        $title = self::normalize((string) $title);
        $summary = self::normalize(mb_substr(strip_tags((string) $summary), 0, 300));

        if ($title === '' && $summary === '') {
            return 'netral';
        }

        $fromTitle = self::score($title);
        if ($fromTitle !== 'netral') {
            return $fromTitle;
        }

        return $summary === '' ? 'netral' : self::score($title . '. ' . $summary);
    }

    private static function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($text)) ?? '');
    }

    private static function score(string $text): string
    {
        if ($text === '') {
            return 'netral';
        }

        $hasPolice = self::hits(self::POLICE_CONTEXT, $text) > 0;

        $negPolri = 0;
        if ($hasPolice) {
            $negPolri = self::hits(self::NEGATIVE_POLRI, $text) + (preg_match(self::NEGATIVE_POLRI_REGEX, $text) === 1 ? 1 : 0);
        }

        if ($negPolri > 0) {
            return 'negatif';
        }

        $negKamtibmas = self::hits(self::NEGATIVE_KAMTIBMAS, $text);
        $positive = self::hits(self::POSITIVE, $text)
            + ($hasPolice && preg_match(self::POSITIVE_REGEX, $text) === 1 ? 1 : 0);

        if ($negKamtibmas > $positive) {
            return 'negatif';
        }
        if ($positive > $negKamtibmas) {
            return 'positif';
        }

        return 'netral';
    }

    /**
     * Jumlah kata/frasa berbeda dari daftar yang muncul sebagai KATA UTUH (aman" tidak cocok dengan "pengamanan").
     *
     * @param array<int, string> $words
     */
    private static function hits(array $words, string $text): int
    {
        $key = md5(implode('|', $words));
        self::$compiled[$key] ??= '/(?<![\p{L}\d])(?:' . implode('|', array_map(fn ($w) => preg_quote($w, '/'), $words)) . ')(?![\p{L}\d])/u';

        return preg_match_all(self::$compiled[$key], $text, $m) === false ? 0 : count(array_unique($m[0]));
    }
}

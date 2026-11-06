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

    /** Ungkapan warganet (khusus media sosial), tambahan atas aturan berita. */
    private const SOCIAL_POSITIVE = [
        'mantap', 'mantul', 'keren', 'hebat', 'salut', 'bangga', 'terima kasih', 'makasih', 'sigap', 'terpuji',
        'luar biasa', 'gercep', 'respek',
    ];

    private const SOCIAL_NEGATIVE = [
        'kecewa', 'marah', 'kesal', 'muak', 'bohong', 'kasar', 'lambat', 'tidak becus', 'gak becus', 'nggak becus',
        'semena-mena', 'sewenang-wenang', 'brutal', 'zalim', 'payah', 'penipuan', 'hoaks', 'hoax',
    ];

    /** @var array<string, string> cache pola gabungan per daftar kata */
    private static array $compiled = [];

    /**
     * Tentukan sentimen dari judul (prioritas) dan ringkasan.
     * Judul dinilai lebih dulu; ringkasan hanya dipakai bila judul sendiri netral (mengurangi derau).
     */
    public static function detect(?string $title, ?string $summary = null): string
    {
        return self::explain($title, $summary)['sentiment'];
    }

    /**
     * Sentimen BESERTA alasannya (kata/pola yang cocok) untuk ditampilkan ke analis. `detect()` memakai fungsi
     * ini sehingga label dan alasan selalu berasal dari aturan yang sama.
     *
     * @return array{sentiment: string, rule: string, basis: string, police_context: bool, negative: array<int, string>, positive: array<int, string>, reason: string}
     */
    public static function explain(?string $title, ?string $summary = null): array
    {
        $title = self::normalize((string) $title);
        $summary = self::normalize(mb_substr(strip_tags((string) $summary), 0, 300));

        if ($title === '' && $summary === '') {
            return self::result('netral', 'empty', 'judul', false, [], [], 'Tidak ada teks untuk dinilai.');
        }

        $fromTitle = self::evaluate($title, false, 'judul');
        if ($fromTitle['sentiment'] !== 'netral' || $summary === '') {
            return $fromTitle;
        }

        return self::evaluate($title . '. ' . $summary, false, 'judul + ringkasan');
    }

    /** Sentimen unggahan media sosial: aturan berita yang sama + ungkapan warganet (SOCIAL_*). */
    public static function detectSocial(?string $content): string
    {
        return self::explainSocial($content)['sentiment'];
    }

    public static function explainSocial(?string $content): array
    {
        $text = self::normalize(mb_substr(strip_tags((string) $content), 0, 600));
        if ($text === '') {
            return self::result('netral', 'empty', 'isi unggahan', false, [], [], 'Tidak ada teks untuk dinilai.');
        }

        return self::evaluate($text, true, 'isi unggahan');
    }

    private static function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($text)) ?? '');
    }

    /**
     * Urutan aturan: (1) konteks Polri + isu sensitif → negatif; (2) bandingkan jumlah penanda negatif
     * (gangguan kamtibmas, + keluhan warganet untuk medsos) dengan penanda positif; seimbang/tidak ada → netral.
     */
    private static function evaluate(string $text, bool $social, string $basis): array
    {
        $hasPolice = self::matches(self::POLICE_CONTEXT, $text) !== [];

        if ($hasPolice) {
            $negPolri = self::matches(self::NEGATIVE_POLRI, $text);
            if (preg_match(self::NEGATIVE_POLRI_REGEX, $text, $m) === 1) {
                $negPolri[] = trim($m[0]);
            }
            if ($negPolri !== []) {
                return self::result('negatif', 'polri_negative', $basis, true, $negPolri, [],
                    'Menyangkut Polri dan memuat isu sensitif bagi citra Polri: ' . self::quote($negPolri) . '.');
            }
        }

        $negative = self::matches(self::NEGATIVE_KAMTIBMAS, $text);
        $positive = self::matches(self::POSITIVE, $text);
        if ($hasPolice && preg_match(self::POSITIVE_REGEX, $text, $m) === 1) {
            $positive[] = trim($m[0]);
        }
        if ($social) {
            $negative = array_values(array_unique(array_merge($negative, self::matches(self::SOCIAL_NEGATIVE, $text))));
            $positive = array_values(array_unique(array_merge($positive, self::matches(self::SOCIAL_POSITIVE, $text))));
        }

        $n = count($negative);
        $p = count($positive);
        if ($n > $p) {
            return self::result('negatif', 'negative_majority', $basis, $hasPolice, $negative, $positive,
                "Penanda negatif ({$n}: " . self::quote($negative) . ") lebih banyak daripada penanda positif ({$p}" . ($p ? ': ' . self::quote($positive) : '') . ').');
        }
        if ($p > $n) {
            return self::result('positif', 'positive_majority', $basis, $hasPolice, $negative, $positive,
                "Penanda positif ({$p}: " . self::quote($positive) . ") lebih banyak daripada penanda negatif ({$n}" . ($n ? ': ' . self::quote($negative) : '') . ').');
        }
        if ($p > 0) {
            return self::result('netral', 'balanced', $basis, $hasPolice, $negative, $positive,
                "Penanda positif dan negatif sama banyak ({$p}): " . self::quote(array_merge($positive, $negative)) . '.');
        }

        return self::result('netral', 'none', $basis, $hasPolice, [], [], 'Tidak ditemukan kata penanda positif maupun negatif.');
    }

    private static function result(string $sentiment, string $rule, string $basis, bool $police, array $negative, array $positive, string $reason): array
    {
        return [
            'sentiment'      => $sentiment,
            'rule'           => $rule,
            'basis'          => $basis,
            'police_context' => $police,
            'negative'       => array_values($negative),
            'positive'       => array_values($positive),
            'reason'         => $reason,
        ];
    }

    private static function quote(array $words): string
    {
        return implode(', ', array_map(fn ($w) => '"' . $w . '"', array_slice($words, 0, 5)));
    }

    /**
     * Kata/frasa berbeda dari daftar yang muncul sebagai KATA UTUH ("aman" tidak cocok dengan "pengamanan").
     *
     * @param array<int, string> $words
     * @return array<int, string>
     */
    private static function matches(array $words, string $text): array
    {
        $key = md5(implode('|', $words));
        self::$compiled[$key] ??= '/(?<![\p{L}\d])(?:' . implode('|', array_map(fn ($w) => preg_quote($w, '/'), $words)) . ')(?![\p{L}\d])/u';

        return preg_match_all(self::$compiled[$key], $text, $m) ? array_values(array_unique($m[0])) : [];
    }

    /**
     * Daftar kata yang dipakai, untuk popup metodologi (sumber tunggal = konstanta di kelas ini).
     *
     * @return array<string, array<int, string>>
     */
    public static function lexicon(): array
    {
        return [
            'police_context'     => self::POLICE_CONTEXT,
            'negative_polri'     => self::NEGATIVE_POLRI,
            'negative_kamtibmas' => self::NEGATIVE_KAMTIBMAS,
            'positive'           => self::POSITIVE,
            'social_negative'    => self::SOCIAL_NEGATIVE,
            'social_positive'    => self::SOCIAL_POSITIVE,
        ];
    }
}

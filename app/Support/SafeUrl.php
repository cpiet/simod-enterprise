<?php

namespace App\Support;

/**
 * Pelindung SSRF untuk URL yang diberikan pengguna/admin (feed RSS, uji feed): hanya http/https ke alamat PUBLIK.
 * Menolak localhost, jaringan privat (10/8, 172.16/12, 192.168/16), link-local/metadata cloud (169.254/16),
 * dan rentang cadangan lain, baik berupa IP langsung maupun hasil resolusi DNS.
 */
class SafeUrl
{
    /**
     * @param bool $requireResolvable true (bawaan): host harus dapat di-resolve ke alamat publik. false: host yang belum
     *                               dapat di-resolve (mis. DNS sementara gagal) tetap diterima; yang terbukti privat tetap ditolak.
     */
    public static function isPublicHttp(?string $url, bool $requireResolvable = true): bool
    {
        if (! is_string($url) || $url === '' || strlen($url) > 2048) {
            return false;
        }

        $parts = parse_url($url);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = trim($parts['host'], '[]');
        if (strcasecmp($host, 'localhost') === 0 || str_ends_with(strtolower($host), '.localhost') || str_ends_with(strtolower($host), '.internal') || str_ends_with(strtolower($host), '.local')) {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (array) (@gethostbynamel($host) ?: []);
        if ($ips === []) {
            return ! $requireResolvable;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /** Opsi cURL aman: hanya http/https (juga pada redirect), batas redirect, verifikasi TLS di luar lingkungan lokal. */
    public static function curlOptions(): array
    {
        return [
            CURLOPT_PROTOCOLS       => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_MAXREDIRS       => 4,
            CURLOPT_SSL_VERIFYPEER  => ! app()->environment(['local', 'testing']),
            CURLOPT_SSL_VERIFYHOST  => app()->environment(['local', 'testing']) ? 0 : 2,
        ];
    }
}

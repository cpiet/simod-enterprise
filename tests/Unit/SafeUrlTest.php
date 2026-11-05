<?php

namespace Tests\Unit;

use App\Support\SafeUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Pelindung SSRF: hanya http/https ke alamat publik. */
class SafeUrlTest extends TestCase
{
    public static function blocked(): array
    {
        return [
            'localhost' => ['http://localhost/rss'],
            'loopback' => ['http://127.0.0.1/rss'],
            'metadata cloud' => ['http://169.254.169.254/latest/meta-data/'],
            'privat 10/8' => ['https://10.0.0.5/feed'],
            'privat 192.168' => ['http://192.168.1.10/feed'],
            'privat 172.16' => ['http://172.16.0.1/feed'],
            'ipv6 loopback' => ['http://[::1]/feed'],
            'skema file' => ['file:///etc/passwd'],
            'skema ftp' => ['ftp://8.8.8.8/feed'],
            'skema gopher' => ['gopher://8.8.8.8/'],
            'kredensial di URL' => ['http://user:pass@8.8.8.8/feed'],
            'subdomain .local' => ['http://nas.local/feed'],
            'kosong' => [''],
        ];
    }

    #[DataProvider('blocked')]
    public function test_internal_and_non_http_urls_are_rejected(string $url): void
    {
        $this->assertFalse(SafeUrl::isPublicHttp($url));
        $this->assertFalse(SafeUrl::isPublicHttp($url, false));
    }

    public function test_public_ip_is_accepted_and_unresolvable_host_depends_on_mode(): void
    {
        $this->assertTrue(SafeUrl::isPublicHttp('https://8.8.8.8/feed.xml'));
        $this->assertFalse(SafeUrl::isPublicHttp('https://tidak-ada-host.invalid/feed'));
        $this->assertTrue(SafeUrl::isPublicHttp('https://tidak-ada-host.invalid/feed', false));
    }
}

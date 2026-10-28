<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RssFeedService
{
    protected int $cacheTtl = 300; // 5 menit

    /**
     * Ambil feed dari satu sumber.
     */
    public function fetchSourceFeed(array $source, bool $forceRefresh = false): array
    {
        $url = trim((string) ($source['url'] ?? ''));
        if ($url === '') {
            return [];
        }

        $key = 'rss_feed_' . md5($url);
        if (! $forceRefresh) {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $xml = $this->httpGet($url);
        if ($xml === '') {
            return [];
        }

        $items = $this->parseXml($xml, $source);
        if ($items !== []) {
            Cache::put($key, $items, $this->cacheTtl);
        }

        return $items;
    }

    /**
     * Ambil banyak sumber secara paralel menggunakan curl_multi.
     */
    public function fetchAggregatedFeeds(array $sources, bool $forceRefresh = false, ?string $keyword = null, ?string $category = null): array
    {
        $all = [];
        $eligibleSources = [];

        foreach ($sources as $source) {
            if (empty($source['is_active'])) {
                continue;
            }
            if ($category && $category !== 'all' && strcasecmp((string) ($source['category'] ?? ''), $category) !== 0) {
                continue;
            }
            $eligibleSources[] = $source;
        }

        foreach ($this->fetchSourcesParallel($eligibleSources, $forceRefresh) as $items) {
            foreach ($items as $item) {
                $all[] = $item;
            }
        }

        if ($keyword !== null && trim($keyword) !== '') {
            $needle = mb_strtolower(trim($keyword));
            $all = array_values(array_filter($all, static function (array $item) use ($needle): bool {
                return str_contains(mb_strtolower((string) ($item['title'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($item['summary'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($item['source_name'] ?? '')), $needle);
            }));
        }

        usort($all, static fn (array $a, array $b): int => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

        // Lengkapi OG Image untuk 4 berita teratas jika belum punya thumbnail
        if ($forceRefresh) {
            $enriched = 0;
            foreach ($all as &$item) {
                if ($enriched >= 4) {
                    break;
                }
                if (empty($item['image_url']) && ! empty($item['link'])) {
                    $item['image_url'] = $this->fetchOgImage((string) $item['link']);
                    $enriched++;
                }
            }
            unset($item);
        }

        return $all;
    }

    /**
     * Ambil banyak feed bersamaan via curl_multi.
     */
    protected function fetchSourcesParallel(array $sources, bool $forceRefresh): array
    {
        $results = [];
        $pending = [];
        $multi = curl_multi_init();

        foreach ($sources as $source) {
            $url = trim((string) ($source['url'] ?? ''));
            $key = 'rss_feed_' . md5($url);

            if (! $forceRefresh) {
                $cached = Cache::get($key);
                if (is_array($cached)) {
                    $results[] = $cached;
                    continue;
                }
            }

            $parts = parse_url($url);
            $host = strtolower((string) ($parts['host'] ?? ''));
            if (! $parts || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                || $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0', '169.254.169.254'], true)) {
                continue;
            }

            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 4,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT      => 'SIMOD-Enterprise-Bot/2.0',
                CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, application/xml, text/xml, application/atom+xml, text/html;q=0.9, */*;q=0.8'],
            ]);
            curl_multi_add_handle($multi, $handle);
            $pending[(int) $handle] = [$handle, $source, $key];
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 0.2);
            }
        } while ($running);

        foreach ($pending as [$handle, $source, $key]) {
            $body = curl_multi_getcontent($handle);
            $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            if ($code >= 200 && $code < 400 && is_string($body) && $body !== '') {
                $items = $this->parseXml($body, $source);
                if ($items !== []) {
                    Cache::put($key, $items, $this->cacheTtl);
                    $results[] = $items;
                }
            }
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);

        return $results;
    }

    protected function httpGet(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! $parts || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0', '169.254.169.254'], true)) {
            return '';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'SIMOD-Enterprise-Bot/2.0',
            CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, application/xml, text/xml, application/atom+xml, text/html;q=0.9, */*;q=0.8'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code >= 200 && $code < 400 && is_string($body) ? $body : '';
    }

    protected function parseXml(string $content, array $source): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        if ($xml === false) {
            libxml_clear_errors();
            return [];
        }

        $items = [];
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $parsed = $this->parseItem($item, $source, false);
                if ($parsed) {
                    $items[] = $parsed;
                }
            }
        } elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $parsed = $this->parseItem($entry, $source, true);
                if ($parsed) {
                    $items[] = $parsed;
                }
            }
        }
        libxml_clear_errors();

        return $items;
    }

    protected function parseItem(\SimpleXMLElement $item, array $source, bool $atom): ?array
    {
        $title = trim((string) $item->title);
        $link = trim((string) $item->link);

        if ($atom && isset($item->link)) {
            foreach ($item->link as $node) {
                $attrs = $node->attributes();
                if (! isset($attrs['rel']) || (string) $attrs['rel'] === 'alternate') {
                    $link = (string) ($attrs['href'] ?? '');
                    break;
                }
            }
        }

        if ($title === '' || $link === '') {
            return null;
        }

        $raw = $atom ? (string) ($item->summary ?? $item->content ?? '') : (string) ($item->description ?? '');
        $namespaces = $item->getNamespaces(true);
        if (! $atom && isset($namespaces['content'])) {
            $content = $item->children($namespaces['content']);
            $raw = (string) ($content->encoded ?? $raw);
        }

        $date = $atom ? (string) ($item->published ?? $item->updated ?? '') : (string) ($item->pubDate ?? '');
        $timestamp = strtotime($date) ?: time();
        $imageUrl = $this->extractImage($item, $raw, $namespaces);

        // Kategori dinamis
        $category = (string) ($source['category'] ?? 'Regional');
        $lowerTitle = mb_strtolower($title);
        if (str_contains($lowerTitle, 'polri') || str_contains($lowerTitle, 'polisi') || str_contains($lowerTitle, 'kapolda') || str_contains($lowerTitle, 'polres')) {
            $category = 'Harkamtibmas';
        } elseif (str_contains($lowerTitle, 'laka') || str_contains($lowerTitle, 'lalulintas') || str_contains($lowerTitle, 'lalu lintas') || str_contains($lowerTitle, 'razia')) {
            $category = 'Lalu Lintas';
        } elseif (str_contains($lowerTitle, 'korupsi') || str_contains($lowerTitle, 'tangkap') || str_contains($lowerTitle, 'tersangka') || str_contains($lowerTitle, 'pidana')) {
            $category = 'Hukum & Kriminal';
        }

        return [
            'id'             => substr(md5($link), 0, 12),
            'title'          => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'link'           => $link,
            'summary'        => $this->cleanSummary($raw),
            'image_url'      => $imageUrl,
            'pub_date'       => date('d M Y, H.i', $timestamp) . ' WITA',
            'timestamp'      => $timestamp,
            'time_ago'       => $this->timeAgo($timestamp),
            'source_id'      => $source['id'] ?? 0,
            'source_name'    => strtoupper($source['name'] ?? 'MEDIA ONLINE'),
            'category'       => $category,
            'category_badge' => strtoupper($category),
            'sentiment'      => 'netral',
        ];
    }

    protected function extractImage(\SimpleXMLElement $item, string $raw, array $namespaces): ?string
    {
        if (isset($item->enclosure)) {
            $url = (string) ($item->enclosure->attributes()['url'] ?? '');
            if ($url !== '') {
                return $url;
            }
        }
        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);
            foreach (['content', 'thumbnail'] as $tag) {
                if (isset($media->{$tag})) {
                    $url = (string) ($media->{$tag}->attributes()['url'] ?? '');
                    if ($url !== '') {
                        return $url;
                    }
                }
            }
        }

        return preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $raw, $m) && filter_var($m[1], FILTER_VALIDATE_URL) ? $m[1] : null;
    }

    protected function fetchOgImage(string $url): ?string
    {
        $key = 'rss_og_image_' . md5($url);
        $cached = Cache::get($key);
        if ($cached === '__none__') {
            return null;
        }
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $html = $this->httpGet($url);
        $image = null;
        if ($html !== '') {
            if (preg_match('/<meta[^>]+(?:property|name)=["\'](?:og:image|twitter:image)["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $match)
                || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\'](?:og:image|twitter:image)["\']/i', $html, $match)) {
                $candidate = html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (filter_var($candidate, FILTER_VALIDATE_URL)) {
                    $image = $candidate;
                }
            }
        }

        Cache::put($key, $image ?? '__none__', $this->cacheTtl);

        return $image;
    }

    protected function cleanSummary(string $raw): string
    {
        $text = trim(html_entity_decode((string) preg_replace('/\s+/', ' ', strip_tags($raw)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return mb_strlen($text) > 220 ? mb_substr($text, 0, 217) . '...' : $text;
    }

    protected function timeAgo(int $timestamp): string
    {
        $diff = max(0, time() - $timestamp);
        if ($diff < 60) return 'Baru saja';
        if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
        if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';

        return floor($diff / 86400) . ' hari lalu';
    }
}


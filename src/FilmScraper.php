<?php

declare(strict_types=1);

/**
 * fullhdfilmizlesene.life için film listesi (li.film) alanını
 * ayrıştırıp yapılandırılmış diziye çeviren scraper.
 */
final class FilmScraper
{
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    public function __construct(private int $timeout = 15)
    {
    }

    /**
     * Verilen URL'in HTML içeriğini indirir.
     */
    public function fetchHtml(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
            ],
        ]);

        $html = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException("cURL hatası ({$errno}): {$error}");
        }

        if ($html === false || $status >= 400) {
            throw new RuntimeException("Sayfa alınamadı, HTTP durum kodu: {$status}");
        }

        return $html;
    }

    /**
     * ul.list altındaki li.film öğelerini ayrıştırır.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseListing(string $html): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $items = $xpath->query('//ul[contains(concat(" ", normalize-space(@class), " "), " list ")]//li[contains(concat(" ", normalize-space(@class), " "), " film ")]');

        $films = [];
        foreach ($items as $li) {
            $films[] = $this->parseFilmNode($xpath, $li);
        }

        return $films;
    }

    private function parseFilmNode(DOMXPath $xpath, DOMElement $li): array
    {
        $link = $xpath->query('.//a[contains(concat(" ", normalize-space(@class), " "), " tt ")]', $li)->item(0);
        $titleSpan = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " film-title ")]', $li)->item(0);
        $typeSpan = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " trz ")]', $li)->item(0);
        $qualitySpan = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " uhd ") or contains(concat(" ", normalize-space(@class), " "), " hd ")]', $li)->item(0);
        $imdbSpan = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " imdb ")]', $li)->item(0);
        $commentSpan = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " yrm ")]', $li)->item(0);
        $timeNode = $xpath->query('.//time', $li)->item(0);
        $yearSpan = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " film-yil ")]', $li)->item(0);
        $genreSpans = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " ktt ")]', $li);

        $genres = [];
        foreach ($genreSpans as $genreSpan) {
            $genres[] = trim($genreSpan->textContent);
        }

        return [
            'url' => $link?->getAttribute('href'),
            'title' => $titleSpan !== null
                ? trim($titleSpan->textContent)
                : $this->stripIzleSuffix($link?->textContent),
            'year' => $yearSpan !== null ? trim($yearSpan->textContent) : null,
            'genres' => $genres,
            'imdb' => $imdbSpan !== null ? trim($imdbSpan->textContent) : null,
            'audio_type' => $typeSpan?->getAttribute('title'),
            'quality' => $qualitySpan !== null ? trim($qualitySpan->textContent) : null,
            'comments' => $commentSpan !== null ? trim($commentSpan->textContent) : null,
            'added_at' => $timeNode?->getAttribute('datetime'),
            'added_label' => $timeNode !== null ? trim($timeNode->textContent) : null,
            'poster' => $this->parsePoster($xpath, $li),
        ];
    }

    private function parsePoster(DOMXPath $xpath, DOMElement $li): ?array
    {
        $source = $xpath->query('.//picture/source[@type="image/webp"]', $li)->item(0);
        $img = $xpath->query('.//picture/img', $li)->item(0);

        $srcset = $source?->getAttribute('data-srcset') ?: $source?->getAttribute('srcset');
        $poster = [
            'webp' => $srcset !== null && $srcset !== '' ? $this->firstUrlFromSrcset($srcset) : null,
            'fallback' => $img?->getAttribute('data-src') ?: $img?->getAttribute('src'),
        ];

        return ($poster['webp'] || $poster['fallback']) ? $poster : null;
    }

    private function firstUrlFromSrcset(string $srcset): ?string
    {
        $first = trim(explode(',', $srcset)[0]);
        $parts = preg_split('/\s+/', $first);

        return $parts[0] ?? null;
    }

    private function stripIzleSuffix(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        return trim(preg_replace('/\s*izle\s*$/u', '', trim($text)));
    }
}

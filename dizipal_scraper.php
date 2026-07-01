<?php

declare(strict_types=1);

/**
 * dizipal1559.com için basit, tek dosyalık kazıyıcı (scraper).
 * Kullanım (CLI):
 *   php dizipal_scraper.php https://dizipal1559.com/kanal/exxen
 */

function fetchHtml(string $url): string
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
        ],
    ]);
    $html = curl_exec($ch);
    if ($html === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cURL hatası: {$error}");
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 400) {
        throw new RuntimeException("HTTP hata kodu: {$status} ({$url})");
    }
    return $html;
}

function makeXPath(string $html): DOMXPath
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    return new DOMXPath($dom);
}

/**
 * Kanal/kategori sayfasındaki (ör. /kanal/exxen) dizi listesini çeker.
 * @return array<int, array{title: string, url: string}>
 */
function scrapeChannelPage(string $url): array
{
    $xpath = makeXPath(fetchHtml($url));

    $items = [];
    $seen = [];
    foreach ($xpath->query('//div[contains(@class,"new-added-list")]//a[@data-dizipal-pageloader]') as $a) {
        $href = $a->getAttribute('href');
        if ($href === '' || isset($seen[$href])) {
            continue;
        }
        $title = trim($a->getAttribute('title'));
        if ($title === '') {
            $h2 = $xpath->query('.//h2|.//h3', $a);
            $title = $h2->length > 0 ? trim($h2->item(0)->textContent) : '';
        }
        $seen[$href] = true;
        $items[] = ['title' => $title, 'url' => $href];
    }
    return $items;
}

/**
 * Dizi sayfasındaki (ör. /series/survivor-ceza) sezon butonlarını ve
 * o an sayfada yüklü olan (varsayılan sezon) bölüm listesini çeker.
 * Not: Diğer sezonların bölümleri sayfada AJAX ile geldiği için,
 * bu basit sürüm sadece ilk yüklenen sezonu görür.
 * @return array{seasons: array<int, string>, episodes: array<int, array{title: string, info: string, url: string}>}
 */
function scrapeSeriesPage(string $url): array
{
    $xpath = makeXPath(fetchHtml($url));

    $seasons = [];
    foreach ($xpath->query('//button[contains(@class,"allsznbtns")]') as $btn) {
        $seasons[] = trim($btn->textContent);
    }

    $episodes = [];
    foreach ($xpath->query('//a[@data-dizipal-pageloader and contains(@href,"/bolum/")]') as $a) {
        $href = $a->getAttribute('href');
        $h2 = $xpath->query('.//h2', $a);
        $info = $xpath->query('.//div', $a);
        $episodes[] = [
            'title' => $h2->length > 0 ? trim($h2->item(0)->textContent) : '',
            'info' => $info->length > 0 ? trim($info->item(0)->textContent) : '',
            'url' => $href,
        ];
    }

    return ['seasons' => $seasons, 'episodes' => $episodes];
}

/**
 * Bölüm sayfasındaki (ör. /bolum/survivor-ceza-1x1) video iframe linkini çeker.
 */
function scrapeEpisodeIframe(string $url): ?string
{
    $xpath = makeXPath(fetchHtml($url));
    $nodes = $xpath->query('//iframe[@src]');
    if ($nodes->length === 0) {
        return null;
    }
    $src = $nodes->item(0)->getAttribute('src');
    if (str_starts_with($src, '//')) {
        $src = 'https:' . $src;
    }
    return $src;
}

// ---- Örnek kullanım (CLI) ----
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
    $channelUrl = $argv[1] ?? 'https://dizipal1559.com/kanal/exxen';

    echo "Kanal taranıyor: {$channelUrl}\n";
    $diziler = scrapeChannelPage($channelUrl);
    echo json_encode($diziler, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

    if (!empty($diziler)) {
        $ilkDizi = $diziler[0]['url'];
        echo "\nÖrnek dizi taranıyor: {$ilkDizi}\n";
        $dizi = scrapeSeriesPage($ilkDizi);
        echo json_encode($dizi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

        if (!empty($dizi['episodes'])) {
            $ilkBolum = $dizi['episodes'][0]['url'];
            echo "\nÖrnek bölüm iframe linki taranıyor: {$ilkBolum}\n";
            echo 'Iframe: ' . (scrapeEpisodeIframe($ilkBolum) ?? '(bulunamadı)') . "\n";
        }
    }
}

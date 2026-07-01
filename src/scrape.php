<?php

declare(strict_types=1);

require __DIR__ . '/FilmScraper.php';

$url = $argv[1] ?? 'https://www.fullhdfilmizlesene.life/';

$scraper = new FilmScraper();

try {
    $html = $scraper->fetchHtml($url);
    $films = $scraper->parseListing($html);
} catch (Throwable $e) {
    fwrite(STDERR, 'Hata: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo json_encode($films, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;

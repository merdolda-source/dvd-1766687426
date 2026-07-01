<?php

declare(strict_types=1);

require __DIR__ . '/src/FilmScraper.php';

$sourceUrl = 'https://www.fullhdfilmizlesene.life/';

$scraper = new FilmScraper();
$films = [];
$error = null;

try {
    $html = $scraper->fetchHtml($sourceUrl);
    $films = $scraper->parseListing($html);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Film Listesi</title>
    <style>
        body { font-family: Arial, sans-serif; background: #111; color: #eee; margin: 0; padding: 20px; }
        h1 { font-size: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; }
        .card { background: #1c1c1c; border-radius: 6px; overflow: hidden; }
        .card img { width: 100%; display: block; aspect-ratio: 2/3; object-fit: cover; background: #333; }
        .card .body { padding: 8px; }
        .card .title { font-size: 14px; font-weight: bold; color: #fff; text-decoration: none; }
        .card .meta { font-size: 12px; color: #999; margin-top: 4px; display: flex; gap: 6px; flex-wrap: wrap; }
        .badge { background: #333; border-radius: 3px; padding: 1px 5px; }
        .error { color: #f66; }
    </style>
</head>
<body>
<h1>Film Listesi</h1>

<?php if ($error !== null): ?>
    <p class="error">Liste alınamadı: <?= htmlspecialchars($error) ?></p>
<?php elseif (empty($films)): ?>
    <p>Gösterilecek film bulunamadı.</p>
<?php else: ?>
    <div class="grid">
        <?php foreach ($films as $film): ?>
            <div class="card">
                <img
                    src="<?= htmlspecialchars($film['poster']['fallback'] ?? $film['poster']['webp'] ?? '') ?>"
                    alt="<?= htmlspecialchars($film['title'] ?? '') ?>"
                    loading="lazy"
                >
                <div class="body">
                    <a class="title" href="<?= htmlspecialchars($film['url'] ?? '#') ?>" target="_blank" rel="noopener">
                        <?= htmlspecialchars($film['title'] ?? '') ?>
                    </a>
                    <div class="meta">
                        <?php if (!empty($film['year'])): ?><span class="badge"><?= htmlspecialchars($film['year']) ?></span><?php endif; ?>
                        <?php if (!empty($film['imdb'])): ?><span class="badge">IMDB <?= htmlspecialchars($film['imdb']) ?></span><?php endif; ?>
                        <?php if (!empty($film['quality'])): ?><span class="badge"><?= htmlspecialchars($film['quality']) ?></span><?php endif; ?>
                        <?php foreach ($film['genres'] ?? [] as $genre): ?>
                            <span class="badge"><?= htmlspecialchars($genre) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</body>
</html>

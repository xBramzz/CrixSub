<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/id3.php';
header('Content-Type: application/json');

$rows    = $pdo->query("SELECT id, filename, title, artist, cover FROM tracks")->fetchAll(PDO::FETCH_ASSOC);
$updated = 0;
$errors  = [];

foreach ($rows as $t) {
    try {
        $path = __DIR__ . '/../assets/uploads/' . $t['filename'];
        if (!file_exists($path)) { $errors[] = $t['filename'] . ': file missing'; continue; }

        $m     = parseAudioMetadata($path);
        $cover = $t['cover'];
        if (!empty($m['cover']) && !$cover) $cover = saveCover($m['cover'], $m['coverMime'] ?? null);

        $pdo->prepare("UPDATE tracks SET title=?, artist=?, album=?, cover=? WHERE id=?")->execute([
            $m['title']  ?: $t['title'],
            $m['artist'] ?: $t['artist'],
            $m['album'],
            $cover,
            $t['id']
        ]);
        $updated++;
    } catch (Throwable $e) {
        $errors[] = $t['filename'] . ': ' . $e->getMessage();
    }
}

echo json_encode(['success' => true, 'updated' => $updated, 'errors' => $errors]);
?>
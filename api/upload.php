<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json'); }
        echo json_encode(['error' => 'PHP fatal: ' . $e['message'] . ' in ' . basename($e['file']) . ':' . $e['line']]);
    }
});

require_once __DIR__ . '/../config.php';

$hasId3 = false;
$id3Path = __DIR__ . '/../inc/id3.php';
if (file_exists($id3Path)) {
    require_once $id3Path;
    $hasId3 = function_exists('parseAudioMetadata') && function_exists('saveCover');
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['audio'])) {
    die(json_encode(['error' => 'Invalid request']));
}

try {
    $file = $_FILES['audio'];
    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload error code: ' . $file['error']);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp3', 'wav', 'ogg'])) throw new Exception('Only MP3, WAV, OGG allowed');

    $filename  = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
    $uploadDir = __DIR__ . '/../assets/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) throw new Exception('Move to uploads folder failed');

    $title  = ($_POST['title']  ?? '') !== '' ? $_POST['title']  : pathinfo($file['name'], PATHINFO_FILENAME);
    $artist = ($_POST['artist'] ?? '') !== '' ? $_POST['artist'] : 'Unknown Artist';
    $album  = null;
    $cover  = null;
    $warning = null;

    if ($hasId3) {
        $m = parseAudioMetadata($uploadDir . $filename);
        if (!empty($m['title']))  $title  = $m['title'];
        if (!empty($m['artist'])) $artist = $m['artist'];
        $album = $m['album'] ?? null;
        if (!empty($m['cover']))  $cover  = saveCover($m['cover'], $m['coverMime'] ?? null);
    } else {
        $warning = 'inc/id3.php not found - metadata skipped';
    }

    $cols = $pdo->query("SHOW COLUMNS FROM tracks")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('album', $cols) && in_array('cover', $cols)) {
        $pdo->prepare("INSERT INTO tracks (title, artist, album, cover, filename) VALUES (?,?,?,?,?)")
            ->execute([$title, $artist, $album, $cover, $filename]);
    } else {
        $pdo->prepare("INSERT INTO tracks (title, artist, filename) VALUES (?,?,?)")
            ->execute([$title, $artist, $filename]);
        $warning = 'DB migration not run - execute the ALTER TABLE SQL in phpMyAdmin';
    }

    echo json_encode([
        'success' => true,
        'id'      => $pdo->lastInsertId(),
        'title'   => $title,
        'artist'  => $artist,
        'album'   => $album,
        'cover'   => $cover,
        'warning' => $warning
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
<?php
require_once __DIR__ . '/../config.php';  // ✅ CORRECT
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
    echo json_encode($pdo->query("SELECT * FROM playlists ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC));
} 
elseif ($action === 'create') {
    $stmt = $pdo->prepare("INSERT INTO playlists (name) VALUES (?)");
    $stmt->execute([$_POST['name']]);
    echo json_encode(['success'=>true, 'id'=>$pdo->lastInsertId()]);
}
elseif ($action === 'delete') {
    $pdo->prepare("DELETE FROM playlists WHERE id=?")->execute([$_POST['id']]);
    echo json_encode(['success'=>true]);
}
elseif ($action === 'tracks') {
    $stmt = $pdo->prepare("SELECT t.* FROM tracks t JOIN playlist_tracks pt ON t.id=pt.track_id WHERE pt.playlist_id=? ORDER BY pt.position");
    $stmt->execute([$_GET['id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
elseif ($action === 'add_track') {
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(position),0)+1 as next_pos FROM playlist_tracks WHERE playlist_id=?");
    $stmt->execute([$_POST['playlist_id']]);
    $pos = $stmt->fetch()['next_pos'];
    $pdo->prepare("INSERT INTO playlist_tracks (playlist_id,track_id,position) VALUES (?,?,?)")->execute([$_POST['playlist_id'],$_POST['track_id'],$pos]);
    echo json_encode(['success'=>true]);
}
elseif ($action === 'remove_track') {
    $pdo->prepare("DELETE FROM playlist_tracks WHERE playlist_id=? AND track_id=?")->execute([$_POST['playlist_id'],$_POST['track_id']]);
    echo json_encode(['success'=>true]);
}
?>
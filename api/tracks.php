<?php
require_once __DIR__ . '/../config.php';  // ✅ CORRECT

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
    $stmt = $pdo->query("SELECT * FROM tracks ORDER BY uploaded_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} 
elseif ($action === 'delete' && isset($_POST['id'])) {
    $stmt = $pdo->prepare("SELECT filename FROM tracks WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $track = $stmt->fetch();
    
    if ($track && file_exists("../assets/uploads/" . $track['filename'])) {
        unlink("../assets/uploads/" . $track['filename']);
    }
    
    $pdo->prepare("DELETE FROM tracks WHERE id = ?")->execute([$_POST['id']]);
    echo json_encode(['success' => true]);
}
?>
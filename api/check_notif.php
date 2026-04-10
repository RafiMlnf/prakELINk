<?php
require_once __DIR__ . '/../config/auth.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user = currentUser();
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$isInit = isset($_GET['init']) ? (int)$_GET['init'] : 0;

$db = getDB();

if ($isInit) {
    // Just return the maximum ID to initialize JS tracker
    $stmt = $db->prepare("SELECT MAX(id) FROM notifikasi WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $maxId = (int)$stmt->fetchColumn();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'max_id' => $maxId]);
    exit;
}

// Fetch new notifications
$stmt = $db->prepare("SELECT id, tipe, pesan, link, created_at FROM notifikasi WHERE user_id = ? AND id > ? ORDER BY id ASC");
$stmt->execute([$user['id'], $lastId]);
$newNotifs = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $newNotifs
]);

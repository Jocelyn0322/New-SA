<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) { echo json_encode(['success' => false]); exit; }
$me = $_SESSION['user'];

// 確保 notifications 表存在
$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id          SERIAL PRIMARY KEY,
    recipient   VARCHAR(100) NOT NULL,
    actor       VARCHAR(100) NOT NULL,
    type        VARCHAR(50)  DEFAULT 'new_video',
    video_id    INT,
    video_title VARCHAR(255),
    is_read     BOOLEAN      DEFAULT FALSE,
    created_at  TIMESTAMP    DEFAULT NOW()
)");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'count') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient = ? AND is_read = FALSE");
    $stmt->execute([$me]);
    echo json_encode(['count' => (int)$stmt->fetchColumn()]);

} elseif ($action === 'list') {
    $stmt = $pdo->prepare("
        SELECT n.id, n.actor, n.type, n.video_id, n.video_title, n.is_read, n.created_at
        FROM notifications n
        WHERE n.recipient = ?
        ORDER BY n.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$me]);
    $rows = $stmt->fetchAll();
    // mark as read
    $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE recipient = ?")->execute([$me]);
    echo json_encode(['success' => true, 'notifications' => $rows]);

} elseif ($action === 'mark_read') {
    $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE recipient = ?")->execute([$me]);
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['success' => false, 'message' => 'unknown action']);
}

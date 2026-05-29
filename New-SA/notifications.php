<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/db.php';
require_once __DIR__ . '/notify_helper.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) { echo json_encode(['success' => false]); exit; }
$me = $_SESSION['user'];

ensureNotificationsTable($pdo);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'count') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient = ? AND is_read = FALSE");
    $stmt->execute([$me]);
    echo json_encode(['count' => (int)$stmt->fetchColumn()]);

} elseif ($action === 'list') {
    $stmt = $pdo->prepare("
        SELECT n.id, n.actor, n.type, n.message, n.video_id, n.video_title, n.is_read, n.created_at
        FROM notifications n
        WHERE n.recipient = ?
        ORDER BY n.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$me]);
    $rows = $stmt->fetchAll();

    // 標記哪些 video_removed 通知的影片已被申訴
    try {
        $appealedVideoIds = [];
        $vidIds = array_filter(array_column($rows, 'video_id'));
        if ($vidIds) {
            $ph = implode(',', array_fill(0, count($vidIds), '?'));
            $as = $pdo->prepare("SELECT video_id FROM video_appeals WHERE username = ? AND video_id IN ($ph)");
            $as->execute(array_merge([$me], array_values($vidIds)));
            $appealedVideoIds = $as->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Exception $e) { $appealedVideoIds = []; }

    foreach ($rows as &$row) {
        $row['has_appealed'] = ($row['type'] === 'video_removed' && $row['video_id'] && in_array($row['video_id'], $appealedVideoIds));
    }
    unset($row);

    // mark as read
    $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE recipient = ?")->execute([$me]);
    echo json_encode(['success' => true, 'notifications' => $rows]);

} elseif ($action === 'mark_read') {
    $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE recipient = ?")->execute([$me]);
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['success' => false, 'message' => 'unknown action']);
}

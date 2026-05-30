<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../db.php';

$user = $_SESSION['user'] ?? null;

// ?test=1 → 直接模擬存入，測試 DB 寫入是否正常
if (isset($_GET['test']) && $user) {
    try {
        $stmt = $pdo->prepare("
            UPDATE users SET
                skin_type     = :skin_type,
                skin_tone     = :skin_tone,
                skin_concerns = :skin_concerns,
                updated_at    = NOW()
            WHERE username = :username
        ");
        $stmt->execute([
            ':username'      => $user,
            ':skin_type'     => '混油皮',
            ':skin_tone'     => '中二白',
            ':skin_concerns' => '',
        ]);
        echo json_encode(['test_save' => 'SUCCESS', 'user' => $user, 'skin_type' => '混油皮', 'skin_tone' => '中二白'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        echo json_encode(['test_save' => 'FAILED', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit;
}

// 一般診斷
$rows = [];
if ($user) {
    $stmt = $pdo->prepare("SELECT u.id, u.username, up.skin_type, up.skin_tone, up.skin_concerns, up.gender, up.age, up.allergies, up.makeup_finish, up.makeup_style, up.avatar_url, up.updated_at FROM users u LEFT JOIN user_profiles up ON u.username = up.username WHERE u.username = ?");
    $stmt->execute([$user]);
    $rows = $stmt->fetchAll();
}

echo json_encode([
    'session_user' => $user,
    'logged_in'    => (bool)$user,
    'profile_rows' => $rows,
    'hint'         => '加 ?test=1 可直接測試寫入 DB'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

$user = $_SESSION['user'] ?? null;

// ?test=1 → 直接模擬存入，測試 DB 寫入是否正常
if (isset($_GET['test']) && $user) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_profiles (username, skin_type, skin_tone, skin_concerns)
            VALUES (:username, :skin_type, :skin_tone, :skin_concerns)
            ON CONFLICT (username) DO UPDATE SET
                skin_type     = EXCLUDED.skin_type,
                skin_tone     = EXCLUDED.skin_tone,
                skin_concerns = EXCLUDED.skin_concerns,
                updated_at    = NOW()
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
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE username = ?");
    $stmt->execute([$user]);
    $rows = $stmt->fetchAll();
}

// 確認 user_profiles 的唯一約束
$constraints = [];
try {
    $stmt = $pdo->query("SELECT constraint_name, constraint_type FROM information_schema.table_constraints WHERE table_name = 'user_profiles'");
    $constraints = $stmt->fetchAll();
} catch (Exception $e) {
    $constraints = ['error' => $e->getMessage()];
}

echo json_encode([
    'session_user' => $user,
    'logged_in'    => (bool)$user,
    'profile_rows' => $rows,
    'constraints'  => $constraints,
    'hint'         => '加 ?test=1 可直接測試寫入 DB'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

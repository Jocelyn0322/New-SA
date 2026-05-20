<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require 'db.php';

$data         = json_decode(file_get_contents('php://input'), true) ?? [];
$skinType     = trim($data['skinType']     ?? '');
$skinTone     = trim($data['skinTone']     ?? '');
$skinConcerns = trim($data['skinConcerns'] ?? '');
$makeupFinish = trim($data['makeupFinish'] ?? '');
$makeupStyle  = trim($data['makeupStyle']  ?? '');

$loggedIn = isset($_SESSION['user']) && trim((string)$_SESSION['user']) !== '';

if (!$loggedIn) {
    echo json_encode(['success' => false, 'loggedIn' => false]);
    exit;
}

$username = $_SESSION['user'];

try {
    $stmt = $pdo->prepare("
        INSERT INTO user_profiles (username, skin_type, skin_tone, skin_concerns, makeup_finish, makeup_style)
        VALUES (:username, :skin_type, :skin_tone, :skin_concerns, :makeup_finish, :makeup_style)
        ON CONFLICT (username) DO UPDATE SET
            skin_type     = EXCLUDED.skin_type,
            skin_tone     = EXCLUDED.skin_tone,
            skin_concerns = EXCLUDED.skin_concerns,
            makeup_finish = EXCLUDED.makeup_finish,
            makeup_style  = EXCLUDED.makeup_style,
            updated_at    = NOW()
    ");
    $stmt->execute([
        ':username'     => $username,
        ':skin_type'    => $skinType,
        ':skin_tone'    => $skinTone,
        ':skin_concerns'=> $skinConcerns,
        ':makeup_finish'=> $makeupFinish,
        ':makeup_style' => $makeupStyle,
    ]);
    echo json_encode(['success' => true, 'loggedIn' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'loggedIn' => true, 'error' => $e->getMessage()]);
}

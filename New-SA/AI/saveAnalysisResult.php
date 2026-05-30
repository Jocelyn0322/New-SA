<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require __DIR__ . '/../db.php';

$data         = json_decode(file_get_contents('php://input'), true) ?? [];
$skinType     = trim($data['skinType']     ?? '');
$skinTone     = trim($data['skinTone']     ?? '');
$skinConcerns = trim($data['skinConcerns'] ?? '');
$makeupFinish = trim($data['makeupFinish'] ?? '');
$makeupStyle  = trim($data['makeupStyle']  ?? '');
$quizSkinType = trim($data['quizSkinType'] ?? '');
$aiSkinType   = trim($data['aiSkinType']   ?? '');

$loggedIn = isset($_SESSION['user']) && trim((string)$_SESSION['user']) !== '';

if (!$loggedIn) {
    echo json_encode(['success' => false, 'loggedIn' => false]);
    exit;
}

$username = $_SESSION['user'];

try {
    $stmt = $pdo->prepare("
        INSERT INTO user_profiles (username, skin_type, skin_tone, skin_concerns, makeup_finish, makeup_style, updated_at)
        VALUES (:username, :skin_type, :skin_tone, :skin_concerns, :makeup_finish, :makeup_style, NOW())
        ON DUPLICATE KEY UPDATE
            skin_type     = VALUES(skin_type),
            skin_tone     = VALUES(skin_tone),
            skin_concerns = VALUES(skin_concerns),
            makeup_finish = VALUES(makeup_finish),
            makeup_style  = VALUES(makeup_style),
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS analysis_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        skin_type VARCHAR(50),
        skin_tone VARCHAR(100),
        skin_concerns TEXT,
        quiz_skin_type VARCHAR(50),
        ai_skin_type VARCHAR(50),
        analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->prepare("INSERT INTO analysis_history (username, skin_type, skin_tone, skin_concerns, quiz_skin_type, ai_skin_type)
        VALUES (:username, :skin_type, :skin_tone, :skin_concerns, :quiz_skin_type, :ai_skin_type)")
        ->execute([
            ':username'       => $username,
            ':skin_type'      => $skinType,
            ':skin_tone'      => $skinTone,
            ':skin_concerns'  => $skinConcerns,
            ':quiz_skin_type' => $quizSkinType,
            ':ai_skin_type'   => $aiSkinType,
        ]);

    echo json_encode(['success' => true, 'loggedIn' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'loggedIn' => true, 'error' => $e->getMessage()]);
}

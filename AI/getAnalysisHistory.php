<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require __DIR__ . '/../db.php';

if (!isset($_SESSION['user']) || trim((string)$_SESSION['user']) === '') {
    echo json_encode(['success' => false, 'history' => []]);
    exit;
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS analysis_history (
        id SERIAL PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        skin_type VARCHAR(50),
        skin_tone VARCHAR(100),
        skin_concerns TEXT,
        quiz_skin_type VARCHAR(50),
        ai_skin_type VARCHAR(50),
        analyzed_at TIMESTAMP DEFAULT NOW()
    )");
    $stmt = $pdo->prepare(
        "SELECT skin_type, skin_tone, quiz_skin_type, ai_skin_type, analyzed_at
         FROM analysis_history WHERE username = ?
         ORDER BY analyzed_at DESC LIMIT 5"
    );
    $stmt->execute([$_SESSION['user']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'history' => $rows]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'history' => [], 'error' => $e->getMessage()]);
}

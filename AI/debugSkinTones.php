<?php
header('Content-Type: application/json; charset=utf-8');

try {
    // Include database connection
    $dbFile = __DIR__ . '/db.php';
    if (!file_exists($dbFile)) {
        throw new Exception('db.php not found at: ' . $dbFile);
    }
    require_once $dbFile;
    
    // Verify connection
    if (!isset($pdo)) {
        throw new Exception('PDO connection not available');
    }
    
    // Get all tone names from SkinTones table
    $stmt = $pdo->query("SELECT DISTINCT toneName FROM SkinTones ORDER BY toneName ASC");
    $tones = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tones)) {
        throw new Exception('No tones found in SkinTones table');
    }
    
    // Group by keywords for analysis
    $grouped = [
        '粉' => [],
        '黃' => [],
        '冷' => [],
        '暖' => [],
        '中性' => [],
        '橄欖' => [],
        '偏紅' => [],
        '其他' => []
    ];
    
    foreach ($tones as $tone) {
        $found = false;
        foreach ($grouped as $key => &$group) {
            if (strpos($tone, $key) !== false) {
                $group[] = $tone;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $grouped['其他'][] = $tone;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'total_count' => count($tones),
        'all_tones' => $tones,
        'grouped' => $grouped
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>

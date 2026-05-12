<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

function normalize_text(string $value): string
{
    return mb_strtolower(trim($value), 'UTF-8');
}

function contains_any(string $text, array $keywords): bool
{
    foreach ($keywords as $keyword) {
        if ($keyword !== '' && mb_strpos($text, $keyword, 0, 'UTF-8') !== false) {
            return true;
        }
    }
    return false;
}

try {
    $skinType = trim((string)($_GET['skinType'] ?? ''));
    $sensitive = filter_var($_GET['sensitive'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $limit = (int)($_GET['limit'] ?? 6);
    if ($limit <= 0 || $limit > 12) {
        $limit = 6;
    }

    $skinTypeKeywords = [
        '乾性皮' => ['保濕', '潤澤', '水光', '裸光', '養膚', '滋潤', '霜', '精華', '乳霜'],
        '混乾皮' => ['保濕', '潤澤', '服貼', '裸光', '輕薄', '精華'],
        '油性皮' => ['控油', '持妝', '霧面', '柔霧', '無油', '抗汗', '長效'],
        '混油皮' => ['控油', '持妝', '霧面', '柔霧', '平衡', '長效', '輕薄'],
        '中性皮' => ['自然', '平衡', '輕薄', '通用', '裸妝', '日常'],
        '敏感肌' => ['敏感', '溫和', '舒敏', '無香料', '無酒精', '低刺激', '修護']
    ];

    $sensitiveBoostKeywords = ['無香料', '無酒精', '溫和', '舒敏', '低刺激', '敏感肌', '保濕', '養膚', '修護'];

    $stmt = $pdo->query(
        'SELECT p.*, GROUP_CONCAT(pc.color_name ORDER BY pc.color_id SEPARATOR ",") AS color_names,
                GROUP_CONCAT(pc.color_hex ORDER BY pc.color_id SEPARATOR ",") AS color_hexes
         FROM products p
         LEFT JOIN product_colors pc ON p.p_id = pc.p_id
         WHERE p.category = "底妝"
         GROUP BY p.p_id
         ORDER BY p.created_at DESC'
    );

    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $keywords = $skinTypeKeywords[$skinType] ?? $skinTypeKeywords['中性皮'];

    $ranked = [];
    foreach ($rows as $row) {
        $searchText = normalize_text(implode(' ', [
            (string)($row['brand'] ?? ''),
            (string)($row['category'] ?? ''),
            (string)($row['name'] ?? ''),
            (string)($row['purpose'] ?? ''),
            (string)($row['ingredients'] ?? ''),
            (string)($row['precautions'] ?? '')
        ]));

        $score = 5.0; // base weight for actual DB products
        $reasons = [];

        foreach ($keywords as $keyword) {
            if ($keyword !== '' && mb_strpos($searchText, normalize_text($keyword), 0, 'UTF-8') !== false) {
                $score += 2.0;
                $reasons[] = $keyword;
            }
        }

        if ($sensitive && contains_any($searchText, $sensitiveBoostKeywords)) {
            $score += 1.5;
            $reasons[] = '敏感肌友善';
        }

        if (contains_any($searchText, ['底妝', '粉底', '遮瑕'])) {
            $score += 1.0;
        }

        $colorNames = array_values(array_filter(array_map('trim', explode(',', (string)($row['color_names'] ?? '')))));
        $colorHexes = array_values(array_filter(array_map('trim', explode(',', (string)($row['color_hexes'] ?? '')))));

        $ranked[] = [
            'id' => (int)$row['p_id'],
            'brand' => $row['brand'] ?? '通用',
            'productName' => $row['name'] ?? '推薦產品',
            'name' => trim(($row['brand'] ?? '通用') . '｜' . ($row['name'] ?? '推薦產品')),
            'category' => $row['category'] ?? '',
            'purpose' => $row['purpose'] ?? '',
            'description' => $row['purpose'] ?? '',
            'recommendedShade' => $colorNames[0] ?? '',
            'recommendedShadeHex' => $colorHexes[0] ?? '',
            'matchScore' => round($score, 1),
            'matchReasons' => $reasons,
        ];
    }

    usort($ranked, function (array $left, array $right): int {
        if ($left['matchScore'] === $right['matchScore']) {
            return $left['id'] <=> $right['id'];
        }
        return $right['matchScore'] <=> $left['matchScore'];
    });

    $ranked = array_slice($ranked, 0, $limit);

    echo json_encode([
        'status' => 'success',
        'skinType' => $skinType,
        'sensitive' => $sensitive,
        'products' => $ranked,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
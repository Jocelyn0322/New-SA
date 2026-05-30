<?php
/**
 * 共用推薦邏輯：依膚質 / 妝感 / 是否敏感，對「底妝（與乾敏膚的護膚）」產品評分排序。
 * 同時供 AI/getRecommendedProducts.php（AI 檢測）與 產品/products.php（推薦小框）使用，
 * 確保兩邊推薦結果一致。
 */

if (!function_exists('rec_normalize_text')) {
    function rec_normalize_text(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }
}

if (!function_exists('rec_contains_any')) {
    function rec_contains_any(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && mb_strpos($text, $keyword, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }
}

/**
 * 回傳排名後的推薦產品（每筆含 id、matchScore、matchReasons 等）。
 *
 * @return array<int,array<string,mixed>>
 */
function getRecommendedProductRanking(PDO $pdo, string $skinType, string $makeupFinish = '', bool $sensitive = false, int $limit = 6): array
{
    $skinType     = trim($skinType);
    $makeupFinish = trim($makeupFinish);
    if ($limit <= 0 || $limit > 12) $limit = 6;

    $skinTypeKeywords = [
        '乾性皮' => ['保濕', '潤澤', '水光', '裸光', '養膚', '滋潤', '霜', '精華', '乳霜'],
        '混乾皮' => ['保濕', '潤澤', '服貼', '裸光', '輕薄', '精華'],
        '油性皮' => ['控油', '持妝', '霧面', '柔霧', '無油', '抗汗', '長效'],
        '混油皮' => ['控油', '持妝', '霧面', '柔霧', '平衡', '長效', '輕薄'],
        '中性皮' => ['自然', '平衡', '輕薄', '通用', '裸妝', '日常'],
        '敏感肌' => ['敏感', '溫和', '舒敏', '無香料', '無酒精', '低刺激', '修護']
    ];

    $finishKeywords = [
        '霧面'    => ['霧面', '控油', '柔霧', '無油', '持妝'],
        '水光'    => ['水光', '保濕', '潤澤', '光澤', '水感'],
        '自然光感' => ['自然', '裸妝', '輕薄', '日常'],
        '緞面'    => ['緞面', '光感', '光澤', '亮澤'],
    ];

    $dryTypes  = ['乾性皮', '混乾皮'];
    $oilyTypes = ['油性皮', '混油皮'];
    $rawFkw    = $finishKeywords[$makeupFinish] ?? [];

    if (in_array($skinType, $dryTypes) && $makeupFinish === '霧面') {
        $oilConflictKw = ['控油', '無油', '持妝', '抗汗', '長效'];
        $fkw = array_merge(
            array_filter($rawFkw, fn($k) => !in_array($k, $oilConflictKw)),
            ['保濕', '潤澤']
        );
    } elseif (in_array($skinType, $oilyTypes) && $makeupFinish === '水光') {
        $fkw = $rawFkw;
    } else {
        $fkw = $rawFkw;
    }

    $sensitiveBoostKeywords = ['無香料', '無酒精', '溫和', '舒敏', '低刺激', '敏感肌', '保濕', '養膚', '修護'];

    // 乾性皮、混乾皮、敏感肌額外納入護膚類別
    $dryTypesForCat = ['乾性皮', '混乾皮', '敏感肌'];
    $categories = in_array($skinType, $dryTypesForCat) ? "('底妝', '護膚')" : "('底妝')";

    $stmt = $pdo->query(
        "SELECT d.*, GROUP_CONCAT(pc.color_name ORDER BY pc.color_id SEPARATOR ',') AS color_names,
                GROUP_CONCAT(pc.color_hex ORDER BY pc.color_id SEPARATOR ',') AS color_hexes
         FROM data d
         LEFT JOIN product_colors pc ON d.id = pc.p_id
         WHERE d.category IN $categories
         GROUP BY d.id
         ORDER BY d.created_at DESC"
    );

    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $keywords = $skinTypeKeywords[$skinType] ?? $skinTypeKeywords['中性皮'];

    $ranked = [];
    foreach ($rows as $row) {
        $searchText = rec_normalize_text(implode(' ', [
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
            if ($keyword !== '' && mb_strpos($searchText, rec_normalize_text($keyword), 0, 'UTF-8') !== false) {
                $score += 2.0;
                $reasons[] = $keyword;
            }
        }

        foreach ($fkw as $kw) {
            if ($kw !== '' && mb_strpos($searchText, rec_normalize_text($kw), 0, 'UTF-8') !== false) {
                $score += 1.5;
            }
        }

        if ($sensitive && rec_contains_any($searchText, $sensitiveBoostKeywords)) {
            $score += 1.5;
            $reasons[] = '敏感肌友善';
        }

        if (rec_contains_any($searchText, ['底妝', '粉底', '遮瑕'])) {
            $score += 1.0;
        }

        $colorNames = array_values(array_filter(array_map('trim', explode(',', (string)($row['color_names'] ?? '')))));
        $colorHexes = array_values(array_filter(array_map('trim', explode(',', (string)($row['color_hexes'] ?? '')))));

        $ranked[] = [
            'id' => (int)$row['id'],
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

    return array_slice($ranked, 0, $limit);
}

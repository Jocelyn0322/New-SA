<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

function fetchTableNames(PDO $pdo, string $pattern): array
{
    $quotedPattern = $pdo->quote($pattern);
    $stmt = $pdo->query("SHOW TABLES LIKE {$quotedPattern}");
    return $stmt ? array_map('current', $stmt->fetchAll(PDO::FETCH_NUM)) : [];
}

function findSkinToneTable(PDO $pdo): ?string
{
    // First try tables with 'tone' in name
    $toneTables = fetchTableNames($pdo, '%tone%');
    if (!empty($toneTables)) {
        return $toneTables[0];
    }

    // Then try tables with 'skin' in name
    $skinTables = fetchTableNames($pdo, '%skin%');
    if (!empty($skinTables)) {
        // Filter out tables that are not skin tone tables
        foreach ($skinTables as $table) {
            if (stripos($table, 'type') === false) { // Avoid SkinTypes
                return $table;
            }
        }
    }

    // Fallback to first table
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
    return $tables[0][0] ?? null;
}

function getColumnNames(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    return array_map(fn($row) => $row['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function parseJsonValue($value)
{
    if ($value === null) {
        return null;
    }
    if (is_array($value)) {
        return $value;
    }
    $decoded = json_decode($value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
}

try {
    $table = findSkinToneTable($pdo);
    if (!$table) {
        throw new RuntimeException('找不到膚色資料表，請確認資料庫是否已正確建立');
    }

    $columns = getColumnNames($pdo, $table);

    $fields = [];
    $toneField = null;
    foreach (['toneName', 'tone_name', 'name', 'tone', 'ToneName'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $toneField = $candidate;
            break;
        }
    }
    if (!$toneField) {
        throw new RuntimeException('資料表缺少膚色名稱欄位');
    }
    $fields[] = "`{$toneField}` AS `toneName`";

    if (in_array('hex', $columns, true) || in_array('HexValue', $columns, true)) {
        $fields[] = in_array('hex', $columns, true) ? '`hex`' : '`HexValue` AS `hex`';
    }
    if (in_array('category', $columns, true) || in_array('ToneCategory', $columns, true)) {
        $fields[] = in_array('category', $columns, true) ? '`category`' : '`ToneCategory` AS `category`';
    }
    if (in_array('rgb', $columns, true)) {
        $fields[] = '`rgb`';
    }
    if (in_array('lab', $columns, true)) {
        $fields[] = '`lab`';
    }
    if (in_array('LAB_L', $columns, true) && in_array('LAB_a', $columns, true) && in_array('LAB_b', $columns, true)) {
        $fields[] = '`LAB_L`';
        $fields[] = '`LAB_a`';
        $fields[] = '`LAB_b`';
    }
    if (in_array('r', $columns, true) && in_array('g', $columns, true) && in_array('b', $columns, true)) {
        $fields[] = '`r`';
        $fields[] = '`g`';
        $fields[] = '`b`';
    }

    if (count($fields) === 1) {
        throw new RuntimeException('資料表沒有可讀取的膚色資料欄位');
    }

    $sql = sprintf('SELECT %s FROM `%s` ORDER BY `%s`', implode(', ', $fields), $table, $toneField);
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = array_map(function ($row) {
        $tone = [
            'toneName' => $row['toneName'],
        ];

        if (isset($row['hex'])) {
            $tone['hex'] = $row['hex'];
        }
        if (isset($row['category'])) {
            $tone['category'] = $row['category'];
        }

        if (isset($row['rgb'])) {
            $rgb = parseJsonValue($row['rgb']);
            if (is_array($rgb) && isset($rgb['r'], $rgb['g'], $rgb['b'])) {
                $tone['rgb'] = [
                    'r' => (int)$rgb['r'],
                    'g' => (int)$rgb['g'],
                    'b' => (int)$rgb['b'],
                ];
            }
        }

        if ((!isset($tone['rgb']) || empty($tone['rgb'])) && isset($row['r'], $row['g'], $row['b'])) {
            $tone['rgb'] = [
                'r' => (int)$row['r'],
                'g' => (int)$row['g'],
                'b' => (int)$row['b'],
            ];
        }

        if (isset($row['lab'])) {
            $lab = parseJsonValue($row['lab']);
            if (is_array($lab) && isset($lab['L'], $lab['a'], $lab['b'])) {
                $tone['lab'] = [
                    'L' => (float)$lab['L'],
                    'a' => (float)$lab['a'],
                    'b' => (float)$lab['b'],
                ];
            }
        }
        if (!isset($tone['lab']) && isset($row['LAB_L'], $row['LAB_a'], $row['LAB_b'])) {
            $tone['lab'] = [
                'L' => (float)$row['LAB_L'],
                'a' => (float)$row['LAB_a'],
                'b' => (float)$row['LAB_b'],
            ];
        }

        return $tone;
    }, $rows);

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

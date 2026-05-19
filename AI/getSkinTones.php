<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

function fetchTableNames(PDO $pdo, string $pattern): array
{
    $stmt = $pdo->prepare("
        SELECT table_name FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name ILIKE ?
        ORDER BY table_name
    ");
    $stmt->execute([$pattern]);
    return array_map('current', $stmt->fetchAll(PDO::FETCH_NUM));
}

function findSkinToneTable(PDO $pdo): ?string
{
    $toneTables = fetchTableNames($pdo, '%tone%');
    if (!empty($toneTables)) return $toneTables[0];

    $skinTables = fetchTableNames($pdo, '%skin%');
    foreach ($skinTables as $table) {
        if (stripos($table, 'type') === false) return $table;
    }

    $stmt = $pdo->query("
        SELECT table_name FROM information_schema.tables
        WHERE table_schema = 'public' ORDER BY table_name LIMIT 1
    ");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    return $row[0] ?? null;
}

function getColumnNames(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare("
        SELECT column_name FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = ?
        ORDER BY ordinal_position
    ");
    $stmt->execute([$table]);
    return array_map('current', $stmt->fetchAll(PDO::FETCH_NUM));
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

    // PostgreSQL 欄位名全部小寫，做 case-insensitive 比對
    $columnsLower = array_map('strtolower', $columns);

    $fields = [];
    $toneField = null;
    foreach (['tonename', 'tone_name', 'name', 'tone'] as $candidate) {
        if (in_array($candidate, $columnsLower, true)) {
            $toneField = $columns[array_search($candidate, $columnsLower)];
            break;
        }
    }
    if (!$toneField) {
        throw new RuntimeException('資料表缺少膚色名稱欄位');
    }
    $fields[] = "{$toneField} AS \"toneName\"";

    if (in_array('hexvalue', $columnsLower, true)) {
        $col = $columns[array_search('hexvalue', $columnsLower)];
        $fields[] = "{$col} AS hex";
    } elseif (in_array('hex', $columnsLower, true)) {
        $fields[] = 'hex';
    }
    if (in_array('tonecategory', $columnsLower, true)) {
        $col = $columns[array_search('tonecategory', $columnsLower)];
        $fields[] = "{$col} AS category";
    } elseif (in_array('category', $columnsLower, true)) {
        $fields[] = 'category';
    }
    foreach (['rgb', 'lab'] as $f) {
        if (in_array($f, $columnsLower, true)) $fields[] = $f;
    }
    foreach (['lab_l', 'lab_a', 'lab_b'] as $f) {
        if (in_array($f, $columnsLower, true)) {
            $col = $columns[array_search($f, $columnsLower)];
            $fields[] = $col;
        }
    }
    if (in_array('r', $columnsLower) && in_array('g', $columnsLower) && in_array('b', $columnsLower)) {
        $fields[] = 'r'; $fields[] = 'g'; $fields[] = 'b';
    }

    if (count($fields) === 1) {
        throw new RuntimeException('資料表沒有可讀取的膚色資料欄位');
    }

    $sql = sprintf('SELECT %s FROM %s ORDER BY %s', implode(', ', $fields), $table, $toneField);
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = array_map(function ($row) {
        $tone = [
            'toneName' => $row['toneName'],
        ];

        if (isset($row['hex'])) {
            $val = $row['hex'];
            // 若 DB 改為十進位整數儲存，自動轉成 #RRGGBB；現為 hex 字串則直接使用
            if (is_numeric($val) && !str_starts_with((string)$val, '#')) {
                $tone['hex'] = '#' . strtoupper(str_pad(dechex((int)$val), 6, '0', STR_PAD_LEFT));
            } else {
                $tone['hex'] = $val;
            }
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

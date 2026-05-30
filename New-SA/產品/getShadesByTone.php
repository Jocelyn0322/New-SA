<?php
require_once __DIR__ . '/../auth_check.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$skinTone = $_GET['skin_tone'] ?? '';
$pidsRaw  = $_GET['pids']      ?? '';

if (!$skinTone || !$pidsRaw) {
    echo json_encode([]);
    exit;
}

$pids = array_values(array_filter(array_map('intval', explode(',', $pidsRaw))));
if (empty($pids)) {
    echo json_encode([]);
    exit;
}

function hexColorDistance(string $hex1, string $hex2): float {
    $parse = fn($h) => [
        hexdec(substr(ltrim($h, '#'), 0, 2)),
        hexdec(substr(ltrim($h, '#'), 2, 2)),
        hexdec(substr(ltrim($h, '#'), 4, 2)),
    ];
    [$r1,$g1,$b1] = $parse($hex1);
    [$r2,$g2,$b2] = $parse($hex2);
    return sqrt(($r1-$r2)**2 + ($g1-$g2)**2 + ($b1-$b2)**2);
}

try {
    // 查膚色 hex
    $ts = $pdo->prepare("SELECT HexValue FROM skintones WHERE ToneName = ? LIMIT 1");
    $ts->execute([$skinTone]);
    $toneHex = $ts->fetchColumn() ?: '';

    if (!$toneHex) {
        echo json_encode([]);
        exit;
    }

    // 查色號
    $ph = implode(',', array_fill(0, count($pids), '?'));
    $cs = $pdo->prepare("SELECT p_id, color_name, color_hex FROM product_colors WHERE p_id IN ($ph) ORDER BY p_id, color_id");
    $cs->execute($pids);

    $colorsByProduct = [];
    foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $colorsByProduct[$c['p_id']][] = $c;
    }

    $result = [];
    foreach ($pids as $pid) {
        $shades = $colorsByProduct[$pid] ?? [];
        if (empty($shades)) {
            $result[$pid] = null;
            continue;
        }
        $best = $shades[0]; $bestDist = PHP_FLOAT_MAX;
        foreach ($shades as $shade) {
            $d = hexColorDistance($toneHex, $shade['color_hex']);
            if ($d < $bestDist) { $bestDist = $d; $best = $shade; }
        }
        $result[$pid] = ['color_name' => $best['color_name'], 'color_hex' => $best['color_hex']];
    }

    echo json_encode(['toneHex' => $toneHex, 'shades' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

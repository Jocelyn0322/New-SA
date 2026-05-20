<?php
require 'db.php';

$imgDir = __DIR__ . '/images/';

// Category gradient colors
$catColors = [
    '底妝'   => ['#f9d4e0', '#e8a0b4'],
    '口紅'   => ['#f5c0cc', '#d9607a'],
    '唇釉'   => ['#f5c0cc', '#c94f6a'],
    '眼影'   => ['#d4c5e8', '#9b7fc2'],
    '睫毛膏' => ['#c8d8f0', '#6a90c2'],
    '腮紅'   => ['#fad4c0', '#e8906a'],
    '打亮'   => ['#faeec0', '#d4b86a'],
    '修容'   => ['#e8d4c0', '#b8906a'],
    '遮瑕'   => ['#f0e0d0', '#c8a080'],
    '眼線'   => ['#c8d4e8', '#6a80b8'],
    '護膚'   => ['#c8e8d4', '#6ab890'],
    '防曬'   => ['#f0f0c8', '#b8b86a'],
    '精華液' => ['#d4e8f0', '#6aacc8'],
];

$products = $pdo->query("
    SELECT d.id, d.brand, d.name, d.category,
           MIN(pc.color_hex) as first_color
    FROM public.data d
    LEFT JOIN product_colors pc ON d.id = pc.p_id
    WHERE d.id > 201
    GROUP BY d.id, d.brand, d.name, d.category
    ORDER BY d.id
")->fetchAll();

$generated = 0;
foreach ($products as $p) {
    $path = $imgDir . $p['id'] . '.jpg';
    if (file_exists($path)) continue;

    $cat = $p['category'];
    [$c1, $c2] = $catColors[$cat] ?? ['#f0d4e0', '#c8849a'];

    $w = 600; $h = 600;
    $img = imagecreatetruecolor($w, $h);

    // Parse hex colors
    $hex1 = ltrim($c1, '#');
    $hex2 = ltrim($c2, '#');
    $r1 = hexdec(substr($hex1,0,2)); $g1 = hexdec(substr($hex1,2,2)); $b1 = hexdec(substr($hex1,4,2));
    $r2 = hexdec(substr($hex2,0,2)); $g2 = hexdec(substr($hex2,2,2)); $b2 = hexdec(substr($hex2,4,2));

    // Draw gradient background
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $r = (int)($r1 + ($r2 - $r1) * $t);
        $g = (int)($g1 + ($g2 - $g1) * $t);
        $b = (int)($b1 + ($b2 - $b1) * $t);
        $col = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $w, $y, $col);
    }

    // Add product color swatch circle if available
    if ($p['first_color']) {
        $ch = ltrim($p['first_color'], '#');
        $cr = hexdec(substr($ch,0,2)); $cg = hexdec(substr($ch,2,2)); $cb = hexdec(substr($ch,4,2));
        $swatchCol = imagecolorallocate($img, $cr, $cg, $cb);
        $white     = imagecolorallocate($img, 255, 255, 255);
        imagefilledellipse($img, $w/2, $h/2 - 40, 180, 180, $white);
        imagefilledellipse($img, $w/2, $h/2 - 40, 160, 160, $swatchCol);
    }

    // Text: brand and name
    $white = imagecolorallocate($img, 255, 255, 255);
    $dark  = imagecolorallocate($img, 80, 40, 55);

    // Use built-in font (no TTF needed)
    $brandText = mb_strlen($p['brand']) > 12 ? mb_substr($p['brand'],0,12).'...' : $p['brand'];
    $nameText  = mb_strlen($p['name'])  > 14 ? mb_substr($p['name'], 0,14).'...' : $p['name'];

    // Draw semi-transparent bottom bar
    $barColor = imagecolorallocatealpha($img, 255, 255, 255, 50);
    imagefilledrectangle($img, 0, $h-120, $w, $h, $barColor);

    // Draw text (using built-in fonts, size 5 is largest)
    imagestring($img, 5, 20, $h-100, $brandText, $dark);
    imagestring($img, 4, 20, $h-70,  $nameText,  $dark);
    imagestring($img, 3, 20, $h-45,  $cat,       $dark);

    imagejpeg($img, $path, 90);
    imagedestroy($img);
    $generated++;
    echo "生成 [{$p['id']}] {$p['brand']} - {$p['name']}\n";
}

echo "\n完成！共生成 $generated 張佔位圖\n";

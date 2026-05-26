<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '只支援 POST']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (empty($payload['imageBase64'])) {
    http_response_code(400);
    echo json_encode(['error' => '缺少 imageBase64']);
    exit;
}

$imageBase64 = $payload['imageBase64'];
$mimeType    = $payload['mimeType'] ?? 'image/jpeg';

// ── 呼叫 GROQ Vision API ────────────────────────────────────────────
$groqApiKey   = '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';
$groqEndpoint = 'https://api.groq.com/openai/v1/chat/completions';

$prompt = '你是彩妝產品識別專家。分析這張產品照片，只輸出 JSON，不要任何說明或 markdown。

格式：
{
  "brand": "品牌名，優先辨識包裝上的文字（看不清楚填空字串）",
  "product_type": "產品類型，如睫毛膏、粉底液、口紅、眼影盤、眼線筆、腮紅、氣墊粉餅等（不確定填空字串，寧可填空也不要猜錯）",
  "shape": "外觀容器形狀，如細長管狀、扁平盒狀、調色盤、氣墊、粉條、膏狀管等",
  "attributes": ["顏色或質地描述，如黑色、霧面、玫瑰色等"]
}

重要：brand 辨識優先級最高；product_type 不確定時填空字串，不要猜測。';

$requestBody = json_encode([
    'model'    => 'meta-llama/llama-4-scout-17b-16e-instruct',
    'messages' => [[
        'role'    => 'user',
        'content' => [
            ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageBase64}"]],
            ['type' => 'text',      'text'      => $prompt]
        ]
    ]],
    'max_tokens'  => 200,
    'temperature' => 0.1
]);

$ch = curl_init($groqEndpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestBody,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $groqApiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'AI 分析失敗', 'detail' => $response]);
    exit;
}

$groqData = json_decode($response, true);
$content  = $groqData['choices'][0]['message']['content'] ?? '';
$content  = preg_replace('/```json\s*|\s*```/', '', trim($content));
$parsed   = json_decode($content, true);

if (!$parsed) {
    http_response_code(500);
    echo json_encode(['error' => 'AI 回應格式錯誤', 'raw' => $content]);
    exit;
}

$productType = trim($parsed['product_type'] ?? '');
$brand       = trim($parsed['brand']        ?? '');
$shape       = trim($parsed['shape']        ?? '');
$attributes  = array_filter(array_map('trim', $parsed['attributes'] ?? []));

// ── 相關度評分搜尋 ──────────────────────────────────────────────────
// 品牌吻合 = 15分（最高），product_type 吻合 = 8分加分，shape/attributes 各 1分
// 策略：brand 識別到 → 以 brand 為必要篩選條件，product_type 只影響排名
//       brand 識別不到 → 以 product_type 為必要篩選條件

$scoreParts = [];
$params     = [];

if ($brand) {
    $scoreParts[] = "CASE WHEN p.brand ILIKE ? THEN 15 ELSE 0 END";    $params[] = "%$brand%";
    $scoreParts[] = "CASE WHEN p.name  ILIKE ? THEN 5  ELSE 0 END";    $params[] = "%$brand%";
}

if ($productType) {
    $scoreParts[] = "CASE WHEN p.name     ILIKE ? THEN 8 ELSE 0 END";  $params[] = "%$productType%";
    $scoreParts[] = "CASE WHEN p.purpose  ILIKE ? THEN 6 ELSE 0 END";  $params[] = "%$productType%";
    $scoreParts[] = "CASE WHEN p.category ILIKE ? THEN 4 ELSE 0 END";  $params[] = "%$productType%";
}

if ($shape) {
    $scoreParts[] = "CASE WHEN p.name    ILIKE ? THEN 1 ELSE 0 END";   $params[] = "%$shape%";
    $scoreParts[] = "CASE WHEN p.purpose ILIKE ? THEN 1 ELSE 0 END";   $params[] = "%$shape%";
}

foreach ($attributes as $attr) {
    $scoreParts[] = "CASE WHEN p.name    ILIKE ? THEN 1 ELSE 0 END";   $params[] = "%$attr%";
    $scoreParts[] = "CASE WHEN p.purpose ILIKE ? THEN 1 ELSE 0 END";   $params[] = "%$attr%";
}

if (empty($scoreParts)) {
    echo json_encode(['products' => [], 'parsed' => $parsed, 'terms' => []]);
    exit;
}

$scoreExpr = '(' . implode(' + ', $scoreParts) . ')';

// WHERE 條件：brand 識別到 → 以品牌篩選（product_type 只加分）；否則以 product_type 篩選
$whereParts  = [];
$whereParams = [];
if ($brand) {
    $whereParts[]  = "(p.brand ILIKE ? OR p.name ILIKE ?)";
    $whereParams   = array_merge($whereParams, ["%$brand%", "%$brand%"]);
} elseif ($productType) {
    $whereParts[]  = "(p.name ILIKE ? OR p.purpose ILIKE ? OR p.category ILIKE ?)";
    $whereParams   = array_merge($whereParams, ["%$productType%", "%$productType%", "%$productType%"]);
}

$whereClause = implode(' OR ', $whereParts);
$allParams   = array_merge($params, $whereParams);

$sql = "SELECT p.id, p.name, p.brand, p.category, p.image_url,
               $scoreExpr AS relevance
        FROM data p
        WHERE $whereClause
        ORDER BY relevance DESC
        LIMIT 24";

$stmt = $pdo->prepare($sql);
$stmt->execute($allParams);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 顯示給前端的標籤：brand + product_type + attributes
$displayTerms = array_filter(array_merge(
    $brand       ? [$brand]       : [],
    $productType ? [$productType] : [],
    $attributes
));

echo json_encode([
    'products' => $products,
    'parsed'   => $parsed,
    'terms'    => array_values($displayTerms)
], JSON_UNESCAPED_UNICODE);

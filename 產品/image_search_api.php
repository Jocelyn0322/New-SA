<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include 'db.php';

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
  "product_type": "具體產品名稱，如睫毛膏、粉底液、口紅、眼影盤、眼線筆、腮紅、氣墊粉餅等",
  "brand": "品牌名（看不清楚填空字串）",
  "attributes": ["顏色或質地描述，如黑色、霧面、玫瑰色等"]
}

重要：product_type 必須是最具體的產品類型，不要填大類別（不要填「眼妝」，要填「睫毛膏」）。';

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
$attributes  = array_filter(array_map('trim', $parsed['attributes'] ?? []));

// ── 相關度評分搜尋 ──────────────────────────────────────────────────
// 分數設計：product_type 命中名稱 = 10分，命中用途 = 8分；brand 命中 = 5分；attributes 各 1分
// 至少需要 product_type 或 brand 命中才收錄

$scoreParts = [];
$params     = [];

if ($productType) {
    $scoreParts[] = "CASE WHEN p.name    ILIKE ? THEN 10 ELSE 0 END";  $params[] = "%$productType%";
    $scoreParts[] = "CASE WHEN p.purpose ILIKE ? THEN 8  ELSE 0 END";  $params[] = "%$productType%";
    $scoreParts[] = "CASE WHEN p.category ILIKE ? THEN 3 ELSE 0 END";  $params[] = "%$productType%";
}

if ($brand) {
    $scoreParts[] = "CASE WHEN p.brand ILIKE ? THEN 5 ELSE 0 END";     $params[] = "%$brand%";
    $scoreParts[] = "CASE WHEN p.name  ILIKE ? THEN 3 ELSE 0 END";     $params[] = "%$brand%";
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

// WHERE 條件：product_type 或 brand 至少命中一個欄位
$whereParts = [];
$whereParams = [];
if ($productType) {
    $whereParts[]  = "(p.name ILIKE ? OR p.purpose ILIKE ? OR p.category ILIKE ?)";
    $whereParams   = array_merge($whereParams, ["%$productType%", "%$productType%", "%$productType%"]);
}
if ($brand) {
    $whereParts[]  = "(p.brand ILIKE ? OR p.name ILIKE ?)";
    $whereParams   = array_merge($whereParams, ["%$brand%", "%$brand%"]);
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

// 顯示給前端的標籤：product_type + brand + attributes
$displayTerms = array_filter(array_merge(
    $productType ? [$productType] : [],
    $brand       ? [$brand]       : [],
    $attributes
));

echo json_encode([
    'products' => $products,
    'parsed'   => $parsed,
    'terms'    => array_values($displayTerms)
], JSON_UNESCAPED_UNICODE);

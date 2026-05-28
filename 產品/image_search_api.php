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

$prompt = '你是彩妝產品識別專家。分析這張產品照片（可能是打開或合起的外觀），只輸出 JSON，不要任何說明或 markdown。

資料庫固定分類（category 只能填以下其中一個，或空字串）：
底妝、遮瑕、眼影、眼線、睫毛膏、腮紅、修容、打亮、唇彩、護膚、護唇、防曬

格式：
{
  "brand": "從包裝上任何文字辨識品牌名（看不清楚填空字串）",
  "category": "從上方固定分類中選最符合的一個（完全不確定填空字串）",
  "keywords": ["產品系列名、色系、其他搜尋關鍵字，最多3個，看不出來留空陣列"]
}

重要：
- brand 是第一優先，仔細辨識包裝上所有文字
- category 必須完全符合固定分類中的一個詞，不確定就填空字串，不要猜
- 眼影盤→眼影、口紅/唇膏→唇彩、粉底/氣墊→底妝、腮紅→腮紅、修容/立體→修容';

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

$brand    = trim($parsed['brand']    ?? '');
$category = trim($parsed['category'] ?? '');
$keywords = array_filter(array_map('trim', $parsed['keywords'] ?? []));

$validCategories = ['底妝','遮瑕','眼影','眼線','睫毛膏','腮紅','修容','打亮','唇彩','護膚','護唇','防曬'];

// 使用者手動選類別時直接覆蓋 AI 的判斷（更準確）
$userCategory = trim($payload['userCategory'] ?? '');
if ($userCategory && in_array($userCategory, $validCategories)) {
    $category = $userCategory;
} elseif ($category && !in_array($category, $validCategories)) {
    $category = '';
}

// ── 相關度評分搜尋 ──────────────────────────────────────────────────
// 品牌完全匹配 = 20分（最高），category 精確匹配 = 10分，keywords = 各2分
$scoreParts = [];
$params     = [];

if ($brand) {
    $scoreParts[] = "CASE WHEN p.brand ILIKE ? THEN 20 ELSE 0 END";  $params[] = "%$brand%";
    $scoreParts[] = "CASE WHEN p.name  ILIKE ? THEN 5  ELSE 0 END";  $params[] = "%$brand%";
}

if ($category) {
    $scoreParts[] = "CASE WHEN p.category = ? THEN 10 ELSE 0 END";   $params[] = $category;
}

foreach ($keywords as $kw) {
    $scoreParts[] = "CASE WHEN p.name    ILIKE ? THEN 2 ELSE 0 END"; $params[] = "%$kw%";
    $scoreParts[] = "CASE WHEN p.purpose ILIKE ? THEN 2 ELSE 0 END"; $params[] = "%$kw%";
}

if (empty($scoreParts)) {
    echo json_encode(['products' => [], 'parsed' => $parsed, 'terms' => []]);
    exit;
}

$scoreExpr = '(' . implode(' + ', $scoreParts) . ')';

// WHERE 條件：
//   brand 識別到 → 以品牌過濾（category 只加分，不限制）
//   brand 未識別 → 以 category 過濾
//   都沒有 → 已在上方 exit
$whereParts  = [];
$whereParams = [];
if ($brand) {
    $whereParts[]  = "(p.brand ILIKE ? OR p.name ILIKE ?)";
    $whereParams   = array_merge($whereParams, ["%$brand%", "%$brand%"]);
} elseif ($category) {
    $whereParts[]  = "p.category = ?";
    $whereParams[] = $category;
}

$whereClause = implode(' AND ', $whereParts);
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

// 顯示給前端的標籤
$displayTerms = array_filter(array_merge(
    $brand    ? [$brand]    : [],
    $category ? [$category] : [],
    $keywords
));

echo json_encode([
    'products' => $products,
    'parsed'   => $parsed,
    'terms'    => array_values($displayTerms)
], JSON_UNESCAPED_UNICODE);

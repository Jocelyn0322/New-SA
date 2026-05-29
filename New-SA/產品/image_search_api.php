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

資料庫固定分類（category 只能填以下其中一個，或空字串）：
底妝、遮瑕、眼影、眼線、睫毛膏、腮紅、修容、打亮、唇彩、護膚、護唇、防曬

格式：
{
  "brand": "品牌名",
  "category": "分類",
  "keywords": ["關鍵字1", "關鍵字2"]
}

brand 規則（非常重要）：
- 只填寫照片中肉眼能直接讀到的品牌文字，例如包裝上印著 CHANEL、DIOR、MAC 等字樣
- 看不到清楚的品牌文字 → 一定要填空字串 ""
- 絕對禁止從外觀顏色、設計風格、產品形狀去推測或猜測品牌
- 寧可填空也不能猜錯

category 規則：
- 必須是固定分類中的一個詞，不確定填 ""
- 眼影盤/眼影粉→眼影、口紅/唇膏/唇釉→唇彩、粉底/氣墊/BB霜→底妝、打亮/highlighter→打亮、修容/bronzer/contour→修容

keywords：產品系列名或色號（如看到文字），最多2個，看不出來留 []';

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
    $scoreParts[] = "CASE WHEN p.brand LIKE ? THEN 20 ELSE 0 END";  $params[] = "%$brand%";
    $scoreParts[] = "CASE WHEN p.name  LIKE ? THEN 5  ELSE 0 END";  $params[] = "%$brand%";
}

if ($category) {
    $scoreParts[] = "CASE WHEN p.category = ? THEN 10 ELSE 0 END";   $params[] = $category;
}

foreach ($keywords as $kw) {
    $scoreParts[] = "CASE WHEN p.name    LIKE ? THEN 2 ELSE 0 END"; $params[] = "%$kw%";
    $scoreParts[] = "CASE WHEN p.purpose LIKE ? THEN 2 ELSE 0 END"; $params[] = "%$kw%";
}

if (empty($scoreParts)) {
    echo json_encode(['products' => [], 'parsed' => $parsed, 'terms' => []]);
    exit;
}

$scoreExpr = '(' . implode(' + ', $scoreParts) . ')';

// WHERE 策略：
//   使用者手選了類別  → category 為主要 filter，brand 只加分
//   AI 識別到 brand + category → category 為主要 filter，brand 只加分（顯示全分類，品牌排前）
//   AI 只識別到 brand → 以 brand 過濾
//   AI 只識別到 category → 以 category 過濾
$whereParts  = [];
$whereParams = [];
if ($userCategory || ($brand && $category)) {
    // 有 category（來自使用者或 AI 兩者都識別到）→ category 為主
    $whereParts[]  = "p.category = ?";
    $whereParams[] = $category;
} elseif ($brand) {
    $whereParts[]  = "(p.brand LIKE ? OR p.name LIKE ?)";
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

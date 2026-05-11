<?php
header('Content-Type: application/json; charset=utf-8');

function isDebugMode(): bool
{
    $value = getenv('APP_DEBUG');
    return is_string($value) && in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function sanitizeText(string $text): string
{
    $masked = preg_replace('/gsk_[A-Za-z0-9]+/', 'gsk_***', $text);
    if (!is_string($masked)) {
        return $text;
    }
    return $masked;
}

function errorResponse(int $status, string $publicMessage, array $debugContext = []): void
{
    http_response_code($status);
    $payload = [
        'error' => true,
        'message' => $publicMessage,
    ];

    if (isDebugMode() && !empty($debugContext)) {
        $payload['debug'] = $debugContext;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse(405, '只支援 POST');
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    errorResponse(400, '請提供有效 JSON');
}

$imageBase64 = isset($payload['imageBase64']) ? trim((string)$payload['imageBase64']) : '';
$userPreference = isset($payload['userPreference']) ? trim((string)$payload['userPreference']) : 'Matte';
$makeupPreference = isset($payload['makeupPreference']) && is_array($payload['makeupPreference']) ? $payload['makeupPreference'] : [];

if ($imageBase64 === '') {
    errorResponse(400, '缺少 imageBase64');
}

function resolveNodeBinary(): string
{
    $envNodeBin = getenv('NODE_BIN');
    if (is_string($envNodeBin) && $envNodeBin !== '' && is_executable($envNodeBin)) {
        return $envNodeBin;
    }

    $candidates = [
        '/opt/homebrew/bin/node',
        '/usr/local/bin/node',
        '/usr/bin/node',
        '/bin/node',
    ];

    foreach ($candidates as $candidate) {
        if (is_executable($candidate)) {
            return $candidate;
        }
    }

    if (function_exists('shell_exec')) {
        $which = trim((string)shell_exec('PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:$PATH" command -v node 2>/dev/null'));
        if ($which !== '' && is_executable($which)) {
            return $which;
        }
    }

    return '';
}

$nodeBin = resolveNodeBinary();
if ($nodeBin === '') {
    errorResponse(
        500,
        '找不到 node 執行環境，請確認 Node.js 已安裝，或在 Apache/PHP 環境設定 NODE_BIN=/opt/homebrew/bin/node'
    );
}

$projectRoot = __DIR__;
$scriptPath = $projectRoot . '/scripts/analyzeSkinFromBase64.js';

if (!file_exists($scriptPath)) {
    errorResponse(500, '找不到分析腳本 analyzeSkinFromBase64.js');
}

$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$safeEnv = $_ENV;
$safeEnv['PATH'] = '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin';

foreach (['DYLD_LIBRARY_PATH', 'DYLD_INSERT_LIBRARIES', 'LD_LIBRARY_PATH'] as $dangerKey) {
    if (isset($safeEnv[$dangerKey])) {
        unset($safeEnv[$dangerKey]);
    }
}

$command = [$nodeBin, $scriptPath];
$process = proc_open($command, $descriptorSpec, $pipes, $projectRoot, $safeEnv);

if (!is_resource($process)) {
    errorResponse(500, '無法啟動分析程序');
}

$input = json_encode([
    'imageBase64' => $imageBase64,
    'userPreference' => $userPreference,
    'makeupPreference' => $makeupPreference,
], JSON_UNESCAPED_UNICODE);

fwrite($pipes[0], $input);
fclose($pipes[0]);

$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);

$exitCode = proc_close($process);

if ($exitCode !== 0) {
    $safeStdErr = sanitizeText(trim((string)$stderr));
    $errorJson = json_decode($safeStdErr, true);
    $rawMessage = $errorJson['message'] ?? ($safeStdErr ?: '分析程序執行失敗');
    $safeMessage = sanitizeText((string)$rawMessage);

    $publicMessage = str_contains($safeMessage, 'Groq API 請求失敗')
        ? 'AI 膚質分析服務暫時無法處理此圖片，請確認照片清晰並稍後再試'
        : $safeMessage;

    errorResponse(500, $publicMessage, [
        'node' => $nodeBin,
        'exitCode' => $exitCode,
        'stderr' => $safeStdErr,
    ]);
}

$decoded = json_decode($stdout, true);
if (!is_array($decoded)) {
    errorResponse(500, '分析程序回傳格式錯誤', [
        'node' => $nodeBin,
        'stdout' => sanitizeText(substr((string)$stdout, 0, 500)),
    ]);
}

echo json_encode($decoded, JSON_UNESCAPED_UNICODE);

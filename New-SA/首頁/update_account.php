<?php
session_start();
require __DIR__ . '/../db.php';
require __DIR__ . '/send_mail.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$action   = $body['action'] ?? '';
$username = $_SESSION['user'];

function _genCode() { return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT); }
function _maskEmail($e) {
    $p = explode('@', $e);
    if (count($p) < 2) return $e;
    return mb_substr($p[0], 0, 1) . str_repeat('*', max(1, mb_strlen($p[0]) - 1)) . '@' . $p[1];
}

try {
    $stmt = $pdo->prepare("SELECT email, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $me = $stmt->fetch();
    if (!$me) { echo json_encode(['success' => false, 'message' => '帳號不存在']); exit; }

    // ───────── 改 Email 步驟1：驗證目前密碼，寄驗證碼到「原信箱」 ─────────
    if ($action === 'email_request') {
        $newEmail = trim($body['email'] ?? '');
        $pwd      = $body['current_password'] ?? '';
        if ($me['password'] !== $pwd)                       { echo json_encode(['success'=>false,'message'=>'目前密碼不正確']); exit; }
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL))  { echo json_encode(['success'=>false,'message'=>'Email 格式不正確']); exit; }
        if ($newEmail === $me['email'])                     { echo json_encode(['success'=>false,'message'=>'新 Email 與目前相同']); exit; }
        if (empty($me['email']))                            { echo json_encode(['success'=>false,'message'=>'目前帳號沒有信箱可驗證，請聯絡管理員']); exit; }
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND username != ?");
        $chk->execute([$newEmail, $username]);
        if ($chk->fetch())                                  { echo json_encode(['success'=>false,'message'=>'此 Email 已被使用']); exit; }

        $code = _genCode();
        $_SESSION['email_chg'] = ['new' => $newEmail, 'stage' => 'old', 'code' => $code, 'exp' => time() + 900];
        $sent = sendVerificationEmail($me['email'], $username, $code);
        echo json_encode(['success'=>true, 'stage'=>'old', 'sent'=>$sent,
            'message'=>'驗證碼已寄到你目前的信箱（'._maskEmail($me['email']).'），請輸入確認']);
        exit;
    }

    // ───────── 改 Email 步驟2：驗證原信箱碼 → 寄新碼到「新信箱」 ─────────
    if ($action === 'email_verify_old') {
        $code = trim($body['code'] ?? '');
        $s = $_SESSION['email_chg'] ?? null;
        if (!$s || $s['stage'] !== 'old') { echo json_encode(['success'=>false,'message'=>'請重新發起變更']); exit; }
        if (time() > $s['exp']) { unset($_SESSION['email_chg']); echo json_encode(['success'=>false,'message'=>'驗證碼已過期，請重新發起']); exit; }
        if ($code !== $s['code']) { echo json_encode(['success'=>false,'message'=>'驗證碼錯誤']); exit; }

        $code2 = _genCode();
        $_SESSION['email_chg']['stage'] = 'new';
        $_SESSION['email_chg']['code']  = $code2;
        $_SESSION['email_chg']['exp']   = time() + 900;
        $sent = sendVerificationEmail($s['new'], $username, $code2);
        echo json_encode(['success'=>true, 'stage'=>'new', 'sent'=>$sent,
            'message'=>'原信箱已確認！新的驗證碼已寄到新信箱（'._maskEmail($s['new']).'），請輸入以完成變更']);
        exit;
    }

    // ───────── 改 Email 步驟3：驗證新信箱碼 → 真正更新 ─────────
    if ($action === 'email_verify_new') {
        $code = trim($body['code'] ?? '');
        $s = $_SESSION['email_chg'] ?? null;
        if (!$s || $s['stage'] !== 'new') { echo json_encode(['success'=>false,'message'=>'請先完成上一步']); exit; }
        if (time() > $s['exp']) { unset($_SESSION['email_chg']); echo json_encode(['success'=>false,'message'=>'驗證碼已過期，請重新發起']); exit; }
        if ($code !== $s['code']) { echo json_encode(['success'=>false,'message'=>'驗證碼錯誤']); exit; }

        $newEmail = $s['new'];
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND username != ?");
        $chk->execute([$newEmail, $username]);
        if ($chk->fetch()) { unset($_SESSION['email_chg']); echo json_encode(['success'=>false,'message'=>'此 Email 剛剛被他人使用']); exit; }

        $pdo->prepare("UPDATE users SET email = ? WHERE username = ?")->execute([$newEmail, $username]);
        unset($_SESSION['email_chg']);
        echo json_encode(['success'=>true, 'stage'=>'done', 'message'=>'Email 已成功更新為 '.$newEmail, 'new_email'=>$newEmail]);
        exit;
    }

    // ───────── 改密碼 步驟1：驗證目前密碼，寄驗證碼到信箱 ─────────
    if ($action === 'pwd_request') {
        $pwd    = $body['current_password'] ?? '';
        $newPwd = $body['new_password'] ?? '';
        if ($me['password'] !== $pwd)  { echo json_encode(['success'=>false,'message'=>'目前密碼不正確']); exit; }
        if (strlen($newPwd) < 6)       { echo json_encode(['success'=>false,'message'=>'新密碼至少需要 6 個字元']); exit; }
        if (empty($me['email']))       { echo json_encode(['success'=>false,'message'=>'目前帳號沒有信箱可驗證，請聯絡管理員']); exit; }

        $code = _genCode();
        $_SESSION['pwd_chg'] = ['new' => $newPwd, 'code' => $code, 'exp' => time() + 900];
        $sent = sendVerificationEmail($me['email'], $username, $code);
        echo json_encode(['success'=>true, 'sent'=>$sent,
            'message'=>'驗證碼已寄到你的信箱（'._maskEmail($me['email']).'），請輸入以完成變更']);
        exit;
    }

    // ───────── 改密碼 步驟2：驗證碼 → 真正更新 ─────────
    if ($action === 'pwd_verify') {
        $code = trim($body['code'] ?? '');
        $s = $_SESSION['pwd_chg'] ?? null;
        if (!$s) { echo json_encode(['success'=>false,'message'=>'請先發起變更']); exit; }
        if (time() > $s['exp']) { unset($_SESSION['pwd_chg']); echo json_encode(['success'=>false,'message'=>'驗證碼已過期，請重新發起']); exit; }
        if ($code !== $s['code']) { echo json_encode(['success'=>false,'message'=>'驗證碼錯誤']); exit; }

        $pdo->prepare("UPDATE users SET password = ? WHERE username = ?")->execute([$s['new'], $username]);
        unset($_SESSION['pwd_chg']);
        echo json_encode(['success'=>true, 'message'=>'密碼已成功更新']);
        exit;
    }

    // ───────── 改 Email：重新寄送驗證碼（依目前階段寄到原/新信箱）─────────
    if ($action === 'email_resend') {
        $s = $_SESSION['email_chg'] ?? null;
        if (!$s) { echo json_encode(['success'=>false,'message'=>'請重新發起變更']); exit; }
        $code = _genCode();
        $_SESSION['email_chg']['code'] = $code;
        $_SESSION['email_chg']['exp']  = time() + 900;
        $target = $s['stage'] === 'old' ? $me['email'] : $s['new'];
        $sent = sendVerificationEmail($target, $username, $code);
        echo json_encode(['success'=>true, 'sent'=>$sent, 'message'=>'已重新寄送驗證碼到 '._maskEmail($target)]);
        exit;
    }

    // ───────── 改 Email：取消 ─────────
    if ($action === 'email_cancel') {
        unset($_SESSION['email_chg']);
        echo json_encode(['success'=>true, 'message'=>'已取消變更']);
        exit;
    }

    // ───────── 改密碼：重新寄送驗證碼 ─────────
    if ($action === 'pwd_resend') {
        $s = $_SESSION['pwd_chg'] ?? null;
        if (!$s) { echo json_encode(['success'=>false,'message'=>'請重新發起變更']); exit; }
        $code = _genCode();
        $_SESSION['pwd_chg']['code'] = $code;
        $_SESSION['pwd_chg']['exp']  = time() + 900;
        $sent = sendVerificationEmail($me['email'], $username, $code);
        echo json_encode(['success'=>true, 'sent'=>$sent, 'message'=>'已重新寄送驗證碼到 '._maskEmail($me['email'])]);
        exit;
    }

    // ───────── 改密碼：取消 ─────────
    if ($action === 'pwd_cancel') {
        unset($_SESSION['pwd_chg']);
        echo json_encode(['success'=>true, 'message'=>'已取消變更']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => '不支援的操作']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '資料庫錯誤']);
}

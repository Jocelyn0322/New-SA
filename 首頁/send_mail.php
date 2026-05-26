<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

// ── 寄件帳號設定（統一由此帳號送出所有系統信）──
define('MAIL_USER', 'jocelynfan.tw@gmail.com');
define('MAIL_PASS', 'wnkmjytmssjnwwfo');   // Google App 密碼（16碼）
define('MAIL_NAME', 'COSMETIC');

function _setupMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USER;
    $mail->Password   = MAIL_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];
    $mail->setFrom(MAIL_USER, MAIL_NAME);
    return $mail;
}

function sendProductReviewEmail($toEmail, $username, $productName, $status, $adminNote = '') {
    $mail = _setupMailer();

    $isApproved  = ($status === 'approved');
    $subjectText = $isApproved ? '您的商品申請已通過審核' : '您的商品申請未通過審核';
    $statusColor = $isApproved ? '#27ae60' : '#c0392b';
    $statusLabel = $isApproved ? '✅ 審核通過' : '❌ 審核未通過';
    $noteHtml    = $adminNote
        ? "<p><strong>管理員備註：</strong></p><blockquote style='border-left:3px solid #ddd;padding-left:12px;color:#555;'>{$adminNote}</blockquote>"
        : '';
    $nextStep    = $isApproved
        ? '<p>您的商品將在完成內容確認後正式上架，感謝您的貢獻！</p>'
        : '<p>如有疑問，歡迎聯繫管理員。您可依建議修改後重新提交。</p>';

    try {
        $mail->addAddress($toEmail, $username);
        $mail->isHTML(true);
        $mail->Subject = $subjectText;
        $mail->Body    = "
            <div style='font-family:sans-serif;max-width:520px;margin:auto;'>
                <h2 style='color:{$statusColor};'>{$statusLabel}</h2>
                <p>您好，<strong>{$username}</strong></p>
                <p>您提交的商品申請：<strong>「{$productName}」</strong>，審核結果如下：</p>
                <div style='background:#f8f8f8;border-radius:8px;padding:16px;margin:16px 0;'>
                    <p style='font-size:18px;font-weight:bold;color:{$statusColor};margin:0;'>{$statusLabel}</p>
                </div>
                {$noteHtml}
                {$nextStep}
                <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                <p style='color:#aaa;font-size:12px;'>此郵件由系統自動發送，請勿回覆。</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("商品審核 Email 發送失敗: " . $mail->ErrorInfo);
        return false;
    }
}

function sendVerificationEmail($toEmail, $username, $code) {
    $mail = _setupMailer();

    try {
        $mail->addAddress($toEmail, $username);

        $mail->isHTML(true);
        $mail->Subject = 'Email 驗證碼';
        $mail->Body = "
            <h2>Email 驗證</h2>
            <p>您好，{$username}</p>
            <p>您的驗證碼是：</p>
            <h1 style='color:#ff5a7e;'>{$code}</h1>
            <p>此驗證碼 15 分鐘內有效。</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email 發送失敗: " . $mail->ErrorInfo);
        return false;
    }
}

function sendPasswordResetEmail($toEmail, $username, $code) {
    $mail = _setupMailer();

    try {
        $mail->addAddress($toEmail, $username);

        $mail->isHTML(true);
        $mail->Subject = '重設密碼驗證碼';
        $mail->Body    = "
            <div style='font-family:sans-serif;max-width:480px;margin:auto;'>
                <h2 style='color:#ff5a7e;'>重設密碼</h2>
                <p>您好，<strong>{$username}</strong></p>
                <p>您申請了密碼重設，驗證碼如下：</p>
                <div style='background:#fff5f6;border-radius:8px;padding:20px;text-align:center;margin:16px 0;'>
                    <span style='font-size:36px;font-weight:bold;color:#ff3a6f;letter-spacing:8px;'>{$code}</span>
                </div>
                <p>此驗證碼 <strong>15 分鐘</strong>內有效。</p>
                <p>若非您本人申請，請忽略此信，密碼不會被更改。</p>
                <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                <p style='color:#aaa;font-size:12px;'>此郵件由系統自動發送，請勿回覆。</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("密碼重設 Email 發送失敗: " . $mail->ErrorInfo);
        return false;
    }
}
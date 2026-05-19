<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function sendProductReviewEmail($toEmail, $username, $productName, $status, $adminNote = '') {
    $mail = new PHPMailer(true);

    if (!defined('GMAIL_USER') && file_exists(__DIR__ . '/mail_config.php')) {
        require_once __DIR__ . '/mail_config.php';
    }
    $gmailUser = defined('GMAIL_USER') ? GMAIL_USER : getenv('GMAIL_USER');
    $gmailPass = defined('GMAIL_PASS') ? GMAIL_PASS : getenv('GMAIL_PASS');
    if (!$gmailUser || !$gmailPass) {
        error_log('Email 設定遺失：請設定 mail_config.php 或環境變數');
        return false;
    }

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
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $gmailUser;
        $mail->Password   = $gmailPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($gmailUser, 'Makeup Website');
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
    $mail = new PHPMailer(true);

    // 從環境變數讀取。設定方式：在 XAMPP httpd.conf 加入：
    //   SetEnv GMAIL_USER yourname@gmail.com
    //   SetEnv GMAIL_PASS xxxx xxxx xxxx xxxx（Google App 密碼，16碼）
    if (!defined('GMAIL_USER') && file_exists(__DIR__ . '/mail_config.php')) {
        require_once __DIR__ . '/mail_config.php';
    }
    $gmailUser = defined('GMAIL_USER') ? GMAIL_USER : getenv('GMAIL_USER');
    $gmailPass = defined('GMAIL_PASS') ? GMAIL_PASS : getenv('GMAIL_PASS');
    if (!$gmailUser || !$gmailPass) {
        error_log('Email 設定遺失：請設定 mail_config.php 或環境變數');
        return false;
    }

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $gmailUser;
        $mail->Password = $gmailPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($gmailUser, 'Makeup Website');
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
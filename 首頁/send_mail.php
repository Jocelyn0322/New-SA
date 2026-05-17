<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function sendVerificationEmail($toEmail, $username, $code) {
    $mail = new PHPMailer(true);

    // 從環境變數讀取。設定方式：在 XAMPP httpd.conf 加入：
    //   SetEnv GMAIL_USER yourname@gmail.com
    //   SetEnv GMAIL_PASS xxxx xxxx xxxx xxxx（Google App 密碼，16碼）
    $gmailUser = getenv('GMAIL_USER');
    $gmailPass = getenv('GMAIL_PASS');
    if (!$gmailUser || !$gmailPass) {
        error_log('Email 設定遺失：請設定 GMAIL_USER 與 GMAIL_PASS 環境變數');
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

function sendVideoRemovedEmail($toEmail, $username, $videoTitle, $reason, $appealUrl) {
    $mail = new PHPMailer(true);
    $gmailUser = getenv('GMAIL_USER');
    $gmailPass = getenv('GMAIL_PASS');
    if (!$gmailUser || !$gmailPass) {
        error_log('Email 設定遺失：請設定 GMAIL_USER 與 GMAIL_PASS 環境變數');
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
        $mail->Subject = '【重要】您的影片已被下架';
        $mail->Body = "
            <div style='font-family:sans-serif;max-width:520px;margin:auto;'>
            <h2 style='color:#e83e5a;'>您的影片已被下架</h2>
            <p>您好，<strong>{$username}</strong></p>
            <p>您上傳的影片「<strong>" . htmlspecialchars($videoTitle) . "</strong>」因違反社群規範已被管理員下架。</p>
            <p><strong>下架原因：</strong><br>" . nl2br(htmlspecialchars($reason)) . "</p>
            <hr style='margin:20px 0;border:none;border-top:1px solid #eee;'>
            <p>若您認為此決定有誤，可在 <strong>7 天內</strong>提出申訴：</p>
            <a href='{$appealUrl}' style='display:inline-block;background:#e83e5a;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;'>提出申訴</a>
            <p style='margin-top:20px;color:#999;font-size:12px;'>若未在期限內申訴，影片將被永久刪除。</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email 發送失敗: " . $mail->ErrorInfo);
        return false;
    }
}
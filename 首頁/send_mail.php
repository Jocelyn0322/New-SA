<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function sendVerificationEmail($toEmail, $username, $code) {
    $mail = new PHPMailer(true);

    // 從環境變數讀取。設定方式：在 XAMPP httpd.conf 加入：
    //   SetEnv GMAIL_USER yourname@gmail.com
    //   SetEnv GMAIL_PASS xxxx xxxx xxxx xxxx（Google App 密碼，16碼）
    if (!defined('GMAIL_USER')) {
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
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function sendVerificationEmail($toEmail, $username, $code) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com';  // 改成你的 Gmail
        $mail->Password = 'your-app-password';      // Google App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('your-email@gmail.com', 'Makeup Website');
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

<?php

function sendEmail(string $to, string $subject, string $message): bool
{
    // Load credentials from environment variables.
    $env = parse_ini_file(dirname(__DIR__) . '/.env');
    
    if ($env === false) {
        error_log('QueueFlow: Unable to read the .env file.');
        return false;
    }

    $gmail = $env['QUEUEFLOW_GMAIL'] ?? '';
    $appPassword = $env['QUEUEFLOW_GMAIL_APP_PASSWORD'] ?? '';

    if (!$gmail || !$appPassword) {
        error_log('QueueFlow: Gmail SMTP credentials are not configured.');
        return false;
    }

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('QueueFlow: Invalid recipient email address.');
        return false;
    }

    if (!function_exists('curl_init')) {
        error_log('QueueFlow: PHP cURL extension is not enabled.');
        return false;
    }

    // Prevent email header injection.
    $subject = str_replace(["\r", "\n"], '', $subject);

    // Encode the email subject.
    if (function_exists('mb_encode_mimeheader')) {
        $subject = mb_encode_mimeheader($subject, 'UTF-8');
    }

    // Normalize email body line endings.
    $message = str_replace(
        ["\r\n", "\r"],
        "\n",
        $message
    );

    $message = str_replace("\n", "\r\n", $message);

    // Build the complete email.
    $email =
        "Date: " . date(DATE_RFC2822) . "\r\n" .
        "From: QueueFlow <$gmail>\r\n" .
        "To: <$to>\r\n" .
        "Subject: $subject\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/html; charset=UTF-8\r\n" .
        "Content-Transfer-Encoding: 8bit\r\n" .
        "\r\n" .
        $message . "\r\n";

    $position = 0;

    $ch = curl_init();

    if ($ch === false) {
        error_log('QueueFlow: Unable to initialize cURL.');
        return false;
    }

    curl_setopt_array($ch, [

        CURLOPT_URL => 'smtps://smtp.gmail.com:465',

        CURLOPT_USERNAME => $gmail,
        CURLOPT_PASSWORD => str_replace(' ', '', $appPassword),

        CURLOPT_MAIL_FROM => $gmail,
        CURLOPT_MAIL_RCPT => [$to],

        CURLOPT_UPLOAD => true,
        CURLOPT_INFILESIZE => strlen($email),

        CURLOPT_READFUNCTION => function (
            $ch,
            $fd,
            $length
        ) use (&$email, &$position) {

            $data = substr($email, $position, $length);

            $position += strlen($data);

            return $data;
        },

        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,

    ]);

    $result = curl_exec($ch);

    $curlError = curl_error($ch);
    $smtpResponseCode = curl_getinfo(
        $ch,
        CURLINFO_RESPONSE_CODE
    );

    curl_close($ch);

    // Check whether cURL completed successfully.
    if ($result === false) {

        error_log(
            'QueueFlow SMTP cURL error: ' . $curlError
        );

        return false;
    }

    // Gmail should accept the message with SMTP response 250.
    if ($smtpResponseCode !== 250) {

        error_log(
            'QueueFlow SMTP rejected email. Response code: ' .
            $smtpResponseCode
        );

        return false;
    }

    return true;
}

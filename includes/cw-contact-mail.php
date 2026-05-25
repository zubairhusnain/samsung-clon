<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

function cw_contact_mail_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $defaults = require __DIR__ . '/contact-mail-config.php';
    $local = __DIR__ . '/contact-mail.local.php';
    if (is_file($local)) {
        $overrides = require $local;
        if (is_array($overrides)) {
            $defaults = array_replace_recursive($defaults, $overrides);
        }
    }

    $config = $defaults;
    return $config;
}

function cw_contact_send_mail(string $username, string $email, string $subject, string $message): array
{
    $config = cw_contact_mail_config();
    $to = (string)($config['recipient'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Contact form is not configured (invalid recipient).'];
    }

    $fromEmail = (string)($config['from_email'] ?? '');
    $fromName = (string)($config['from_name'] ?? 'Samsung Pakistan Contact Form');

    $safeName = trim($username);
    $safeEmail = trim($email);
    $safeSubject = str_replace(["\r", "\n"], '', trim($subject));
    $safeMessage = trim($message);

    $mailSubject = 'Contact: ' . $safeSubject;
    $body = "Contact form submission (Samsung Pakistan)\n\n"
        . "Name: {$safeName}\n"
        . "Email: {$safeEmail}\n"
        . "Subject: {$safeSubject}\n\n"
        . "Message:\n{$safeMessage}\n";

    if (cw_contact_smtp_ready($config)) {
        $smtpResult = cw_contact_send_smtp(
            $config,
            $to,
            $fromEmail,
            $fromName,
            $mailSubject,
            $body,
            $safeName,
            $safeEmail
        );
        if ($smtpResult['ok']) {
            return $smtpResult;
        }
        if (empty($config['fallback_mail'])) {
            return $smtpResult;
        }
    }

    return cw_contact_send_php_mail($to, $fromEmail, $fromName, $mailSubject, $body, $safeName, $safeEmail);
}

function cw_contact_smtp_ready(array $config): bool
{
    $smtp = $config['smtp'] ?? [];
    if (empty($smtp['enabled'])) {
        return false;
    }

    $user = trim((string)($smtp['username'] ?? ''));
    $pass = (string)($smtp['password'] ?? '');

    return $user !== '' && $pass !== '';
}

function cw_contact_send_smtp(
    array $config,
    string $to,
    string $fromEmail,
    string $fromName,
    string $subject,
    string $body,
    string $replyName,
    string $replyEmail
): array {
    $smtp = $config['smtp'] ?? [];
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return [
            'ok' => false,
            'message' => 'SMTP is configured but PHPMailer is missing. Run: composer install in the site root.',
        ];
    }

    require_once $autoload;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = (string)($smtp['host'] ?? 'smtp.gmail.com');
        $mail->Port = (int)($smtp['port'] ?? 587);
        $mail->SMTPAuth = true;
        $mail->Username = (string)$smtp['username'];
        $mail->Password = (string)$smtp['password'];
        $mail->CharSet = PHPMailer::CHARSET_UTF8;

        $encryption = strtolower((string)($smtp['encryption'] ?? 'tls'));
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = (string)$smtp['username'];
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->addReplyTo($replyEmail, $replyName);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        $mail->send();

        return ['ok' => true, 'message' => 'Thank you. Your message has been sent.'];
    } catch (MailerException $e) {
        return [
            'ok' => false,
            'message' => 'Could not send email (SMTP). Check includes/contact-mail.local.php — ' . $mail->ErrorInfo,
        ];
    }
}

function cw_contact_send_php_mail(
    string $to,
    string $fromEmail,
    string $fromName,
    string $subject,
    string $body,
    string $replyName,
    string $replyEmail
): array {
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = preg_replace('/[^a-z0-9.-]/i', '', $host) ?: 'localhost';
        $fromEmail = 'noreply@' . $host;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . cw_contact_encode_address($fromName, $fromEmail),
        'Reply-To: ' . cw_contact_encode_address($replyName, $replyEmail),
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $headerString = implode("\r\n", $headers);
    $body = str_replace(["\r\n", "\r"], "\n", $body);

    $envelopeFrom = filter_var($fromEmail, FILTER_VALIDATE_EMAIL);
    $params = is_string($envelopeFrom) ? ('-f' . $envelopeFrom) : '';

    $ok = $params !== ''
        ? @mail($to, $subject, $body, $headerString, $params)
        : @mail($to, $subject, $body, $headerString);

    if ($ok) {
        return ['ok' => true, 'message' => 'Thank you. Your message has been sent.'];
    }

    return [
        'ok' => false,
        'message' => 'Could not send email. Configure SMTP in includes/contact-mail.local.php or enable mail() on the server.',
    ];
}

function cw_contact_encode_address(string $name, string $email): string
{
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    if (!is_string($email) || $email === '') {
        return 'noreply@localhost';
    }
    $name = str_replace(['"', "\r", "\n"], '', $name);
    if ($name === '') {
        return $email;
    }
    return '"' . $name . '" <' . $email . '>';
}

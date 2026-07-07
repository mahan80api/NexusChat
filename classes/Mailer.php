<?php
/**
 * Mailer — minimal email sending with fallback
 *
 * Uses PHP mail() by default, can be replaced with PHPMailer/Symfony Mailer
 * by setting MAIL_DRIVER in config.
 */
declare(strict_types=1);

class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody, ?string $fromName = null, ?string $fromEmail = null): bool
    {
        $fromEmail = $fromEmail ?? (defined('MAIL_FROM')    ? MAIL_FROM    : 'noreply@nexuschat.app');
        $fromName  = $fromName  ?? (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'NexusChat');
        $boundary  = md5(uniqid((string)time()));

        $headers   = [];
        $headers[] = "From: $fromName <$fromEmail>";
        $headers[] = "Reply-To: $fromEmail";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "X-Mailer: NexusChat/" . (defined('APP_VERSION') ? APP_VERSION : '1.0');

        // Log every send (in dev)
        if (defined('APP_ENV') && APP_ENV !== 'production') {
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
            $log = $logDir . '/mail.log';
            @file_put_contents($log, "[" . date('Y-m-d H:i:s') . "] To: $to | Subject: $subject\n", FILE_APPEND);
        }

        $driver = defined('MAIL_DRIVER') ? MAIL_DRIVER : 'php';
        if ($driver === 'log') {
            // dry-run, no actual send
            return true;
        }
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, implode("\r\n", $headers));
    }
}

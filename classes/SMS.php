<?php
/**
 * SMS — multi-driver SMS sending
 *
 * Drivers (set SMS_DRIVER in config):
 *  - 'log'         → just write to logs/sms.log (default, dev)
 *  - 'kavenegar'   → Kaveh Negar (Iran)
 *  - 'ghasedak'    → Ghasedak (Iran)
 *  - 'melipayamak' → Meli Payamak (Iran)
 *  - 'twilio'      → Twilio (global)
 *  - 'curl'        → generic HTTP API (config: SMS_API_URL, SMS_API_KEY, SMS_API_TEMPLATE)
 */
declare(strict_types=1);

class SMS
{
    public static function send(string $phoneE164, string $message): bool
    {
        $driver = defined('SMS_DRIVER') ? SMS_DRIVER : 'log';

        // log to file in dev
        if (defined('APP_ENV') && APP_ENV !== 'production') {
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
            @file_put_contents($logDir . '/sms.log',
                '[' . date('Y-m-d H:i:s') . "] To: $phoneE164\nMessage: $message\n\n", FILE_APPEND);
        }

        switch ($driver) {
            case 'log':       return true;
            case 'kavenegar': return self::kavenegar($phoneE164, $message);
            case 'ghasedak':  return self::ghasedak($phoneE164, $message);
            case 'melipayamak': return self::melipayamak($phoneE164, $message);
            case 'twilio':    return self::twilio($phoneE164, $message);
            case 'curl':      return self::curl($phoneE164, $message);
            default:          return false;
        }
    }

    private static function kavenegar(string $phone, string $msg): bool {
        $apiKey = defined('KAVENEGAR_API_KEY') ? KAVENEGAR_API_KEY : '';
        if (!$apiKey) return false;
        $url = "https://api.kavenegar.com/v1/$apiKey/sms/send.json";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['receptor' => $phone, 'message' => $msg]),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $r = curl_exec($ch);
        $d = json_decode($r, true);
        return ($d['return']['status'] ?? 0) == 200;
    }

    private static function ghasedak(string $phone, string $msg): bool {
        $apiKey = defined('GHASEDAK_API_KEY') ? GHASEDAK_API_KEY : '';
        if (!$apiKey) return false;
        $ch = curl_init('https://api.ghasedak.me/v2/sms/send/simple');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['apikey: ' . $apiKey, 'Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['receptor' => $phone, 'message' => $msg, 'linenumber' => defined('GHASEDAK_LINE') ? GHASEDAK_LINE : '10008566']),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $r = curl_exec($ch);
        $d = json_decode($r, true);
        return ($d['result']['code'] ?? 0) == 200;
    }

    private static function melipayamak(string $phone, string $msg): bool {
        $user = defined('MELIPAYAMAK_USER') ? MELIPAYAMAK_USER : '';
        $pass = defined('MELIPAYAMAK_PASS') ? MELIPAYAMAK_PASS : '';
        $from = defined('MELIPAYAMAK_FROM') ? MELIPAYAMAK_FROM : '';
        if (!$user || !$pass) return false;
        $url = "https://rest.payamak-panel.com/api/SendSMS/SendSMS";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['username' => $user, 'password' => $pass, 'to' => $phone, 'from' => $from, 'text' => $msg]),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $r = curl_exec($ch);
        $d = json_decode($r, true);
        return ($d['RetStatus'] ?? 0) == 1;
    }

    private static function twilio(string $phone, string $msg): bool {
        $sid   = defined('TWILIO_SID')   ? TWILIO_SID   : '';
        $token = defined('TWILIO_TOKEN') ? TWILIO_TOKEN : '';
        $from  = defined('TWILIO_FROM')  ? TWILIO_FROM  : '';
        if (!$sid || !$token || !$from) return false;
        $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => "$sid:$token",
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['To' => $phone, 'From' => $from, 'Body' => $msg]),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $r = curl_exec($ch);
        $d = json_decode($r, true);
        return isset($d['sid']);
    }

    private static function curl(string $phone, string $msg): bool {
        $url  = defined('SMS_API_URL') ? SMS_API_URL : '';
        $key  = defined('SMS_API_KEY') ? SMS_API_KEY : '';
        if (!$url || !$key) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['phone' => $phone, 'message' => $msg]),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $r = curl_exec($ch);
        $d = json_decode($r, true);
        return ($d['ok'] ?? false) === true;
    }
}

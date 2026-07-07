<?php
/**
 * NexusChat - Bot System Manager
 * Handles bot registration, commands, hooks, inline queries
 */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/MessageManager.php';

class BotManager {
    private $db;
    private $mm;
    public const COMMAND_PREFIX = '/';
    public const HOOKS = ['message', 'command', 'join', 'leave', 'new_chat_member', 'callback_query', 'inline_query'];

    public function __construct() {
        $this->db = Database::getInstance();
        $this->mm = new MessageManager();
    }

    /**
     * Create a new bot
     */
    public function createBot($ownerId, $name, $username, $description, $avatar = null) {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/', $username)) {
            throw new Exception('نام کاربری ربات نامعتبر است (۵-۳۲ کاراکتر، حروف و اعداد)');
        }
        $token = bin2hex(random_bytes(20));
        $stmt = $this->db->prepare("INSERT INTO bots
            (owner_id, name, username, description, avatar, token, is_public, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$ownerId, $name, $username, $description, $avatar, $token]);
        return ['bot_id' => $this->db->lastInsertId(), 'token' => $token];
    }

    public function getBotByToken($token) {
        $stmt = $this->db->prepare("SELECT * FROM bots WHERE token = ? AND is_active = 1");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getBotById($botId) {
        $stmt = $this->db->prepare("SELECT * FROM bots WHERE id = ?");
        $stmt->execute([$botId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getBotByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM bots WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listBots($ownerId) {
        $stmt = $this->db->prepare("SELECT id, name, username, description, avatar, is_public, created_at
            FROM bots WHERE owner_id = ? ORDER BY created_at DESC");
        $stmt->execute([$ownerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function browsePublicBots($limit = 50) {
        $stmt = $this->db->prepare("SELECT id, name, username, description, avatar, install_count
            FROM bots WHERE is_public = 1 AND is_active = 1
            ORDER BY install_count DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateBot($botId, $ownerId, $data) {
        $allowed = ['name', 'description', 'is_public', 'avatar'];
        $sets = []; $params = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $allowed)) { $sets[] = "$k = ?"; $params[] = $v; }
        }
        if (!$sets) return false;
        $params[] = $botId; $params[] = $ownerId;
        $sql = "UPDATE bots SET " . implode(',', $sets) . " WHERE id = ? AND owner_id = ?";
        $this->db->prepare($sql)->execute($params);
        return true;
    }

    public function deleteBot($botId, $ownerId) {
        $this->db->prepare("DELETE FROM bots WHERE id = ? AND owner_id = ?")->execute([$botId, $ownerId]);
        $this->db->prepare("DELETE FROM bot_commands WHERE bot_id = ?")->execute([$botId]);
        $this->db->prepare("DELETE FROM bot_installations WHERE bot_id = ?")->execute([$botId]);
    }

    public function regenerateToken($botId, $ownerId) {
        $token = bin2hex(random_bytes(20));
        $this->db->prepare("UPDATE bots SET token = ? WHERE id = ? AND owner_id = ?")
                 ->execute([$token, $botId, $ownerId]);
        return $token;
    }

    // ============ Commands ============
    public function addCommand($botId, $command, $description, $response, $isInline = false) {
        if ($command[0] !== '/') $command = '/' . $command;
        $stmt = $this->db->prepare("INSERT INTO bot_commands
            (bot_id, command, description, response, is_inline, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE description=VALUES(description), response=VALUES(response), is_inline=VALUES(is_inline)");
        $stmt->execute([$botId, $command, $description, json_encode($response), $isInline ? 1 : 0]);
        return $this->db->lastInsertId();
    }

    public function listCommands($botId) {
        $stmt = $this->db->prepare("SELECT * FROM bot_commands WHERE bot_id = ? ORDER BY command");
        $stmt->execute([$botId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) $r['response'] = json_decode($r['response'], true);
        return $rows;
    }

    public function deleteCommand($commandId, $botId) {
        $this->db->prepare("DELETE FROM bot_commands WHERE id = ? AND bot_id = ?")->execute([$commandId, $botId]);
    }

    // ============ Installations ============
    public function installBot($botId, $userId, $chatId = null) {
        $stmt = $this->db->prepare("INSERT IGNORE INTO bot_installations (bot_id, user_id, chat_id, installed_at)
            VALUES (?, ?, ?, NOW())");
        $stmt->execute([$botId, $userId, $chatId]);
        $this->db->prepare("UPDATE bots SET install_count = install_count + 1 WHERE id = ?")->execute([$botId]);
        return true;
    }

    public function uninstallBot($botId, $userId, $chatId = null) {
        $sql = "DELETE FROM bot_installations WHERE bot_id = ? AND user_id = ?";
        $params = [$botId, $userId];
        if ($chatId) { $sql .= " AND (chat_id = ? OR chat_id IS NULL)"; $params[] = $chatId; }
        $this->db->prepare($sql)->execute($params);
    }

    public function getInstalledBots($userId) {
        $stmt = $this->db->prepare("SELECT b.*, bi.chat_id, bi.installed_at FROM bots b
            JOIN bot_installations bi ON bi.bot_id = b.id
            WHERE bi.user_id = ? AND b.is_active = 1
            ORDER BY bi.installed_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getChatBots($chatId) {
        $stmt = $this->db->prepare("SELECT DISTINCT b.* FROM bots b
            JOIN bot_installations bi ON bi.bot_id = b.id
            WHERE (bi.chat_id = ? OR bi.chat_id IS NULL)
            AND b.is_active = 1");
        $stmt->execute([$chatId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============ Command execution ============
    public function processMessage($message) {
        $content = $message['content'] ?? '';
        if (!isset($content[0]) || $content[0] !== self::COMMAND_PREFIX) return [];

        // Parse command: /command@botname args
        $parts = explode(' ', $content, 2);
        $cmdPart = $parts[0];
        $argsText = $parts[1] ?? '';
        $args = $argsText === '' ? [] : preg_split('/\s+/', $argsText);

        [$command, $botUsername] = explode('@', $cmdPart) + [null, null];
        if (!$botUsername) {
            // Generic command (any installed bot can handle)
            $bots = $this->getChatBots($message['chat_id']);
        } else {
            $bot = $this->getBotByUsername($botUsername);
            $bots = $bot ? [$bot] : [];
        }

        $responses = [];
        foreach ($bots as $bot) {
            $cmd = $this->db->prepare("SELECT * FROM bot_commands WHERE bot_id = ? AND command = ?");
            $cmd->execute([$bot['id'], $command]);
            $cmdRow = $cmd->fetch(PDO::FETCH_ASSOC);
            if (!$cmdRow) continue;

            $response = json_decode($cmdRow['response'], true);
            $response = $this->renderResponse($response, $message, $args, $bot);

            // Bot replies
            $reply = $this->mm->sendBotMessage($bot['id'], $message['chat_id'], $response, $message['id']);
            $this->incrementStat($bot['id'], 'command_call');
            $responses[] = $reply;
        }
        return $responses;
    }

    private function renderResponse($response, $message, $args, $bot) {
        $vars = [
            '{user}'  => $message['sender_name'] ?? 'کاربر',
            '{user_id}' => $message['sender_id'] ?? 0,
            '{chat}'  => $message['chat_name'] ?? 'چت',
            '{arg1}'  => $args[0] ?? '',
            '{arg2}'  => $args[1] ?? '',
            '{args}'  => implode(' ', $args),
            '{bot}'   => $bot['name'],
            '{date}'  => date('Y-m-d'),
            '{time}'  => date('H:i:s'),
        ];
        if (isset($response['text'])) $response['text'] = strtr($response['text'], $vars);
        return $response;
    }

    // ============ Hooks ============
    public function fireHook($hookName, $context) {
        $stmt = $this->db->prepare("SELECT b.*, bh.config FROM bots b
            JOIN bot_hooks bh ON bh.bot_id = b.id
            WHERE bh.hook_name = ? AND b.is_active = 1");
        $stmt->execute([$hookName]);
        $bots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($bots as $bot) {
            $config = json_decode($bot['config'], true) ?? [];
            if (isset($config['chat_id']) && $config['chat_id'] != ($context['chat_id'] ?? null)) continue;
            $this->executeHookAction($bot, $hookName, $context);
        }
    }

    private function executeHookAction($bot, $hookName, $context) {
        // Default hook actions
        switch ($hookName) {
            case 'new_chat_member':
                $this->mm->sendBotMessage($bot['id'], $context['chat_id'],
                    ['text' => "به " . ($context['new_member_name'] ?? 'کاربر جدید') . " خوش آمدید! 👋"]);
                break;
            case 'message':
                // Auto-reply bots
                if (!empty($context['text']) && stripos($context['text'], 'سلام') !== false) {
                    $this->mm->sendBotMessage($bot['id'], $context['chat_id'],
                        ['text' => "سلام! من {$bot['name']} هستم. 🤖"]);
                }
                break;
        }
        $this->incrementStat($bot['id'], 'hook_fire');
    }

    // ============ Inline mode ============
    public function inlineQuery($botId, $query, $userId) {
        $stmt = $this->db->prepare("SELECT * FROM bot_commands WHERE bot_id = ? AND is_inline = 1");
        $stmt->execute([$botId]);
        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $cmd) {
            $response = json_decode($cmd['response'], true);
            if (stripos($cmd['command'], $query) !== false) {
                $results[] = [
                    'type' => 'article',
                    'id' => $cmd['id'],
                    'title' => $cmd['command'],
                    'description' => $cmd['description'],
                    'message_text' => $response['text'] ?? '',
                ];
            }
        }
        $this->incrementStat($botId, 'inline_query');
        return $results;
    }

    // ============ Stats & analytics ============
    private function incrementStat($botId, $metric, $value = 1) {
        $today = date('Y-m-d');
        $stmt = $this->db->prepare("INSERT INTO bot_stats (bot_id, date, metric, value)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE value = value + VALUES(value)");
        $stmt->execute([$botId, $today, $metric, $value]);
    }

    public function getStats($botId, $days = 30) {
        $stmt = $this->db->prepare("SELECT date, metric, SUM(value) as total
            FROM bot_stats WHERE bot_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY date, metric ORDER BY date ASC");
        $stmt->execute([$botId, $days]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats = ['command_call' => [], 'hook_fire' => [], 'inline_query' => [], 'message_sent' => []];
        foreach ($rows as $r) {
            if (isset($stats[$r['metric']])) $stats[$r['metric']][] = ['date' => $r['date'], 'value' => (int)$r['total']];
        }
        return $stats;
    }

    // ============ Built-in Bot Examples ============
    public function installBuiltinBots($userId) {
        $builtins = [
            [
                'name' => 'WeatherBot',
                'username' => 'weatherbot',
                'description' => 'آب و هوای شهرها',
                'commands' => [
                    ['/weather', 'آب و هوای یک شهر', ['text' => '🌤 آب و هوای {arg1}: آفتابی، ۲۴°C']],
                ],
            ],
            [
                'name' => 'TranslateBot',
                'username' => 'translatebot',
                'description' => 'ترجمه متن',
                'commands' => [
                    ['/translate', 'ترجمه متن', ['text' => '🔤 ترجمه: {args}']],
                ],
            ],
            [
                'name' => 'PollBot',
                'username' => 'pollbot',
                'description' => 'ساخت نظرسنجی سریع',
                'commands' => [
                    ['/poll', 'ساخت نظرسنجی', ['text' => '📊 نظرسنجی: {args}']],
                ],
            ],
            [
                'name' => 'WelcomeBot',
                'username' => 'welcomebot',
                'description' => 'خوش‌آمدگویی به اعضای جدید',
                'commands' => [],
            ],
            [
                'name' => 'MemeBot',
                'username' => 'memebot',
                'description' => 'ارسال میم و جوک',
                'commands' => [
                    ['/joke', 'یه جوک بگو', ['text' => '😂 ' . $this->getRandomJoke()]],
                    ['/quote', 'یه جمله الهام‌بخش', ['text' => '✨ ' . $this->getRandomQuote()]],
                ],
            ],
        ];
        foreach ($builtins as $bot) {
            try {
                $created = $this->createBot($userId, $bot['name'], $bot['username'], $bot['description']);
                foreach ($bot['commands'] as $cmd) {
                    $this->addCommand($created['bot_id'], $cmd[0], $cmd[1], $cmd[2]);
                }
            } catch (Exception $e) { /* skip duplicates */ }
        }
    }

    private function getRandomJoke() {
        $jokes = [
            'چرا برنامه‌نویس‌ها عینک می‌زنن؟ چون نمی‌تونن C# رو ببینن!',
            'یه باگ وارد بار می‌شه، بارون می‌کنه بیرون!',
            'چرا جاوا اسکریپت تنها نشسته؟ چون Node نداره!',
        ];
        return $jokes[array_rand($jokes)];
    }

    private function getRandomQuote() {
        $quotes = [
            'تنها راه انجام کار بزرگ، دوست داشتن کاری است که انجام می‌دهید. - استیو جابز',
            'زندگی چیزی نیست جز آنچه که ما فکر می‌کنیم. - رنه دکارت',
            'موفقیت یعنی از شکست به شکست بدون از دست دادن اشتیاق. - وینستون چرچیل',
        ];
        return $quotes[array_rand($quotes)];
    }

    // ============ Rate Limiting ============
    public function checkRateLimit($botId, $chatId, $maxPerMinute = 20) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as cnt FROM messages
            WHERE sender_id = (SELECT owner_id FROM bots WHERE id = ?)
            AND chat_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        $stmt->execute([$botId, $chatId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['cnt'] < $maxPerMinute;
    }
}

<?php
/**
 * NexusChat - Message Statistics Manager
 * Provides analytics about messages, chats, and user activity
 */
require_once __DIR__ . '/Database.php';

class StatsManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get overall stats for a user
     */
    public function getOverall($userId, $period = 'all') {
        $where = $this->buildUserWhere($userId, 'm', $period);
        $sql = "SELECT
                  COUNT(*) as total_messages,
                  COALESCE(SUM(LENGTH(m.content)), 0) as total_chars,
                  COALESCE(SUM(LENGTH(m.content) - LENGTH(REPLACE(m.content, ' ', '')) + 1), 0) as total_words,
                  SUM(m.type = 'text')    as text_count,
                  SUM(m.type = 'image')   as image_count,
                  SUM(m.type = 'video')   as video_count,
                  SUM(m.type = 'voice')   as voice_count,
                  SUM(m.type = 'file')    as file_count,
                  SUM(m.type = 'sticker') as sticker_count,
                  SUM(m.type = 'poll')    as poll_count,
                  COUNT(DISTINCT m.chat_id) as active_chats,
                  COUNT(DISTINCT DATE(m.created_at)) as active_days,
                  AVG(LENGTH(m.content)) as avg_message_length,
                  MAX(LENGTH(m.content)) as longest_message,
                  MIN(m.created_at) as first_message,
                  MAX(m.created_at) as last_message
                FROM messages m
                $where";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Sent vs received
        $sentWhere = $this->buildUserWhere($userId, 'm', $period) . " AND m.sender_id = :uid";
        $sent = $this->db->prepare("SELECT COUNT(*) FROM messages m $sentWhere");
        $sent->execute([':uid' => $userId]);
        $sentCount = $sent->fetchColumn();

        return [
            'total_messages'    => (int)$row['total_messages'],
            'total_chars'       => (int)$row['total_chars'],
            'total_words'       => (int)$row['total_words'],
            'sent_messages'     => (int)$sentCount,
            'received_messages' => (int)$row['total_messages'] - (int)$sentCount,
            'text_count'        => (int)$row['text_count'],
            'image_count'       => (int)$row['image_count'],
            'video_count'       => (int)$row['video_count'],
            'voice_count'       => (int)$row['voice_count'],
            'file_count'        => (int)$row['file_count'],
            'sticker_count'     => (int)$row['sticker_count'],
            'poll_count'        => (int)$row['poll_count'],
            'active_chats'      => (int)$row['active_chats'],
            'active_days'       => (int)$row['active_days'],
            'avg_message_length'=> (int)$row['avg_message_length'],
            'longest_message'   => (int)$row['longest_message'],
            'first_message'     => $row['first_message'],
            'last_message'      => $row['last_message'],
            'response_rate'     => $this->responseRate($userId, $period),
        ];
    }

    /**
     * Activity per hour of day (24h heatmap)
     */
    public function getHourlyActivity($userId, $period = 'all') {
        $where = $this->buildUserWhere($userId, 'm', $period);
        $sql = "SELECT HOUR(m.created_at) as hour, COUNT(*) as count
                FROM messages m $where
                GROUP BY HOUR(m.created_at)
                ORDER BY hour";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hours = array_fill(0, 24, 0);
        foreach ($rows as $r) $hours[(int)$r['hour']] = (int)$r['count'];
        return $hours;
    }

    /**
     * Activity per day of week
     */
    public function getWeekdayActivity($userId, $period = 'all') {
        $where = $this->buildUserWhere($userId, 'm', $period);
        $sql = "SELECT DAYOFWEEK(m.created_at) as dow, COUNT(*) as count
                FROM messages m $where
                GROUP BY DAYOFWEEK(m.created_at)
                ORDER BY dow";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $days = array_fill(0, 7, 0); // 1=Sunday ... 7=Saturday
        foreach ($rows as $r) $days[(int)$r['dow'] - 1] = (int)$r['count'];
        return $days;
    }

    /**
     * Daily activity for the last N days
     */
    public function getDailyActivity($userId, $days = 30) {
        $sql = "SELECT DATE(m.created_at) as day, COUNT(*) as count
                FROM messages m
                WHERE m.chat_id IN (SELECT chat_id FROM chat_members WHERE user_id = ?)
                  AND m.created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(m.created_at)
                ORDER BY day";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $days]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $r) $map[$r['day']] = (int)$r['count'];
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $result[] = ['date' => $d, 'count' => $map[$d] ?? 0, 'day_name' => $this->persianDayName($d)];
        }
        return $result;
    }

    /**
     * Heatmap for the last year (52 weeks × 7 days)
     */
    public function getYearHeatmap($userId) {
        $sql = "SELECT DATE(m.created_at) as day, COUNT(*) as count
                FROM messages m
                WHERE m.chat_id IN (SELECT chat_id FROM chat_members WHERE user_id = ?)
                  AND m.created_at > DATE_SUB(NOW(), INTERVAL 365 DAY)
                GROUP BY DATE(m.created_at)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $r) $map[$r['day']] = (int)$r['count'];
        $result = [];
        for ($i = 364; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $result[] = ['date' => $d, 'count' => $map[$d] ?? 0];
        }
        return $result;
    }

    /**
     * Top chats by activity
     */
    public function getTopChats($userId, $limit = 10, $period = 'all') {
        $where = $this->buildUserWhere($userId, 'm', $period);
        $sql = "SELECT
                  c.id, c.name, c.type, c.avatar,
                  COUNT(m.id) as message_count,
                  MAX(m.created_at) as last_message,
                  SUM(m.sender_id = ?) as my_messages
                FROM messages m
                JOIN chats c ON c.id = m.chat_id
                $where
                GROUP BY c.id, c.name, c.type, c.avatar
                ORDER BY message_count DESC
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Top people by message exchange
     */
    public function getTopPeople($userId, $limit = 10, $period = 'all') {
        $where = $this->buildUserWhere($userId, 'm', $period);
        $sql = "SELECT
                  u.id, u.username, u.display_name, u.avatar,
                  COUNT(m.id) as total,
                  SUM(m.sender_id = ?) as sent,
                  SUM(m.sender_id != ?) as received
                FROM messages m
                JOIN chats c ON c.id = m.chat_id
                JOIN chat_members cm ON cm.chat_id = c.id
                JOIN users u ON u.id = cm.user_id AND u.id != ?
                $where
                GROUP BY u.id, u.username, u.display_name, u.avatar
                ORDER BY total DESC
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Message type distribution
     */
    public function getTypeDistribution($userId, $period = 'all') {
        $where = $this->buildUserWhere($userId, 'm', $period);
        $sql = "SELECT m.type, COUNT(*) as count
                FROM messages m
                $where
                GROUP BY m.type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $types = ['text' => 0, 'image' => 0, 'video' => 0, 'voice' => 0, 'file' => 0, 'sticker' => 0, 'poll' => 0];
        foreach ($rows as $r) $types[$r['type']] = (int)$r['count'];
        return $types;
    }

    /**
     * Compare current period to previous
     */
    public function getComparison($userId) {
        $current = $this->countFor($userId, '7 DAY');
        $previous = $this->countFor($userId, '14 DAY') - $current;
        return [
            'current'   => $current,
            'previous'  => $previous,
            'change'    => $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0,
        ];
    }

    private function countFor($userId, $interval) {
        $sql = "SELECT COUNT(*) FROM messages m
                WHERE m.chat_id IN (SELECT chat_id FROM chat_members WHERE user_id = ?)
                  AND m.created_at > DATE_SUB(NOW(), INTERVAL $interval)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Peak hours with most activity
     */
    public function getPeakHours($userId, $period = 'all') {
        $hourly = $this->getHourlyActivity($userId, $period);
        arsort($hourly);
        $top = array_slice($hourly, 0, 3, true);
        $peaks = [];
        foreach ($top as $hour => $count) {
            $peaks[] = ['hour' => (int)$hour, 'count' => $count, 'label' => sprintf('%02d:00 - %02d:00', $hour, ($hour+1) % 24)];
        }
        return $peaks;
    }

    /**
     * Streak and records
     */
    public function getRecords($userId) {
        $sql = "SELECT DATE(m.created_at) as day, COUNT(*) as count
                FROM messages m
                WHERE m.chat_id IN (SELECT chat_id FROM chat_members WHERE user_id = ?)
                GROUP BY DATE(m.created_at)
                ORDER BY count DESC
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $busiestDay = $stmt->fetch(PDO::FETCH_ASSOC);

        $sql2 = "SELECT m.content, m.created_at, u.display_name
                 FROM messages m
                 JOIN users u ON u.id = m.sender_id
                 WHERE m.chat_id IN (SELECT chat_id FROM chat_members WHERE user_id = ?)
                   AND m.content IS NOT NULL
                 ORDER BY LENGTH(m.content) DESC
                 LIMIT 1";
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute([$userId]);
        $longest = $stmt2->fetch(PDO::FETCH_ASSOC);

        $sql3 = "SELECT DISTINCT DATE(m.created_at) as day
                 FROM messages m
                 WHERE m.chat_id IN (SELECT chat_id FROM chat_members WHERE user_id = ?)
                 ORDER BY day DESC";
        $stmt3 = $this->db->prepare($sql3);
        $stmt3->execute([$userId]);
        $days = $stmt3->fetchAll(PDO::FETCH_COLUMN);
        $streak = 0;
        $today = date('Y-m-d');
        foreach ($days as $i => $d) {
            $expected = date('Y-m-d', strtotime("-$i days"));
            if ($d === $expected) $streak++;
            else break;
        }

        return [
            'busiest_day'    => $busiestDay,
            'longest_msg'    => $longest ? ['length' => strlen($longest['content']), 'preview' => mb_substr($longest['content'], 0, 60), 'sender' => $longest['display_name'], 'date' => $longest['created_at']] : null,
            'current_streak' => $streak,
        ];
    }

    /**
     * Most used words (with stopword filter)
     */
    public function getTopWords($userId, $limit = 20, $period = 'all') {
        $where = $this->buildUserWhere($userId, 'm', $period);
        $sql = "SELECT m.content FROM messages m $where AND m.content IS NOT NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stop = $this->stopwords();
        $counts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $text = mb_strtolower($row['content']);
            $text = preg_replace('/[^\p{L}\s]/u', ' ', $text);
            $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($words as $w) {
                if (mb_strlen($w) < 3 || in_array($w, $stop)) continue;
                $counts[$w] = ($counts[$w] ?? 0) + 1;
            }
        }
        arsort($counts);
        return array_slice($counts, 0, $limit, true);
    }

    /**
     * Most used emoji
     */
    public function getTopEmoji($userId, $limit = 10, $period = 'all') {
        $where = $this->buildUserWhere($userId, 'm', $period);
        $sql = "SELECT m.content FROM messages m $where AND m.content IS NOT NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $counts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            preg_match_all('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}\x{1F000}-\x{1F02F}\x{1F100}-\x{1F1FF}\x{1F200}-\x{1F2FF}\x{1FA00}-\x{1FAFF}]/u', $row['content'], $matches);
            foreach ($matches[0] as $e) $counts[$e] = ($counts[$e] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice($counts, 0, $limit, true);
    }

    /**
     * Export as CSV
     */
    public function exportCSV($userId, $period = 'all') {
        $where = $this->buildUserWhere($userId, 'm', $period);
        $sql = "SELECT m.id, m.chat_id, c.name as chat_name, m.type, m.content, m.created_at, m.is_edited
                FROM messages m
                JOIN chats c ON c.id = m.chat_id
                $where
                ORDER BY m.created_at DESC
                LIMIT 10000";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, ['ID', 'Chat', 'Type', 'Content', 'Date', 'Edited']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($fp, [$row['id'], $row['chat_name'], $row['type'], $row['content'], $row['created_at'], $row['is_edited']]);
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }

    // ===== Helpers =====
    private function buildUserWhere($userId, $alias, $period) {
        $parts = ["$alias.chat_id IN (SELECT chat_id FROM chat_members WHERE user_id = ?)"];
        $params = [$userId];
        if ($period === 'day')   $parts[] = "$alias.created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)";
        if ($period === 'week')  $parts[] = "$alias.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
        if ($period === 'month') $parts[] = "$alias.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        if ($period === 'year')  $parts[] = "$alias.created_at > DATE_SUB(NOW(), INTERVAL 365 DAY)";
        return 'WHERE ' . implode(' AND ', $parts);
    }

    private function responseRate($userId, $period) {
        $where = $this->buildUserWhere($userId, 'm', $period);
        $sql = "SELECT
                  COUNT(DISTINCT m.chat_id) as chats,
                  SUM(m.sender_id = ?) as my_msgs
                FROM messages m $where";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r['chats'] > 0 ? round(($r['my_msgs'] / max(1, $r['chats'])), 1) : 0;
    }

    private function persianDayName($date) {
        $names = ['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنج‌شنبه','جمعه','شنبه'];
        return $names[(int)date('w', strtotime($date))];
    }

    private function stopwords(): array {
        return ['از','به','در','با','که','این','آن','یک','هم','های','را','برای','ای','می','بود','است','باشد','شده','شده‌اند','کرد','کرده','بوده','دارد','دارند','خواهد','شود','می‌شود','ها','هایی','یکی','دیگر','دیگری','همه','هر','هرچه','همچنین','یعنی','اگر','چه','چون','تا','پس','نیز','همچنین','البته','ولی','اما','ولیکن','گرچه','هرگاه','زیرا','چرا','چگونه','کجا','کی','کسی','چیزی','کسی','هیچ'];
    }
}

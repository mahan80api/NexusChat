#!/usr/bin/env php
<?php
/**
 * NexusChat - WebSocket Server
 * Real-time signaling for calls, presence, and message delivery
 *
 * Run with: php api/websocket.php
 * Or use a process manager like Supervisor
 */
require_once __DIR__ . '/../config/config.php';

class CallSignalingServer {
    private $clients = [];
    private $userConnections = [];  // userId => [connectionId, ...]
    private $activeCalls = [];      // callId => [userId, ...]
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function start($host = '0.0.0.0', $port = 8080) {
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($server, $host, $port);
        socket_listen($server);
        socket_set_nonblock($server);

        echo "🟢 WebSocket server started on $host:$port\n";

        while (true) {
            $read = array_map(fn($c) => $c['socket'], $this->clients);
            $read[] = $server;
            socket_select($read, $write, $except, null);

            if (in_array($server, $read)) {
                $this->acceptConnection($server);
                $key = array_search($server, $read);
                unset($read[$key]);
            }

            foreach ($read as $sock) {
                $this->handleClient($sock);
            }
        }
    }

    private function acceptConnection($server) {
        $client = socket_accept($server);
        if (!$client) return;
        $handshake = $this->performHandshake($client);
        if (!$handshake) {
            socket_close($client);
            return;
        }
        $cid = uniqid('conn_', true);
        $this->clients[$cid] = [
            'socket'  => $client,
            'user_id' => null,
            'handshake' => true,
        ];
        echo "✅ Client connected: $cid\n";
    }

    private function performHandshake($client) {
        $request = socket_read($client, 4096);
        if (preg_match('/Sec-WebSocket-Key: (.+)\r\n/', $request, $matches)) {
            $accept = base64_encode(sha1(trim($matches[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
                       "Upgrade: websocket\r\n" .
                       "Connection: Upgrade\r\n" .
                       "Sec-WebSocket-Accept: $accept\r\n\r\n";
            socket_write($client, $upgrade);
            return true;
        }
        return false;
    }

    private function handleClient($sock) {
        $cid = array_search($sock, array_column($this->clients, 'socket'));
        if ($cid === false) return;
        $data = socket_read($sock, 8192);
        if (!$data) {
            $this->disconnect($cid);
            return;
        }
        $frame = $this->decodeFrame($data);
        if (!$frame) return;
        $msg = json_decode($frame['payload'], true);
        if (!$msg) return;
        $this->routeMessage($cid, $msg);
    }

    private function routeMessage($cid, $msg) {
        $conn = &$this->clients[$cid];
        switch ($msg['type'] ?? '') {
            case 'auth':
                $conn['user_id'] = (int)$msg['user_id'];
                $this->userConnections[$conn['user_id']][$cid] = true;
                $this->send($cid, ['type' => 'auth_ok']);
                $this->broadcastPresence('online', $conn['user_id']);
                break;
            case 'signal':
                $this->forwardSignal($msg);
                break;
            case 'call_invite':
                $this->inviteToCall($msg);
                break;
            case 'call_state':
                $this->broadcastCallState($msg);
                break;
            case 'ping':
                $this->send($cid, ['type' => 'pong']);
                break;
        }
    }

    private function forwardSignal($msg) {
        $to = (int)($msg['to_user_id'] ?? 0);
        if (!isset($this->userConnections[$to])) return;
        foreach (array_keys($this->userConnections[$to]) as $cid) {
            $this->send($cid, $msg);
        }
    }

    private function inviteToCall($msg) {
        $this->activeCalls[$msg['call_id']] = $msg['user_ids'] ?? [];
        $this->forwardSignal($msg);
    }

    private function broadcastCallState($msg) {
        $callId = $msg['call_id'] ?? null;
        if (!$callId || !isset($this->activeCalls[$callId])) return;
        foreach ($this->activeCalls[$callId] as $uid) {
            if (isset($this->userConnections[$uid])) {
                foreach (array_keys($this->userConnections[$uid]) as $cid) {
                    $this->send($cid, $msg);
                }
            }
        }
        if (($msg['state'] ?? '') === 'ended') unset($this->activeCalls[$callId]);
    }

    private function broadcastPresence($status, $userId) {
        // Notify friends about online status
        $stmt = $this->db->prepare("SELECT user_a FROM friendships WHERE user_b = ? AND status = 'accepted'
                                    UNION SELECT user_b FROM friendships WHERE user_a = ? AND status = 'accepted'");
        $stmt->execute([$userId, $userId]);
        $friends = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($friends as $friend) {
            if (isset($this->userConnections[$friend])) {
                foreach (array_keys($this->userConnections[$friend]) as $cid) {
                    $this->send($cid, ['type' => 'presence', 'user_id' => $userId, 'status' => $status]);
                }
            }
        }
    }

    private function disconnect($cid) {
        $conn = $this->clients[$cid] ?? null;
        if ($conn) {
            socket_close($conn['socket']);
            if ($conn['user_id']) {
                unset($this->userConnections[$conn['user_id']][$cid]);
                if (empty($this->userConnections[$conn['user_id']])) {
                    unset($this->userConnections[$conn['user_id']]);
                    $this->broadcastPresence('offline', $conn['user_id']);
                }
            }
            unset($this->clients[$cid]);
            echo "❌ Client disconnected: $cid\n";
        }
    }

    private function send($cid, $msg) {
        $conn = $this->clients[$cid] ?? null;
        if (!$conn) return;
        $payload = json_encode($msg, JSON_UNESCAPED_UNICODE);
        $frame = $this->encodeFrame($payload);
        @socket_write($conn['socket'], $frame);
    }

    private function encodeFrame($payload, $opcode = 0x1) {
        $payloadLen = strlen($payload);
        $frame = chr(0x80 | $opcode);
        if ($payloadLen < 126) {
            $frame .= chr($payloadLen);
        } elseif ($payloadLen < 65536) {
            $frame .= chr(126) . pack('n', $payloadLen);
        } else {
            $frame .= chr(127) . pack('J', $payloadLen);
        }
        $frame .= $payload;
        return $frame;
    }

    private function decodeFrame($data) {
        if (strlen($data) < 2) return null;
        $byte1 = ord($data[0]);
        $byte2 = ord($data[1]);
        $opcode = $byte1 & 0x0F;
        $masked = ($byte2 & 0x80) !== 0;
        $len = $byte2 & 0x7F;
        $offset = 2;
        if ($len === 126) {
            $len = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
        } elseif ($len === 127) {
            $len = unpack('J', substr($data, $offset, 8))[1];
            $offset += 8;
        }
        $mask = '';
        if ($masked) {
            $mask = substr($data, $offset, 4);
            $offset += 4;
        }
        $payload = substr($data, $offset, $len);
        if ($masked) {
            for ($i = 0; $i < strlen($payload); $i++) {
                $payload[$i] = chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
            }
        }
        return ['opcode' => $opcode, 'payload' => $payload];
    }
}

(new CallSignalingServer())->start('0.0.0.0', 8080);

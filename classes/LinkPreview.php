<?php
/**
 * NexusChat - Link Preview Fetcher
 * Extracts OG meta tags and special embeds
 */
class LinkPreview {
    private $db;
    private $cache_ttl = 86400; // 24h
    private $userAgent = 'Mozilla/5.0 (compatible; NexusChatBot/1.0; +https://nexuschat.app)';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Extract all URLs from text
     */
    public function extractUrls($text) {
        $pattern = '/(https?:\/\/[^\s<>"\'\\)]+)/i';
        preg_match_all($pattern, $text, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Get or fetch preview
     */
    public function getPreview($url) {
        $url = trim($url);
        if (!$this->isValidUrl($url)) return ['error' => 'invalid_url'];

        $urlHash = hash('sha256', $this->normalizeUrl($url));

        // Check cache
        $cached = $this->getCached($urlHash);
        if ($cached) {
            $cached['from_cache'] = true;
            return $cached;
        }

        // Fetch new
        $preview = $this->fetchPreview($url);
        if (!isset($preview['error'])) {
            $this->savePreview($urlHash, $url, $preview);
        }
        $preview['from_cache'] = false;
        return $preview;
    }

    /**
     * Normalize URL (remove tracking params, etc.)
     */
    public function normalizeUrl($url) {
        $url = trim($url);
        $parts = parse_url($url);
        if (!$parts || !isset($parts['host'])) return $url;

        $scheme = $parts['scheme'] ?? 'https';
        $host   = strtolower($parts['host']);
        $path   = $parts['path'] ?? '';
        $query  = [];

        // Remove tracking params
        $trackingParams = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content','fbclid','gclid','ref','ref_src','_hsenc','_hsmi','mc_cid','mc_eid'];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            $query = array_diff_key($query, array_flip($trackingParams));
        }
        $queryStr = $query ? '?' . http_build_query($query) : '';
        return "$scheme://$host$path$queryStr";
    }

    public function isValidUrl($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) return false;
        // Block private IPs / file://
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return false;
        if (preg_match('/^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|::1|fc00:|fe80:)/i', $host)) return false;
        return true;
    }

    /**
     * Fetch preview using appropriate strategy
     */
    private function fetchPreview($url) {
        $special = $this->detectSpecialSite($url);
        if ($special) {
            return $this->fetchSpecial($url, $special);
        }
        return $this->fetchOG($url);
    }

    private function detectSpecialSite($url) {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $stmt = $this->db->query("SELECT * FROM special_embedded_sites WHERE is_active = 1");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (preg_match('/' . $row['host_pattern'] . '/i', $host)) {
                return $row;
            }
        }
        return null;
    }

    private function fetchSpecial($url, $site) {
        $handler = $site['custom_handler'];
        if ($handler && method_exists($this, 'fetch_' . $handler)) {
            return call_user_func([$this, 'fetch_' . $handler], $url, $site);
        }
        // Fallback: OG tags
        $og = $this->fetchOG($url);
        if (!isset($og['error'])) {
            $og['embed_type'] = $site['embed_type'];
            $og['site_name']  = $site['name'];
            $og['site_icon']  = $site['icon'];
        }
        return $og;
    }

    /**
     * YouTube-specific
     */
    private function fetch_youtube($url, $site) {
        $videoId = null;
        if (preg_match('/youtube\.com\/watch\?v=([\w-]+)/', $url, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('/youtu\.be\/([\w-]+)/', $url, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('/youtube\.com\/embed\/([\w-]+)/', $url, $m)) {
            $videoId = $m[1];
        }
        if (!$videoId) return ['error' => 'invalid_youtube_url'];

        $oembed = "https://www.youtube.com/oembed?url=" . urlencode($url) . "&format=json";
        $data = $this->httpGet($oembed);
        if ($data) {
            $info = json_decode($data, true);
            if ($info) {
                return [
                    'type'        => 'video',
                    'site_name'   => 'YouTube',
                    'site_icon'   => '▶️',
                    'title'       => $info['title'] ?? '',
                    'author'      => $info['author_name'] ?? '',
                    'image_url'   => "https://i.ytimg.com/vi/$videoId/maxresdefault.jpg",
                    'embed_type'  => 'video',
                    'embed_html'  => "<iframe src='https://www.youtube.com/embed/$videoId' frameborder='0' allowfullscreen></iframe>",
                    'thumbnail'   => "https://i.ytimg.com/vi/$videoId/hqdefault.jpg",
                ];
            }
        }
        return [
            'type'       => 'video',
            'site_name'  => 'YouTube',
            'site_icon'  => '▶️',
            'title'      => 'ویدیوی YouTube',
            'image_url'  => "https://i.ytimg.com/vi/$videoId/maxresdefault.jpg",
            'embed_type' => 'video',
            'embed_html' => "<iframe src='https://www.youtube.com/embed/$videoId' frameborder='0' allowfullscreen></iframe>",
        ];
    }

    /**
     * Aparat-specific
     */
    private function fetch_aparat($url, $site) {
        $videoId = null;
        if (preg_match('/aparat\.com\/v\/([\w-]+)/', $url, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('/aparat\.com\/([\w-]+)/', $url, $m)) {
            $videoId = $m[1];
        }
        if (!$videoId) return ['error' => 'invalid_aparat_url'];

        $oembed = "https://www.aparat.com/oembed?url=" . urlencode($url) . "&format=json";
        $data = $this->httpGet($oembed);
        if ($data) {
            $info = json_decode($data, true);
            if ($info) {
                return [
                    'type'       => 'video',
                    'site_name'  => 'Aparat',
                    'site_icon'  => '🎬',
                    'title'      => $info['title'] ?? '',
                    'image_url'  => $info['thumbnail_url'] ?? '',
                    'embed_type' => 'video',
                    'embed_html' => "<iframe src='https://www.aparat.com/video/video/embed/videohash/$videoId/vt/frame' allowFullScreen='true' webkitallowfullscreen='true' mozallowfullscreen='true'></iframe>",
                ];
            }
        }
        return ['error' => 'aparat_fetch_failed'];
    }

    /**
     * Twitter / X
     */
    private function fetch_twitter($url, $site) {
        $og = $this->fetchOG($url);
        if (!isset($og['error'])) {
            $og['embed_type'] = 'iframe';
            $og['site_name']  = 'Twitter/X';
            $og['site_icon']  = '🐦';
            $og['embed_html'] = $this->buildTwitterEmbed($url);
        }
        return $og;
    }

    private function buildTwitterEmbed($url) {
        // Twitter publishes an embed widget
        return '<blockquote class="twitter-tweet"><a href="' . htmlspecialchars($url) . '">View on Twitter</a></blockquote>';
    }

    /**
     * Instagram
     */
    private function fetch_instagram($url, $site) {
        $og = $this->fetchOG($url);
        if (!isset($og['error'])) {
            $og['embed_type'] = 'iframe';
            $og['site_name']  = 'Instagram';
            $og['site_icon']  = '📷';
        }
        return $og;
    }

    /**
     * GitHub
     */
    private function fetch_github($url, $site) {
        $og = $this->fetchOG($url);
        if (!isset($og['error'])) {
            $og['embed_type'] = 'card';
            $og['site_name']  = 'GitHub';
            $og['site_icon']  = '🐙';
            // Extract repo info
            if (preg_match('#github\.com/([^/]+)/([^/]+)#', $url, $m)) {
                $og['author'] = $m[1];
                $og['title']  = $og['title'] ?: $m[1] . '/' . $m[2];
                // Could fetch repo stats via API
            }
        }
        return $og;
    }

    /**
     * Generic OG tag fetcher
     */
    private function fetchOG($url) {
        $html = $this->httpGet($url);
        if (!$html) return ['error' => 'fetch_failed', 'http_status' => 0];

        $og = [
            'type'        => 'website',
            'title'       => '',
            'description' => '',
            'image_url'   => '',
            'site_name'   => '',
            'favicon_url' => '',
            'locale'      => '',
        ];

        // Extract OG tags
        preg_match_all('/<meta[^>]+(?:property|name)="og:([^"]+)"[^>]+content="([^"]*)"[^>]*>/i', $html, $ogMatches, PREG_SET_ORDER);
        foreach ($ogMatches as $m) {
            $key = str_replace(':', '_', $m[1]);
            if (array_key_exists($key, $og)) {
                $og[$key] = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            }
        }
        // Also handle content first
        preg_match_all('/<meta[^>]+content="([^"]+)"[^>]+(?:property|name)="og:([^"]+)"[^>]*>/i', $html, $ogMatches2, PREG_SET_ORDER);
        foreach ($ogMatches2 as $m) {
            $key = str_replace(':', '_', $m[2]);
            if (array_key_exists($key, $og) && empty($og[$key])) {
                $og[$key] = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            }
        }

        // Fallback: <title> tag
        if (empty($og['title']) && preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            $og['title'] = trim($m[1]);
        }
        // Fallback: meta description
        if (empty($og['description']) && preg_match('/<meta[^>]+name="description"[^>]+content="([^"]+)"/i', $html, $m)) {
            $og['description'] = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }
        if (empty($og['description']) && preg_match('/<meta[^>]+content="([^"]+)"[^>]+name="description"[^>]*>/i', $html, $m)) {
            $og['description'] = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }

        // Favicon
        if (preg_match('/<link[^>]+rel="(?:shortcut )?icon"[^>]+href="([^"]+)"/i', $html, $m)) {
            $favicon = $m[1];
            if (!preg_match('/^https?:\/\//', $favicon)) {
                $base = parse_url($url);
                $favicon = $base['scheme'] . '://' . $base['host'] . '/' . ltrim($favicon, '/');
            }
            $og['favicon_url'] = $favicon;
        } elseif (empty($og['favicon_url'])) {
            $base = parse_url($url);
            $og['favicon_url'] = $base['scheme'] . '://' . $base['host'] . '/favicon.ico';
        }

        // Truncate
        $og['title']       = mb_substr($og['title'] ?? '', 0, 200);
        $og['description'] = mb_substr($og['description'] ?? '', 0, 500);
        $og['site_name']   = $og['site_name'] ?: (parse_url($url, PHP_URL_HOST) ?? '');

        if (empty($og['title']) && empty($og['description'])) {
            return ['error' => 'no_meta_tags'];
        }
        return $og;
    }

    /**
     * HTTP GET with timeout and redirect following
     */
    private function httpGet($url, $timeout = 8) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
            ],
            CURLOPT_ENCODING       => 'gzip, deflate',
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($data === false || $code >= 400) {
            return false;
        }
        return $data;
    }

    private function getCached($urlHash) {
        $stmt = $this->db->prepare("SELECT * FROM link_previews WHERE url_hash = ? AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([$urlHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return [
            'id'           => $row['id'],
            'url'          => $row['url'],
            'title'        => $row['title'],
            'description'  => $row['description'],
            'image_url'    => $row['image_url'],
            'image_path'   => $row['image_path'],
            'site_name'    => $row['site_name'],
            'favicon_url'  => $row['favicon_url'],
            'favicon_path' => $row['favicon_path'],
            'type'         => $row['type'],
            'author'       => $row['author'],
            'embed_html'   => $row['embed_html'],
        ];
    }

    private function savePreview($urlHash, $url, $preview) {
        $expires = date('Y-m-d H:i:s', time() + $this->cache_ttl);
        $stmt = $this->db->prepare("INSERT INTO link_previews
            (url_hash, url, normalized_url, title, description, image_url, site_name, favicon_url, type, author, embed_html, expires_at, http_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 200)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title), description = VALUES(description),
                image_url = VALUES(image_url), site_name = VALUES(site_name),
                favicon_url = VALUES(favicon_url), type = VALUES(type),
                author = VALUES(author), embed_html = VALUES(embed_html),
                expires_at = VALUES(expires_at), fetched_at = CURRENT_TIMESTAMP");
        $stmt->execute([
            $urlHash,
            $url,
            $this->normalizeUrl($url),
            $preview['title'] ?? null,
            $preview['description'] ?? null,
            $preview['image_url'] ?? null,
            $preview['site_name'] ?? null,
            $preview['favicon_url'] ?? null,
            $preview['type'] ?? 'website',
            $preview['author'] ?? null,
            $preview['embed_html'] ?? null,
            $expires,
        ]);
    }

    public function trackClick($previewId, $userId) {
        $this->db->prepare("INSERT INTO link_preview_clicks (preview_id, user_id) VALUES (?, ?)")
            ->execute([$previewId, $userId]);
    }
}

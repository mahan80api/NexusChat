<?php
/**
 * NexusChat - File Upload Class
 * Handles file uploads including voice messages
 */
class FileUpload {
    private $basePath;
    private $baseUrl;

    public function __construct() {
        $this->basePath = dirname(__DIR__) . '/assets/uploads/';
        $this->baseUrl  = 'assets/uploads/';
    }

    /**
     * Upload avatar image
     */
    public function uploadAvatar($file, $userId) {
        $this->validate($file, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], 5 * 1024 * 1024);
        $ext = $this->getExtension($file['name']);
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $dest = $this->basePath . 'avatars/' . $filename;
        $this->ensureDir($this->basePath . 'avatars/');
        $this->resizeAndSave($file['tmp_name'], $dest, 400, 400);
        return 'avatars/' . $filename;
    }

    /**
     * Upload story media
     */
    public function uploadStory($file, $userId) {
        $this->validate($file, ['image/jpeg','image/png','image/gif','image/webp','video/mp4','video/webm'], 50 * 1024 * 1024);
        $ext = $this->getExtension($file['name']);
        $isVideo = strpos($file['type'], 'video') === 0;
        $sub = $isVideo ? 'videos' : 'images';
        $filename = 'story_' . $userId . '_' . time() . '.' . $ext;
        $dest = $this->basePath . 'stories/' . $sub . '/' . $filename;
        $this->ensureDir($this->basePath . 'stories/' . $sub . '/');
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new Exception('خطا در آپلود فایل');
        }
        return [
            'path' => 'stories/' . $sub . '/' . $filename,
            'type' => $isVideo ? 'video' : 'image',
        ];
    }

    /**
     * Upload chat file (image/video/file/voice)
     */
    public function uploadChatFile($file, $userId) {
        $this->validate($file, null, 100 * 1024 * 1024); // 100MB max for chat files
        $ext = strtolower($this->getExtension($file['name']));
        $type = $this->detectFileType($file, $ext);

        $subDir = match($type) {
            'image' => 'images',
            'video' => 'videos',
            'voice' => 'voice',
            default => 'files',
        };

        $filename = $subDir . '_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $this->basePath . 'files/' . $subDir . '/' . $filename;
        $this->ensureDir($this->basePath . 'files/' . $subDir . '/');

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new Exception('خطا در آپلود فایل');
        }

        $duration = null;
        if ($type === 'voice') {
            $duration = $this->estimateAudioDuration($dest);
        }

        return [
            'path'      => 'files/' . $subDir . '/' . $filename,
            'size'      => filesize($dest),
            'mime'      => $file['type'],
            'type'      => $type,
            'duration'  => $duration,
            'url'       => $this->baseUrl . 'files/' . $subDir . '/' . $filename,
        ];
    }

    /**
     * Detect file type from MIME or extension
     */
    private function detectFileType($file, $ext) {
        $imageExts = ['jpg','jpeg','png','gif','webp','svg','bmp'];
        $videoExts = ['mp4','webm','mov','avi','mkv'];
        $voiceExts = ['mp3','wav','ogg','m4a','webm','aac','opus','oga'];

        if (strpos($file['type'], 'audio') === 0 || in_array($ext, $voiceExts)) {
            return 'voice';
        }
        if (strpos($file['type'], 'image') === 0 || in_array($ext, $imageExts)) {
            return 'image';
        }
        if (strpos($file['type'], 'video') === 0 || in_array($ext, $videoExts)) {
            return 'video';
        }
        return 'file';
    }

    /**
     * Estimate audio duration using ffprobe if available, else file size heuristic
     */
    private function estimateAudioDuration($path) {
        if (function_exists('shell_exec') && file_exists('/usr/bin/ffprobe')) {
            $cmd = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($path) . ' 2>/dev/null';
            $out = @shell_exec($cmd);
            if ($out && is_numeric(trim($out))) {
                return (int)round(trim($out));
            }
        }
        // Rough estimate: assume ~16kbps for voice (1KB/s = 8 seconds)
        $size = filesize($path);
        return (int)round($size / 2000);
    }

    /**
     * Validate file: size and MIME type
     */
    private function validate($file, $allowedMimes = null, $maxSize = 10485760) {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('خطا در آپلود فایل');
        }
        if ($file['size'] > $maxSize) {
            throw new Exception('حجم فایل بیش از حد مجاز است (حداکثر ' . round($maxSize / 1048576, 1) . 'MB)');
        }
        if ($allowedMimes && !in_array($file['type'], $allowedMimes)) {
            throw new Exception('نوع فایل مجاز نیست');
        }
    }

    /**
     * Get file extension safely
     */
    private function getExtension($filename) {
        $parts = explode('.', $filename);
        return strtolower(end($parts));
    }

    /**
     * Ensure directory exists
     */
    private function ensureDir($dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Resize and save image (uses GD)
     */
    private function resizeAndSave($source, $dest, $maxW, $maxH) {
        $info = getimagesize($source);
        if (!$info) {
            return move_uploaded_file($source, $dest);
        }
        $w = $info[0]; $h = $info[1]; $type = $info[2];
        $ratio = min($maxW / $w, $maxH / $h, 1);
        $newW = (int)($w * $ratio);
        $newH = (int)($h * $ratio);

        $img = match($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($source),
            IMAGETYPE_PNG  => imagecreatefrompng($source),
            IMAGETYPE_GIF  => imagecreatefromgif($source),
            IMAGETYPE_WEBP => imagecreatefromwebp($source),
            default => null,
        };
        if (!$img) return move_uploaded_file($source, $dest);

        $new = imagecreatetruecolor($newW, $newH);
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagealphablending($new, false);
            imagesavealpha($new, true);
            $transparent = imagecolorallocatealpha($new, 0, 0, 0, 127);
            imagefilledrectangle($new, 0, 0, $newW, $newH, $transparent);
        }
        imagecopyresampled($new, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        match($type) {
            IMAGETYPE_JPEG => imagejpeg($new, $dest, 85),
            IMAGETYPE_PNG  => imagepng($new, $dest, 8),
            IMAGETYPE_GIF  => imagegif($new, $dest),
            IMAGETYPE_WEBP => imagewebp($new, $dest, 85),
            default => null,
        };
        imagedestroy($img);
        imagedestroy($new);
        return true;
    }
}

<?php
/**
 * NexusChat - File Upload Class
 */
class FileUpload {
    private $allowedImage;
    private $allowedVideo;
    private $allowedAudio;
    private $allowedFile;
    private $maxSize;

    public function __construct() {
        $this->allowedImage = ALLOWED_IMAGE;
        $this->allowedVideo = ALLOWED_VIDEO;
        $this->allowedAudio = ALLOWED_AUDIO;
        $this->allowedFile  = ALLOWED_FILE;
        $this->maxSize      = MAX_UPLOAD_SIZE;
    }

    /**
     * Upload avatar
     */
    public function uploadAvatar($file, $userId) {
        if (!$this->validate($file, $this->allowedImage)) {
            throw new Exception('فایل آواتار نامعتبر است');
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $name = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $path = AVATAR_PATH . $name;
        if (!is_dir(AVATAR_PATH)) {
            mkdir(AVATAR_PATH, 0755, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new Exception('خطا در آپلود فایل');
        }
        return 'avatars/' . $name;
    }

    /**
     * Upload story media
     */
    public function uploadStory($file, $userId) {
        $allowed = array_merge($this->allowedImage, $this->allowedVideo);
        if (!$this->validate($file, $allowed)) {
            throw new Exception('فایل استوری نامعتبر است');
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $type = in_array($ext, $this->allowedVideo) ? 'video' : 'image';
        $name = 'story_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = STORY_PATH . $name;
        if (!is_dir(STORY_PATH)) {
            mkdir(STORY_PATH, 0755, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new Exception('خطا در آپلود فایل');
        }
        return ['path' => 'stories/' . $name, 'type' => $type];
    }

    /**
     * Upload chat file (image, video, audio, document)
     */
    public function uploadChatFile($file, $userId) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $type = 'file';
        if (in_array($ext, $this->allowedImage)) $type = 'image';
        elseif (in_array($ext, $this->allowedVideo)) $type = 'video';
        elseif (in_array($ext, $this->allowedAudio)) $type = 'voice';

        if ($type === 'file' && !$this->validate($file, $this->allowedFile)) {
            throw new Exception('نوع فایل مجاز نیست');
        } elseif ($type !== 'file' && !$this->validate($file, ['jpg','jpeg','png','gif','webp','svg','mp4','webm','mov','avi','mp3','wav','ogg','m4a'])) {
            throw new Exception('نوع فایل مجاز نیست');
        }

        $subDir = $type === 'voice' ? 'voice' : 'files';
        $path = ($type === 'voice' ? VOICE_PATH : FILE_PATH) . $name ?? null;
        $name = $type . '_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $fullPath = ($type === 'voice' ? VOICE_PATH : FILE_PATH) . $name;

        $dir = $type === 'voice' ? VOICE_PATH : FILE_PATH;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new Exception('خطا در آپلود فایل');
        }

        return [
            'path'      => ($type === 'voice' ? 'voice/' : 'files/') . $name,
            'type'      => $type,
            'size'      => $file['size'],
            'mime'      => mime_content_type($fullPath),
        ];
    }

    /**
     * Validate uploaded file
     */
    private function validate($file, $allowed) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        if ($file['size'] > $this->maxSize) {
            return false;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        return in_array($ext, $allowed);
    }
}

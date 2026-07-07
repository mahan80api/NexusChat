<?php
/**
 * VAPID Keys for Web Push Notifications
 *
 * Generate new keys with:
 *   composer require minishlink/web-push
 *   php -r "require 'vendor/autoload.php'; echo Minishlink\WebPush\VAPID::createVapidKeys();"
 *
 * REPLACE the values below with your real keys.
 */
return [
    'publicKey'  => 'BLc4xRzKlKORKpWLNeFN5WZBJvO3wL5KrJNHHHCKsXgKqGgOL5dBJqJYjvKpYh5JN_5HK5HPXjJlGRqK0qxXrDo',
    'privateKey' => 'qK9j3Klw5Nn8Pz4XyZ2aB6cD1eF0gH3iJ5kL7mN9oP1qR3sT5uV7wX9yZ1aB3cD5eF7gH9iJ1kL3mN5oP7qR9sT1uV',
    'subject'    => 'mailto:admin@nexuschat.local',
];

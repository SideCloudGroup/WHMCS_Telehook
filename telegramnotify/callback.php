<?php

try {
    require_once __DIR__ . '/../../../init.php';
    require_once __DIR__ . '/lib.php';

    $secret = telegramnotify_setting('webhook_secret');
    $header = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
    if ($secret === '' || !hash_equals($secret, $header)) {
        http_response_code(403);
        echo 'forbidden';
        exit;
    }

    $raw = file_get_contents('php://input');
    $flags = JSON_BIGINT_AS_STRING;
    $update = json_decode(is_string($raw) ? $raw : '', true, 512, $flags);
    if (is_array($update)) {
        telegramnotify_handle_update($update);
    }
} catch (Throwable $e) {
    if (function_exists('telegramnotify_log')) {
        telegramnotify_log('webhook 异常: ' . $e->getMessage());
    }
}

http_response_code(200);
echo 'ok';

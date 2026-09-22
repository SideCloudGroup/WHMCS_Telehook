<?php

use Illuminate\Database\Capsule\Manager as Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function telegramnotify_setting(string $key): string
{
    try {
        $value = Capsule::table('tbladdonmodules')
            ->where('module', 'telegramnotify')
            ->where('setting', $key)
            ->value('value');
        return trim((string) $value);
    } catch (Throwable $e) {
        return '';
    }
}

function telegramnotify_log(string $message): void
{
    try {
        if (function_exists('logActivity')) {
            logActivity('TelegramNotify: ' . $message);
        }
    } catch (Throwable $e) {
    }
}

function telegramnotify_base_url(): string
{
    $configured = telegramnotify_setting('base_url');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    try {
        $value = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
        return rtrim(trim((string) $value), '/');
    } catch (Throwable $e) {
        return '';
    }
}

function telegramnotify_bot_username(): string
{
    $name = telegramnotify_setting('bot_username');
    $name = ltrim($name, '@');
    return $name;
}

function telegramnotify_due_days(): array
{
    $raw = telegramnotify_setting('due_days');
    if ($raw === '') {
        $raw = '7,3,1,0';
    }
    $days = [];
    foreach (preg_split('/\s*,\s*/', $raw) as $part) {
        if ($part !== '' && preg_match('/^\d+$/', $part)) {
            $days[] = (int) $part;
        }
    }
    $days = array_values(array_unique($days));
    sort($days);
    return $days === [] ? [0, 1, 3, 7] : $days;
}

function telegramnotify_tables_ready(): bool
{
    try {
        return Capsule::schema()->hasTable('mod_telegram_clients')
            && Capsule::schema()->hasTable('mod_telegram_log');
    } catch (Throwable $e) {
        return false;
    }
}

function telegramnotify_api(string $method, array $params): array
{
    try {
        $token = telegramnotify_setting('bot_token');
        if ($token === '') {
            return ['ok' => false, 'description' => '未配置 Bot Token', 'http_code' => 0];
        }
        $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
        if ($ch === false) {
            return ['ok' => false, 'description' => 'curl 初始化失败', 'http_code' => 0];
        }
        $body = json_encode($params, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            curl_close($ch);
            return ['ok' => false, 'description' => '请求编码失败', 'http_code' => 0];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $errno !== 0) {
            return ['ok' => false, 'description' => $err !== '' ? $err : '网络错误', 'http_code' => $code];
        }
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'description' => 'Telegram 响应无效', 'http_code' => $code];
        }
        $data['http_code'] = $code;
        if (!array_key_exists('ok', $data)) {
            $data['ok'] = false;
        }
        return $data;
    } catch (Throwable $e) {
        return ['ok' => false, 'description' => $e->getMessage(), 'http_code' => 0];
    }
}

function telegramnotify_dead_chat(array $resp): bool
{
    if (!empty($resp['ok'])) {
        return false;
    }
    $desc = strtolower((string) ($resp['description'] ?? ''));
    if ($desc === '') {
        return false;
    }
    $needles = [
        'blocked by the user',
        'bot was blocked',
        'bot was kicked',
        'chat not found',
        'user is deactivated',
        'peer_id_invalid',
        "bot can't initiate",
        'have no rights to send',
    ];
    foreach ($needles as $needle) {
        if (strpos($desc, $needle) !== false) {
            return true;
        }
    }
    return false;
}

function telegramnotify_mark_blocked(string $chatId, string $reason): void
{
    try {
        if ($chatId === '' || !telegramnotify_tables_ready()) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $n = Capsule::table('mod_telegram_clients')
            ->where('chat_id', $chatId)
            ->whereNull('blocked_at')
            ->update(['blocked_at' => $now, 'updated_at' => $now]);
        if ($n > 0) {
            telegramnotify_log('绑定失效，已停止通知: ' . ($reason !== '' ? $reason : $chatId));
        }
    } catch (Throwable $e) {
        telegramnotify_log('标记绑定失效失败: ' . $e->getMessage());
    }
}

function telegramnotify_send_chat(string $chatId, string $text, bool $markBlocked): bool
{
    try {
        if ($chatId === '' || $text === '') {
            return false;
        }
        $resp = telegramnotify_api('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ]);
        if (!empty($resp['ok'])) {
            return true;
        }
        $desc = (string) ($resp['description'] ?? 'unknown');
        if ($markBlocked && telegramnotify_dead_chat($resp)) {
            telegramnotify_mark_blocked($chatId, $desc);
            return false;
        }
        telegramnotify_log('发送失败: ' . $desc);
        return false;
    } catch (Throwable $e) {
        telegramnotify_log('发送异常: ' . $e->getMessage());
        return false;
    }
}

function telegramnotify_webhook_url(): string
{
    $base = telegramnotify_base_url();
    if ($base === '') {
        return '';
    }
    return $base . '/modules/addons/telegramnotify/callback.php';
}

function telegramnotify_ensure_webhook(bool $force): array
{
    try {
        $token = telegramnotify_setting('bot_token');
        $secret = telegramnotify_setting('webhook_secret');
        $url = telegramnotify_webhook_url();
        if ($token === '' || $secret === '' || $url === '') {
            return ['ok' => false, 'message' => '请先填写 Bot Token、Webhook Secret 和可访问的站点地址'];
        }
        if (!$force) {
            $info = telegramnotify_api('getWebhookInfo', []);
            $current = '';
            if (!empty($info['ok']) && isset($info['result']['url'])) {
                $current = (string) $info['result']['url'];
            }
            if ($current === $url) {
                return ['ok' => true, 'message' => 'Webhook 已是 ' . $url];
            }
        }
        $resp = telegramnotify_api('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => false,
        ]);
        if (!empty($resp['ok'])) {
            return ['ok' => true, 'message' => 'Webhook 已注册: ' . $url];
        }
        $desc = (string) ($resp['description'] ?? 'setWebhook 失败');
        telegramnotify_log('注册 Webhook 失败: ' . $desc);
        return ['ok' => false, 'message' => $desc];
    } catch (Throwable $e) {
        telegramnotify_log('注册 Webhook 异常: ' . $e->getMessage());
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function telegramnotify_client_row(int $clientId): ?object
{
    try {
        if ($clientId <= 0 || !telegramnotify_tables_ready()) {
            return null;
        }
        $row = Capsule::table('mod_telegram_clients')->where('client_id', $clientId)->first();
        return $row ?: null;
    } catch (Throwable $e) {
        telegramnotify_log('读取绑定失败: ' . $e->getMessage());
        return null;
    }
}

function telegramnotify_active_binding(int $clientId): ?object
{
    $row = telegramnotify_client_row($clientId);
    if (!$row || !empty($row->blocked_at) || (string) $row->chat_id === '') {
        return null;
    }
    return $row;
}

function telegramnotify_cache(): ?\WHMCS\TransientData
{
    try {
        if (!class_exists(\WHMCS\TransientData::class)) {
            return null;
        }
        return new \WHMCS\TransientData();
    } catch (Throwable $e) {
        telegramnotify_log('读取 WHMCS 缓存失败: ' . $e->getMessage());
        return null;
    }
}

function telegramnotify_token_key(string $hash): string
{
    return 'telehook_bind_' . $hash;
}

function telegramnotify_client_token_key(int $clientId): string
{
    return 'telehook_client_' . $clientId;
}

function telegramnotify_forget_token(int $clientId): void
{
    $cache = telegramnotify_cache();
    if ($cache === null || $clientId <= 0) {
        return;
    }
    $hash = (string) $cache->retrieve(telegramnotify_client_token_key($clientId));
    if ($hash !== '') {
        $cache->delete(telegramnotify_token_key($hash));
    }
    $cache->delete(telegramnotify_client_token_key($clientId));
}

function telegramnotify_issue_token(int $clientId): string
{
    try {
        if ($clientId <= 0) {
            return '';
        }
        $cache = telegramnotify_cache();
        if ($cache === null) {
            return '';
        }
        telegramnotify_forget_token($clientId);
        $token = bin2hex(random_bytes(16));
        $hash = hash('sha256', $token);
        $cache->store(telegramnotify_token_key($hash), (string) $clientId, 1800);
        $cache->store(telegramnotify_client_token_key($clientId), $hash, 1800);
        return $token;
    } catch (Throwable $e) {
        telegramnotify_log('生成绑定链接失败: ' . $e->getMessage());
        return '';
    }
}

function telegramnotify_deep_link(string $token): string
{
    $name = telegramnotify_bot_username();
    if ($name === '' || $token === '') {
        return '';
    }
    return 'https://t.me/' . rawurlencode($name) . '?start=c_' . $token;
}

function telegramnotify_consume_token(string $token, string $chatId, string $username): string
{
    try {
        if ($token === '' || $chatId === '' || !telegramnotify_tables_ready()) {
            return 'invalid';
        }
        $cache = telegramnotify_cache();
        if ($cache === null) {
            return 'error';
        }
        $hash = hash('sha256', $token);
        $clientId = (int) $cache->retrieve(telegramnotify_token_key($hash));
        if ($clientId <= 0) {
            return 'invalid';
        }
        $now = date('Y-m-d H:i:s');
        $username = substr(preg_replace('/[^A-Za-z0-9_]/', '', $username), 0, 64);
        $existing = Capsule::table('mod_telegram_clients')->where('client_id', $clientId)->first();
        if ($existing) {
            Capsule::table('mod_telegram_clients')->where('client_id', $clientId)->update([
                'chat_id' => $chatId,
                'username' => $username,
                'blocked_at' => null,
                'updated_at' => $now,
            ]);
        } else {
            Capsule::table('mod_telegram_clients')->insert([
                'client_id' => $clientId,
                'chat_id' => $chatId,
                'username' => $username,
                'blocked_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $cache->delete(telegramnotify_token_key($hash));
        $cache->delete(telegramnotify_client_token_key($clientId));
        return 'ok';
    } catch (Throwable $e) {
        telegramnotify_log('绑定失败: ' . $e->getMessage());
        return 'error';
    }
}

function telegramnotify_unbind_client(int $clientId): bool
{
    try {
        if ($clientId <= 0 || !telegramnotify_tables_ready()) {
            return false;
        }
        Capsule::table('mod_telegram_clients')->where('client_id', $clientId)->delete();
        telegramnotify_forget_token($clientId);
        return true;
    } catch (Throwable $e) {
        telegramnotify_log('解除绑定失败: ' . $e->getMessage());
        return false;
    }
}

function telegramnotify_unbind_chat(string $chatId): int
{
    try {
        if ($chatId === '' || !telegramnotify_tables_ready()) {
            return 0;
        }
        return (int) Capsule::table('mod_telegram_clients')->where('chat_id', $chatId)->delete();
    } catch (Throwable $e) {
        telegramnotify_log('解除绑定失败: ' . $e->getMessage());
        return 0;
    }
}

function telegramnotify_handle_update(array $update): void
{
    try {
        $message = $update['message'] ?? null;
        if (!is_array($message)) {
            return;
        }
        $chat = $message['chat'] ?? [];
        if (!is_array($chat) || ($chat['type'] ?? '') !== 'private') {
            return;
        }
        $chatId = isset($chat['id']) ? (string) $chat['id'] : '';
        if ($chatId === '' || !preg_match('/^-?\d+$/', $chatId)) {
            return;
        }
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $username = (string) ($from['username'] ?? ($chat['username'] ?? ''));
        $text = trim((string) ($message['text'] ?? ''));
        if (preg_match('#^/start(?:@\w+)?(?:\s+(\S+))?\s*$#', $text, $match)) {
            $arg = $match[1] ?? '';
            if (!preg_match('#^c_([A-Za-z0-9]+)$#', $arg, $tokenMatch)) {
                telegramnotify_send_chat($chatId, '请从 WHMCS 客户区打开绑定链接。', false);
                return;
            }
            $result = telegramnotify_consume_token($tokenMatch[1], $chatId, $username);
            if ($result === 'ok') {
                telegramnotify_send_chat($chatId, "绑定成功。产品开通、续费账单和到期提醒会发到这里。\n发送 /unbind 可解除绑定。", true);
                return;
            }
            telegramnotify_send_chat($chatId, '绑定链接无效或已过期，请回客户区重新生成。', false);
            return;
        }
        if (preg_match('#^/unbind(?:@\w+)?\s*$#', $text)) {
            $count = telegramnotify_unbind_chat($chatId);
            $reply = $count > 0 ? '已解除绑定。' : '当前没有绑定。';
            telegramnotify_send_chat($chatId, $reply, false);
        }
    } catch (Throwable $e) {
        telegramnotify_log('webhook 处理失败: ' . $e->getMessage());
    }
}

function telegramnotify_already_sent(int $clientId, string $kind, int $refId, string $marker, string $sentOn): bool
{
    try {
        if (!telegramnotify_tables_ready()) {
            return true;
        }
        return Capsule::table('mod_telegram_log')
            ->where('client_id', $clientId)
            ->where('kind', $kind)
            ->where('ref_id', $refId)
            ->where('marker', $marker)
            ->where('sent_on', $sentOn)
            ->exists();
    } catch (Throwable $e) {
        telegramnotify_log('读取通知记录失败: ' . $e->getMessage());
        return true;
    }
}

function telegramnotify_remember(int $clientId, string $kind, int $refId, string $marker, string $sentOn): void
{
    try {
        Capsule::table('mod_telegram_log')->insert([
            'client_id' => $clientId,
            'kind' => $kind,
            'ref_id' => $refId,
            'marker' => $marker,
            'sent_on' => $sentOn,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
    }
}

function telegramnotify_notify_provision(array $params): void
{
    try {
        $serviceId = (int) ($params['serviceid'] ?? 0);
        if ($serviceId <= 0) {
            return;
        }
        $userId = (int) ($params['userid'] ?? 0);
        $domain = trim((string) ($params['domain'] ?? ''));
        $product = '';
        $hosting = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if ($hosting) {
            if ($userId <= 0) {
                $userId = (int) $hosting->userid;
            }
            if ($domain === '') {
                $domain = trim((string) $hosting->domain);
            }
            $product = trim((string) Capsule::table('tblproducts')->where('id', $hosting->packageid)->value('name'));
        }
        $bind = telegramnotify_active_binding($userId);
        if ($bind === null) {
            return;
        }
        $today = date('Y-m-d');
        if (telegramnotify_already_sent($userId, 'provision', $serviceId, '', $today)) {
            return;
        }
        if ($product === '') {
            $product = '产品';
        }
        $text = "产品已开通\n产品：" . $product . "\n域名：" . ($domain !== '' ? $domain : '—');
        if (telegramnotify_send_chat((string) $bind->chat_id, $text, true)) {
            telegramnotify_remember($userId, 'provision', $serviceId, '', $today);
        }
    } catch (Throwable $e) {
        telegramnotify_log('开通通知失败: ' . $e->getMessage());
    }
}

function telegramnotify_notify_invoice(array $vars): void
{
    try {
        if ((string) ($vars['source'] ?? '') !== 'autogen') {
            return;
        }
        $invoiceId = (int) ($vars['invoiceid'] ?? 0);
        if ($invoiceId <= 0) {
            return;
        }
        $status = (string) ($vars['status'] ?? '');
        if ($status !== '' && strcasecmp($status, 'Unpaid') !== 0) {
            return;
        }
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$invoice || strcasecmp((string) $invoice->status, 'Unpaid') !== 0) {
            return;
        }
        $userId = (int) $invoice->userid;
        $bind = telegramnotify_active_binding($userId);
        if ($bind === null) {
            return;
        }
        $today = date('Y-m-d');
        if (telegramnotify_already_sent($userId, 'invoice', $invoiceId, '', $today)) {
            return;
        }
        $items = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->get();
        $lines = [];
        foreach ($items as $item) {
            $label = trim((string) $item->description);
            $parts = preg_split("/\r\n|\n|\r/", $label);
            $label = trim((string) ($parts[0] ?? ''));
            if ((string) $item->type === 'Hosting' && (int) $item->relid > 0) {
                $hosting = Capsule::table('tblhosting as h')
                    ->leftJoin('tblproducts as p', 'p.id', '=', 'h.packageid')
                    ->where('h.id', (int) $item->relid)
                    ->first(['p.name as product', 'h.domain']);
                if ($hosting) {
                    $label = trim((string) $hosting->product . ' ' . (string) $hosting->domain);
                }
            }
            if ($label !== '') {
                $lines[] = $label;
            }
        }
        $code = '';
        $client = Capsule::table('tblclients')->where('id', $userId)->first();
        if ($client) {
            $code = trim((string) Capsule::table('tblcurrencies')->where('id', $client->currency)->value('code'));
        }
        $amount = number_format((float) $invoice->total, 2, '.', '');
        if ($code !== '') {
            $amount .= ' ' . $code;
        }
        $due = trim((string) $invoice->duedate);
        $base = telegramnotify_base_url();
        $text = "续费账单已生成\n账单号：#" . $invoiceId . "\n金额：" . $amount . "\n付款截止：" . ($due !== '' ? $due : '—');
        if ($lines !== []) {
            $shown = array_slice($lines, 0, 20);
            $text .= "\n产品：\n- " . implode("\n- ", $shown);
            if (count($lines) > 20) {
                $text .= "\n- …";
            }
        }
        if ($base !== '') {
            $text .= "\n查看账单：" . $base . '/viewinvoice.php?id=' . $invoiceId;
        }
        if (telegramnotify_send_chat((string) $bind->chat_id, $text, true)) {
            telegramnotify_remember($userId, 'invoice', $invoiceId, '', $today);
        }
    } catch (Throwable $e) {
        telegramnotify_log('账单通知失败: ' . $e->getMessage());
    }
}

function telegramnotify_notify_due(): void
{
    try {
        if (!telegramnotify_tables_ready()) {
            return;
        }
        $today = date('Y-m-d');
        $wanted = [];
        foreach (telegramnotify_due_days() as $day) {
            $date = date('Y-m-d', strtotime($today . ' +' . $day . ' days'));
            $wanted[$date] = $day;
        }
        if ($wanted === []) {
            return;
        }
        $rows = Capsule::table('tblhosting as h')
            ->join('mod_telegram_clients as t', 't.client_id', '=', 'h.userid')
            ->leftJoin('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->whereIn('h.domainstatus', ['Active', 'Suspended'])
            ->whereIn('h.nextduedate', array_keys($wanted))
            ->whereNull('t.blocked_at')
            ->get(['h.id', 'h.userid', 'h.domain', 'h.nextduedate', 'p.name as product', 't.chat_id']);
        foreach ($rows as $row) {
            $due = (string) $row->nextduedate;
            if (!isset($wanted[$due])) {
                continue;
            }
            $day = (int) $wanted[$due];
            $clientId = (int) $row->userid;
            $serviceId = (int) $row->id;
            $marker = (string) $day;
            if (telegramnotify_already_sent($clientId, 'expiry', $serviceId, $marker, $today)) {
                continue;
            }
            $product = trim((string) $row->product);
            if ($product === '') {
                $product = '产品';
            }
            $domain = trim((string) $row->domain);
            if ($day === 0) {
                $remain = '今天到期';
            } else {
                $remain = '剩余 ' . $day . ' 天';
            }
            $text = "产品到期提醒\n产品：" . $product . "\n域名：" . ($domain !== '' ? $domain : '—') . "\n到期日：" . $due . "\n" . $remain;
            $base = telegramnotify_base_url();
            if ($base !== '') {
                $text .= "\n查看产品：" . $base . '/clientarea.php?action=productdetails&id=' . $serviceId;
            }
            if (telegramnotify_send_chat((string) $row->chat_id, $text, true)) {
                telegramnotify_remember($clientId, 'expiry', $serviceId, $marker, $today);
            }
        }
    } catch (Throwable $e) {
        telegramnotify_log('到期提醒失败: ' . $e->getMessage());
    }
}

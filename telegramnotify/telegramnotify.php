<?php

use Illuminate\Database\Capsule\Manager as Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib.php';

function telegramnotify_config(): array
{
    return [
        'name' => 'Telehook',
        'description' => '客户绑定 Telegram 后，接收产品开通、续费账单和到期提醒。',
        'version' => '1.0.3',
        'author' => 'SideCloud',
        'language' => 'chinese',
        'fields' => [
            'bot_token' => [
                'FriendlyName' => 'Bot Token',
                'Type' => 'password',
                'Size' => '80',
                'Description' => 'Telegram BotFather 提供的 token',
            ],
            'bot_username' => [
                'FriendlyName' => 'Bot 用户名',
                'Type' => 'text',
                'Size' => '64',
                'Description' => '不含 @，用于生成客户绑定链接',
            ],
            'webhook_secret' => [
                'FriendlyName' => 'Webhook Secret',
                'Type' => 'password',
                'Size' => '80',
                'Description' => '随机字符串。保存这里不会注册 Webhook。保存后打开 <a href="addonmodules.php?module=telegramnotify">Telehook</a>，点「注册 Webhook」才能看到是否成功',
            ],
            'base_url' => [
                'FriendlyName' => '站点地址',
                'Type' => 'text',
                'Size' => '80',
                'Description' => '客户区和 Webhook 使用的根地址，例如 https://billing.example.com 。留空则用 WHMCS 系统 URL',
            ],
            'due_days' => [
                'FriendlyName' => '到期提醒天数',
                'Type' => 'text',
                'Size' => '40',
                'Default' => '7,3,1,0',
                'Description' => '逗号分隔。0 表示到期当天',
            ],
        ],
    ];
}

function telegramnotify_activate(): array
{
    try {
        if (!Capsule::schema()->hasTable('mod_telegram_clients')) {
            Capsule::schema()->create('mod_telegram_clients', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');
                $table->string('chat_id', 32);
                $table->string('username', 64)->default('');
                $table->dateTime('blocked_at')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->unique('client_id');
                $table->index('chat_id');
            });
        }
        if (Capsule::schema()->hasTable('mod_telegram_tokens')) {
            Capsule::schema()->drop('mod_telegram_tokens');
        }
        if (!Capsule::schema()->hasTable('mod_telegram_log')) {
            Capsule::schema()->create('mod_telegram_log', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');
                $table->string('kind', 32);
                $table->unsignedInteger('ref_id')->default(0);
                $table->string('marker', 32)->default('');
                $table->date('sent_on');
                $table->dateTime('created_at');
                $table->unique(['client_id', 'kind', 'ref_id', 'marker', 'sent_on'], 'mod_telegram_log_dedupe');
            });
        }
        return ['status' => 'success', 'description' => 'Telegram 通知已启用'];
    } catch (Throwable $e) {
        return ['status' => 'error', 'description' => '启用失败: ' . $e->getMessage()];
    }
}

function telegramnotify_deactivate(): array
{
    return ['status' => 'success', 'description' => '模块已停用，绑定记录仍保留'];
}

function telegramnotify_upgrade($vars): void
{
    try {
        if (Capsule::schema()->hasTable('mod_telegram_tokens')) {
            Capsule::schema()->drop('mod_telegram_tokens');
        }
    } catch (Throwable $e) {
        telegramnotify_log('移除旧绑定令牌表失败: ' . $e->getMessage());
    }
}

function telegramnotify_session_token(string $key): string
{
    try {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        $current = $_SESSION[$key] ?? '';
        if (!is_string($current) || $current === '') {
            $current = bin2hex(random_bytes(16));
            $_SESSION[$key] = $current;
        }
        return $current;
    } catch (Throwable $e) {
        return '';
    }
}

function telegramnotify_session_token_ok(string $key): bool
{
    $token = (string) ($_POST['token'] ?? '');
    $expected = '';
    try {
        $expected = (string) ($_SESSION[$key] ?? '');
    } catch (Throwable $e) {
        return false;
    }
    return $token !== '' && $expected !== '' && hash_equals($expected, $token);
}

function telegramnotify_output($vars): void
{
    try {
        $posted = (($_POST['action'] ?? '') === 'setwebhook');
        $webhook = null;
        if ($posted) {
            if (!telegramnotify_session_token_ok('telehook_admin_csrf')) {
                $webhook = ['ok' => false, 'message' => '页面已过期，请刷新后再点注册'];
            } else {
                $webhook = telegramnotify_ensure_webhook(true);
            }
        }
        $rows = [];
        if (telegramnotify_tables_ready()) {
            $rows = Capsule::table('mod_telegram_clients as t')
                ->leftJoin('tblclients as c', 'c.id', '=', 't.client_id')
                ->orderBy('t.updated_at', 'desc')
                ->limit(100)
                ->get(['t.client_id', 't.chat_id', 't.username', 't.blocked_at', 't.updated_at', 'c.firstname', 'c.lastname', 'c.email']);
        }
        $token = telegramnotify_session_token('telehook_admin_csrf');
        echo '<div style="margin-bottom:16px;padding:16px;border:1px solid #d8e2ef;border-radius:8px;background:#fff">';
        echo '<h3 style="margin-top:0">注册 Webhook</h3>';
        echo '<p>保存插件设置不会联系 Telegram。只有点这个按钮才会注册，结果会直接显示在下面。</p>';
        echo '<p>回调地址：<code>' . telegramnotify_h(telegramnotify_webhook_url()) . '</code></p>';
        if (is_array($webhook)) {
            $ok = !empty($webhook['ok']);
            echo '<div class="alert alert-' . ($ok ? 'success' : 'danger') . '">';
            echo $ok ? 'Webhook 注册成功。' : 'Webhook 注册失败。';
            echo ' ' . telegramnotify_h((string) ($webhook['message'] ?? ''));
            echo '</div>';
        }
        echo '<form method="post" action="addonmodules.php?module=telegramnotify">';
        echo '<input type="hidden" name="token" value="' . telegramnotify_h($token) . '" />';
        echo '<input type="hidden" name="action" value="setwebhook" />';
        echo '<button type="submit" class="btn btn-primary">注册 Webhook</button>';
        echo '</form>';
        echo '</div>';
        echo '<h3 style="margin-top:20px">最近绑定</h3>';
        echo '<table class="datatable" width="100%"><tr><th>客户</th><th>Telegram</th><th>状态</th><th>更新时间</th></tr>';
        if (count($rows) === 0) {
            echo '<tr><td colspan="4">还没有客户绑定</td></tr>';
        }
        foreach ($rows as $row) {
            $name = trim((string) $row->firstname . ' ' . (string) $row->lastname);
            if ($name === '') {
                $name = '#' . (int) $row->client_id;
            }
            $email = trim((string) $row->email);
            $tg = (string) $row->username;
            $tg = $tg !== '' ? '@' . $tg : (string) $row->chat_id;
            $state = empty($row->blocked_at) ? '有效' : '已失效';
            echo '<tr>';
            echo '<td><a href="clientssummary.php?userid=' . (int) $row->client_id . '">' . telegramnotify_h($name) . '</a>';
            if ($email !== '') {
                echo '<br>' . telegramnotify_h($email);
            }
            echo '</td>';
            echo '<td>' . telegramnotify_h($tg) . '</td>';
            echo '<td>' . telegramnotify_h($state) . '</td>';
            echo '<td>' . telegramnotify_h((string) $row->updated_at) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } catch (Throwable $e) {
        telegramnotify_log('后台页面失败: ' . $e->getMessage());
        echo '<div class="alert alert-danger">' . telegramnotify_h($e->getMessage()) . '</div>';
    }
}

function telegramnotify_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function telegramnotify_json(array $payload, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function telegramnotify_client_state(int $clientId): array
{
    $row = telegramnotify_client_row($clientId);
    return [
        'ok' => true,
        'bound' => $row !== null && empty($row->blocked_at),
        'blocked' => $row !== null && !empty($row->blocked_at),
        'username' => $row ? (string) $row->username : '',
    ];
}

function telegramnotify_csrf_ok(): bool
{
    return telegramnotify_session_token_ok('telehook_client_csrf');
}

function telegramnotify_clientarea($vars): array
{
    $clientId = 0;
    try {
        $clientId = (int) ($_SESSION['uid'] ?? 0);
    } catch (Throwable $e) {
        $clientId = 0;
    }
    $isAjax = (($_POST['ajax'] ?? '') === '1');
    if ($isAjax) {
        try {
            if ($clientId <= 0) {
                telegramnotify_json(['ok' => false, 'message' => '请先登录'], 401);
            }
            if (!telegramnotify_csrf_ok()) {
                telegramnotify_json(['ok' => false, 'message' => '页面已过期，请刷新后再试'], 403);
            }
            $action = (string) ($_POST['action'] ?? 'status');
            if ($action === 'status') {
                telegramnotify_json(telegramnotify_client_state($clientId));
            }
            if ($action === 'unbind') {
                if (!telegramnotify_unbind_client($clientId)) {
                    telegramnotify_json(['ok' => false, 'message' => '解除绑定失败，请稍后再试']);
                }
                $state = telegramnotify_client_state($clientId);
                $state['message'] = '已解除绑定';
                telegramnotify_json($state);
            }
            if ($action === 'bind') {
                if (telegramnotify_bot_username() === '') {
                    telegramnotify_json(['ok' => false, 'message' => '管理员尚未配置 Bot 用户名']);
                }
                $issued = telegramnotify_issue_token($clientId);
                $link = telegramnotify_deep_link($issued);
                if ($link === '') {
                    telegramnotify_json(['ok' => false, 'message' => '暂时无法生成绑定链接']);
                }
                $state = telegramnotify_client_state($clientId);
                $state['link'] = $link;
                $state['message'] = '链接 30 分钟内有效，只能使用一次';
                telegramnotify_json($state);
            }
            telegramnotify_json(['ok' => false, 'message' => '未知操作'], 400);
        } catch (Throwable $e) {
            telegramnotify_log('客户区操作失败: ' . $e->getMessage());
            telegramnotify_json(['ok' => false, 'message' => '操作失败，请稍后再试'], 500);
        }
    }
    return [
        'pagetitle' => 'Telegram 通知',
        'breadcrumb' => ['index.php?m=telegramnotify' => 'Telegram 通知'],
        'templatefile' => 'clientarea',
        'requirelogin' => true,
        'vars' => [
            'tg_token' => telegramnotify_session_token('telehook_client_csrf'),
        ],
    ];
}

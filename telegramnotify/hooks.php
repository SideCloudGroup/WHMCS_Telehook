<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib.php';

add_hook('AfterModuleCreate', 1, function ($params) {
    try {
        telegramnotify_notify_provision(is_array($params) ? $params : []);
    } catch (Throwable $e) {
        telegramnotify_log('开通通知钩子失败: ' . $e->getMessage());
    }
});

add_hook('InvoiceCreated', 1, function ($vars) {
    try {
        telegramnotify_notify_invoice(is_array($vars) ? $vars : []);
    } catch (Throwable $e) {
        telegramnotify_log('账单通知钩子失败: ' . $e->getMessage());
    }
});

add_hook('DailyCronJob', 1, function () {
    try {
        telegramnotify_notify_due();
    } catch (Throwable $e) {
        telegramnotify_log('到期提醒钩子失败: ' . $e->getMessage());
    }
});

add_hook('ClientAreaPrimaryNavbar', 1, function ($navbar) {
    try {
        $clientId = (int) ($_SESSION['uid'] ?? 0);
        if ($clientId <= 0 && class_exists(\WHMCS\Session::class)) {
            $clientId = (int) \WHMCS\Session::get('uid');
        }
        if ($clientId <= 0 || !is_object($navbar) || !method_exists($navbar, 'addChild')) {
            return;
        }
        $navbar->addChild('telegramnotify', [
            'label' => 'Telegram 通知',
            'uri' => 'index.php?m=telegramnotify',
            'order' => 80,
        ]);
    } catch (Throwable $e) {
        telegramnotify_log('客户区菜单失败: ' . $e->getMessage());
    }
});

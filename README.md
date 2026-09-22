# Telehook

Telehook 是一个 WHMCS 插件。客户在客户区绑定一次 Telegram 之后，这个账号下的产品开通、续费账单和到期提醒都会发到 Telegram。

绑定挂在 WHMCS 客户上，不区分产品模块。邮局、VPS、域名都会通知到同一个 Telegram。

## 要求

- WHMCS 8 或更高版本
- 站点有公网 HTTPS。Telegram 要能访问 Webhook
- 一个 Telegram Bot。在 [@BotFather](https://t.me/BotFather) 里用 `/newbot` 创建

## 安装

1. 把本仓库的 `telegramnotify` 目录复制到 WHMCS 的 `modules/addons/telegramnotify`。
2. 后台打开「系统设置 → 插件模块」，找到 **Telehook**，点击激活。
3. 填好下面的配置并保存。
4. 打开 Telehook 的模块页面。打开时会向 Telegram 注册 Webhook。页面上也可以点「重新注册 Webhook」。

回调地址是：

```text
https://你的站点/modules/addons/telegramnotify/callback.php
```

## 配置

| 配置项 | 说明 |
| --- | --- |
| Bot Token | BotFather 提供的 token |
| Bot 用户名 | 机器人用户名，不要带 `@`。用来生成客户的绑定链接 |
| Webhook Secret | 一串随机字符。Telegram 回调必须带上它，对不上的请求会直接拒绝 |
| 站点地址 | 客户区和 Webhook 使用的根地址，例如 `https://billing.example.com`。留空则使用 WHMCS 系统 URL |
| 到期提醒天数 | 逗号分隔，默认 `7,3,1,0`。`0` 表示到期当天 |

## 客户绑定

客户登录后，点导航里的「Telegram 通知」，或打开 `index.php?m=telegramnotify`。

点「生成绑定链接」，在 Telegram 里打开并点击开始。绑定完成后，页面会自动变成已绑定，不用刷新整页。

链接放在 WHMCS 自带缓存里，30 分钟内有效，用过就失效。同一时间一个客户只有一条有效链接。

解除绑定可以在页面上操作，也可以在 Telegram 里发送 `/unbind`。

## 通知

只发给已经绑定、且绑定仍然有效的客户。没绑定的客户会跳过，之后也不会补发。

- **开通**：产品开通成功后，发送产品名和域名。开通失败不发。
- **账单**：WHMCS 自动生成续费账单时，发送金额、付款截止日和账单链接。下单时的那张账单不发。提前几天出账，沿用 WHMCS 自己的账单设置。
- **到期**：按配置的天数检查正常和已暂停的产品。已终止、已取消的不发。

插件只发消息，不会暂停产品，也不会改账单。

## 发送失败

Bot 请求失败时只记入 WHMCS 活动日志，不影响开通、出账和每天的定时任务。

客户停用了 Bot，或会话已经不存在时，这条绑定会被标成失效，之后不再请求 Telegram。客户重新绑定后恢复。

停用模块不会删除已经保存的绑定。

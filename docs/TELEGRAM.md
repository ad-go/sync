# Telegram and e-mail alerts

Conflicts, refused edits and similar events are sent to a Telegram chat; when `sync.mail.to` is set every message
is also mailed. Conflicts come with inline buttons (keep one node's version) that work from Telegram. The admin UI
stays the source of truth: Telegram is a shortcut.

## 1. Create the bot

1. In Telegram open [@BotFather](https://t.me/BotFather) and send `/newbot`.
2. Give the bot a display name, then a username that ends in `bot` (for example `acme_sync_bot`).
3. BotFather answers with the **token**, `123456789:AAH...`. Treat it like a password; if it leaks, send
   `/revoke` to BotFather and use the new one.
4. Optional: `/setprivacy` → *Enable* (the default) is fine, the bot only posts and reads button presses.
   `/setdescription` and `/setuserpic` make the bot recognisable to the team.

## 2. Find the chat id

The bot can only write to a chat it was started in or added to.

* **Direct chat:** open the bot and press *Start* (or send any message).
* **Group:** add the bot as a member, then write a message in the group.
* **Channel:** add the bot as an administrator with permission to post.

Then ask Telegram for the updates the bot has seen:

```bash
curl "https://api.telegram.org/bot<TOKEN>/getUpdates"
```

The chat id is `message.chat.id` in the answer: a positive number for a person, a negative one for a group, and
`-100...` for a supergroup or channel. If `result` is empty, write another message to the bot and repeat.

## 3. Configure the nodes

In `.env` (the same bot and chat on every node; the secret is a long random string of your choice):

```ini
sync.telegram.token          = 123456789:AAH...
sync.telegram.chat_id        = -1001234567890
sync.telegram.webhook_secret = <random 32+ characters>
sync.telegram.admin_url      = https://node.example.com/sync-admin/conflicts
sync.telegram.queue          = telegram
```

* `admin_url` adds an *Open in admin* button to each conflict.
* `allowed_users` is a comma-separated list of Telegram user ids (not usernames; ask `@userinfobot` or read
  `from.id` in `getUpdates`). Only they can press the decision buttons; anybody else gets a refusal. Left empty,
  every member of the chat can decide, so keep the chat small or set the list.
* `queue` sends through the Queue package so a slow Telegram never delays a sync run and failures are retried;
  `SyncTasks::register()` drains it every minute. Leave it empty to send directly.
* Give all nodes the same default locale (`Config\App::$defaultLocale`): they post to one chat.

For the e-mail copy see [CONFIGURATION.md](CONFIGURATION.md#alerts). The SMTP host name must be the one in the
server's certificate; a mismatch makes the TLS handshake fail.

## 4. Register the webhook (buttons)

Alerts need only the steps above. The buttons need Telegram to reach one of your nodes over public HTTPS. A bot has
**one** webhook: register it on a node that is reachable from the internet (the hub, not a node behind NAT).

```bash
curl "https://api.telegram.org/bot<TOKEN>/setWebhook" \
     --data-urlencode "url=https://hub.example.com/sync-api/telegram" \
     --data-urlencode "secret_token=<webhook_secret>"
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"      # url set, last_error_message empty
```

The node verifies the `X-Telegram-Bot-Api-Secret-Token` header and the chat id before it acts. A button press
for a conflict of another node is forwarded to that node over the signed peer API (`telegram/decide`), so one
webhook serves all nodes that the receiving node can call. A conflict detected on a passive node (behind NAT)
cannot be forwarded to it; resolve those in the admin UI.

The webhook route is excluded from the HMAC filter and from CSRF: `Config\Filters` must contain
`'csrf' => ['except' => ['sync-api/*']]`.

## 5. Test

```bash
php spark sync:check                  # the nodes reach each other
```

then send a test message:

```bash
php spark sync:notify "test from this node"
```

The text appears in the chat (and in the mailbox when `sync.mail.to` is set). If nothing arrives:

| Symptom | Check |
|---|---|
| Nothing in Telegram | `writable/logs/` for `Telegram:` errors; the queue is drained by the scheduler (`tasks:run` in cron); token and chat id; the bot was started in the chat |
| HTTP 400/403 from Telegram | wrong chat id (a group id is negative) or the bot was removed from the chat |
| Buttons do nothing | `getWebhookInfo` shows `last_error_message`; the webhook secret equals `sync.telegram.webhook_secret`; the URL is HTTPS and public |
| E-mail missing | `Email:` errors in the log; the SMTP host name matches the certificate; port 587 with `tls` or 465 with `ssl` |

## Revoking and rotating

To rotate the token send `/revoke` to BotFather, update `sync.telegram.token` on every node and register the
webhook again. To stop alerts remove `token` and `chat_id`; e-mail keeps working if `sync.mail.*` is set.

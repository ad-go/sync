<?php
/** @var \CodeIgniter\Shield\Entities\User $user */
/** @var string $secret */
/** @var string $qr */
/** @var list<string> $codes */
use AdGo\Sync\Libraries\Branding;
?>
<!doctype html>
<html>
<body style="font-family:system-ui,sans-serif;color:#1c1f26">
<p>Hello <?= esc($user->username) ?>,</p>
<p>Your account (<?= esc($user->email) ?>) was added to the <b>superadmin</b> group on <?= esc(Branding::siteName()) ?>,
which requires a second factor at login.</p>

<p>Scan this code with Google Authenticator, Authy, or any other TOTP app:</p>
<div style="max-width:220px"><?= $qr ?></div>

<p>Can't scan it? Enter this key manually:<br>
<code style="word-break:break-all"><?= esc($secret) ?></code></p>

<p><b>Recovery codes</b> — save these somewhere safe. Each works once, if you ever lose access to your
authenticator app:</p>
<table cellpadding="4" style="font-family:ui-monospace,Consolas,monospace;font-size:13px">
<?php foreach (array_chunk($codes, 2) as $pair): ?>
<tr><td><?= esc($pair[0]) ?></td><td><?= esc($pair[1] ?? '') ?></td></tr>
<?php endforeach ?>
</table>

<p><small>An admin can see this QR code and these recovery codes again later from the Users page ("View 2FA").</small></p>
</body>
</html>

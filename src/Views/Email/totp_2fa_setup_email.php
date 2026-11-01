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
<p><?= lang('Mail.greeting', [esc($user->username)]) ?></p>
<p><?= lang('Mail.added', [esc($user->email), esc(Branding::siteName())]) ?></p>

<p><?= lang('Mail.scan') ?></p>
<div style="max-width:220px"><?= $qr ?></div>

<p><?= lang('Mail.cant_scan') ?><br>
<code style="word-break:break-all"><?= esc($secret) ?></code></p>

<p><?= lang('Mail.codes') ?></p>
<table cellpadding="4" style="font-family:ui-monospace,Consolas,monospace;font-size:13px">
<?php foreach (array_chunk($codes, 2) as $pair): ?>
<tr><td><?= esc($pair[0]) ?></td><td><?= esc($pair[1] ?? '') ?></td></tr>
<?php endforeach ?>
</table>

<p><small><?= lang('Mail.admin_note', [lang('Users.twofa.tooltip')]) ?></small></p>
</body>
</html>

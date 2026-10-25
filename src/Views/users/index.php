<?php
/** @var list<\CodeIgniter\Shield\Entities\User> $users */ /** @var list<string> $groups */ /** @var string $base */
use AdGo\Sync\Filters\AuthWriteLock;

$banner = AuthWriteLock::banner();
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Users · <?= esc(\AdGo\Sync\Libraries\Branding::siteName()) ?></title>
<?= $head ?? '' ?>
<style>
body{font:14px/1.45 system-ui,sans-serif;margin:0;padding:16px;max-width:900px;margin-inline:auto;color:#1c1f26;background:#f6f7f9}
table{border-collapse:collapse;width:100%;background:#fff}th,td{padding:6px 8px;border-bottom:1px solid #dde;text-align:left}
.flash{padding:8px 12px;border-radius:6px;margin-bottom:12px;background:#e7f4ea}.err{background:#fde8e8}.warn{background:#fff4d6}
.off{opacity:.55}td form{display:inline;margin-left:8px}td button.d{background:#b42318;color:#fff;border:0;border-radius:5px;padding:3px 9px;font:inherit;cursor:pointer}
form.add{display:flex;flex-wrap:wrap;gap:6px 10px;align-items:center;margin-top:10px}
form.add input:not([type=checkbox]){border:1px solid #dde;border-radius:5px;padding:5px 7px;font:inherit}
form.add button{background:#1d5fd1;color:#fff;border:0;border-radius:5px;padding:5px 12px;font:inherit;cursor:pointer}.grp label{margin-right:4px}
tr.rowlink{cursor:pointer}tr.rowlink:hover td{background:#eef2fb}
button.s{background:transparent;color:#1d5fd1;border:1px solid #1d5fd1;border-radius:5px;padding:3px 9px;cursor:pointer;font:inherit}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:90;align-items:center;justify-content:center}
.modal-backdrop.open{display:flex}
.modal{background:#fff;color:#1c1f26;border-radius:10px;padding:20px;max-width:420px;width:92vw;max-height:85vh;overflow:auto;box-shadow:0 8px 30px rgba(0,0,0,.3)}
.modal h3{margin-top:0}.modal .qrwrap{max-width:200px;margin:12px auto}.modal .qrwrap svg{width:100%;height:auto}
.modal code.key{word-break:break-all;display:block;background:#f6f7f9;padding:6px 8px;border-radius:6px;margin:6px 0}
.modal .codes{display:grid;grid-template-columns:1fr 1fr;gap:4px;font-family:ui-monospace,Consolas,monospace;font-size:13px;margin-top:8px}
.modal .codes .used{text-decoration:line-through;color:#999}
.modal .close{float:right;background:none;border:0;font-size:18px;cursor:pointer;color:#1c1f26}
@media (prefers-color-scheme:dark){body{background:#14161a;color:#e6e8ec}table{background:#1e2128}th,td{border-color:#343944}.flash{background:#1d3b27}.err{background:#4a2020}.warn{background:#4a3a05}tr.rowlink:hover td{background:#28303e}.modal,.modal code.key{background:#1e2128;color:#e6e8ec}}
<?= view('AdGo\Sync\Views\_adminChrome') ?>
</style>
</head>
<body>
<?= view('AdGo\Sync\Views\_adminTopbar') ?>
<div class="tb-resize" id="cfTbResize"></div>
<div class="cfshell">
<aside class="sidebar" id="cfSidebar"><?= view('AdGo\Sync\Views\_adminNav', ['active' => 'users']) ?><?= $menu ?? '' ?></aside>
<div class="sb-resize" id="cfSbResize"></div>
<main class="appmain">
<h1>Users</h1>
<?php if (session()->getFlashdata('message')): ?><div class="flash"><?= esc(session()->getFlashdata('message')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')): ?><div class="flash err"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<?php if ($banner !== ''): ?><div class="flash warn">&#128274; <?= esc($banner) ?></div><?php endif ?>

<table>
<thead><tr><th>#</th><th>Username</th><th>Email</th><th>Groups</th><th>Active</th><th></th></tr></thead>
<tbody>
<?php foreach ($users as $u): ?>
<?= view('AdGo\Sync\Views\users\_row', ['u' => $u, 'readonly' => $banner !== '', 'base' => $base]) ?>
<?php endforeach ?>
<?php if ($users === []): ?><tr><td colspan="6">No users.</td></tr><?php endif ?>
</tbody>
</table>

<?php if ($banner === ''): ?>
<h3>Add user</h3>
<form method="post" action="<?= site_url($base) ?>" class="add">
  <?= csrf_field() ?>
  <input name="username" placeholder="username" maxlength="30" required>
  <input name="email" type="email" placeholder="e-mail" maxlength="254" required autocomplete="off">
  <input name="password" type="password" placeholder="password" autocomplete="new-password" required>
  <span class="grp"><?php foreach ($groups as $g): ?><label><input type="checkbox" name="groups[]" value="<?= esc($g) ?>" <?= $g === 'user' ? 'checked' : '' ?>> <?= esc($g) ?></label> <?php endforeach ?></span>
  <label><input type="checkbox" name="active" value="1" checked> active</label>
  <button>Add</button>
</form>
<?php endif ?>

<div class="modal-backdrop" id="twofaBackdrop">
  <div class="modal">
    <button type="button" class="close" id="twofaClose" aria-label="Close">&times;</button>
    <h3>Authenticator (2FA)</h3>
    <div id="twofaBody">Loading&hellip;</div>
  </div>
</div>
</main>
</div>
<?= view('AdGo\Sync\Views\_adminShellJs') ?>
<script>
(function () {
  'use strict';
  var backdrop = document.getElementById('twofaBackdrop');
  var body     = document.getElementById('twofaBody');

  function openModal(id) {
    body.textContent = 'Loading…';
    backdrop.classList.add('open');
    fetch(<?= json_encode(site_url($base)) ?> + '/' + id + '/2fa', { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) { body.textContent = data.error; return; }
        var html = '<div class="qrwrap">' + data.qr + '</div>';
        html += '<p><small>Manual key:</small><code class="key">' + data.secret.replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }) + '</code></p>';
        html += '<p><small>Recovery codes (struck through = already used):</small></p><div class="codes">';
        data.codes.forEach(function (c) {
          html += '<div class="' + (c.used ? 'used' : '') + '">' + c.code + '</div>';
        });
        html += '</div>';
        body.innerHTML = html;
      })
      .catch(function () { body.textContent = 'Could not load.'; });
  }

  document.querySelector('table').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-view2fa]');
    if (btn) { openModal(btn.getAttribute('data-view2fa')); return; }
    if (e.target.closest('a,button,form,input')) { return; }
    var tr = e.target.closest('tr[data-edit-url]');
    if (tr) { window.location.href = tr.getAttribute('data-edit-url'); }
  });

  document.getElementById('twofaClose').addEventListener('click', function () { backdrop.classList.remove('open'); });
  backdrop.addEventListener('click', function (e) { if (e.target === backdrop) { backdrop.classList.remove('open'); } });
})();
</script>
</body>
</html>

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
<?= view('AdGo\Sync\Views\ui\_theme') ?>
<style>
.off{opacity:.55}
td form{display:inline;margin-left:8px}
form.add{display:flex;flex-wrap:wrap;gap:6px 10px;align-items:center;margin-top:10px}
form.add input[type=text],form.add input[type=email],form.add input[type=password]{width:160px}
form.add button{padding:5px 12px}
.grp label{margin-right:4px}
tr.rowlink{cursor:pointer}
table,form.add{max-width:100%}
</style>
</head>
<body>
<?= view('AdGo\Sync\Views\ui\_topbar') ?>
<div class="appshell">
<aside id="appSidebar"><?= view('AdGo\Sync\Views\ui\_nav', ['active' => 'users']) ?><?= $menu ?? '' ?></aside>
<div class="sb-resize" id="sbResize"></div>
<main class="appmain">
<h1>Users</h1>
<?= view('AdGo\Sync\Views\ui\_flash') ?>
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
  <input type="text" name="username" placeholder="username" maxlength="30" required>
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

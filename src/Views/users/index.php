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
form.add button{padding:5px 12px}
.grp label{margin-right:4px}
tr.rowlink{cursor:pointer}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:90;align-items:center;justify-content:center}
.modal-backdrop.open{display:flex}
.modal{background:var(--card);color:var(--fg);border-radius:var(--r);padding:20px;max-width:420px;width:92vw;max-height:85vh;overflow:auto;box-shadow:var(--shadow)}
.modal h3{margin-top:0}.modal .qrwrap{max-width:200px;margin:12px auto}.modal .qrwrap svg{width:100%;height:auto}
.modal code.key{word-break:break-all;display:block;background:var(--tint);padding:6px 8px;border-radius:6px;margin:6px 0}
.modal .codes{display:grid;grid-template-columns:1fr 1fr;gap:4px;font-family:ui-monospace,Consolas,monospace;font-size:13px;margin-top:8px}
.modal .codes .used{text-decoration:line-through;color:var(--mute)}
.modal .close{float:right;background:none;border:0;font-size:18px;cursor:pointer;color:var(--fg)}
</style>
</head>
<body>
<?= view('AdGo\Sync\Views\ui\_topbar') ?>
<div class="appshell">
<aside id="appSidebar"><?= view('AdGo\Sync\Views\ui\_nav', ['active' => 'users']) ?><?= $menu ?? '' ?></aside>
<div class="sb-resize" id="sbResize"></div>
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
<script>!function(){var s=document.getElementById('appSidebar'),r=document.getElementById('sbResize');if(!s||!r)return;try{var w=localStorage.getItem('appshell-sw');if(w)s.style.width=w;}catch(e){}var d=!1,x,W;r.addEventListener('mousedown',function(e){d=!0;x=e.clientX;W=s.offsetWidth;document.body.style.cssText+='cursor:col-resize;user-select:none'});document.addEventListener('mousemove',function(e){if(!d)return;var n=Math.max(150,Math.min(400,W+e.clientX-x));s.style.width=n+'px'});document.addEventListener('mouseup',function(){if(!d)return;d=!1;document.body.style.cursor='';document.body.style.userSelect='';try{localStorage.setItem('appshell-sw',s.style.width);}catch(e){}});}();</script>
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

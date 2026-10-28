<?php
/** @var \CodeIgniter\Shield\Entities\User $user */ /** @var list<string> $groups */ /** @var string $base */ /** @var string $node */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit user · <?= esc(\AdGo\Sync\Libraries\Branding::siteName()) ?></title>
<?= view('AdGo\Sync\Views\ui\_theme') ?>
<style>
form.edit{background:var(--card);padding:14px;border:1px solid var(--line);border-radius:var(--r);max-width:560px}
label{display:block;margin:8px 0}
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
<h1>Edit user #<?= (int) $user->id ?></h1>
<?php if (session()->getFlashdata('error')): ?><div class="flash err"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<p class="note">Opening this page took the edit lease on <b><?= esc($node ?? config('Sync')->node) ?></b>; the other node shows this user as read-only until you save or leave it idle.</p>
<?php if (in_array('superadmin', $user->getGroups() ?? [], true)): ?>
<p><button type="button" class="s" id="view2fa">View 2FA</button></p>
<?php endif ?>
<form method="post" action="<?= site_url($base . '/' . (int) $user->id) ?>" class="edit">
  <?= csrf_field() ?>
  <label>Username <input type="text" name="username" value="<?= esc($user->username) ?>" maxlength="30" required></label>
  <label>E-mail <input type="email" name="email" value="<?= esc((string) $user->email) ?>" maxlength="254" required autocomplete="off"></label>
  <label>New password <input type="password" name="password" autocomplete="new-password" placeholder="leave empty to keep the current one"></label>
  <label><input type="checkbox" name="active" value="1" <?= $user->active ? 'checked' : '' ?>> active</label>
  <fieldset><legend>Groups</legend>
  <?php $mine = $user->getGroups() ?? []; foreach ($groups as $g): ?>
    <label><input type="checkbox" name="groups[]" value="<?= esc($g) ?>" <?= in_array($g, $mine, true) ? 'checked' : '' ?>> <?= esc($g) ?></label>
  <?php endforeach ?>
  </fieldset>
  <button>Save</button>
</form>

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
<?php if (in_array('superadmin', $user->getGroups() ?? [], true)): ?>
<script>
(function () {
  'use strict';
  var backdrop = document.getElementById('twofaBackdrop');
  var body     = document.getElementById('twofaBody');

  document.getElementById('view2fa').addEventListener('click', function () {
    body.textContent = 'Loading…';
    backdrop.classList.add('open');
    fetch(<?= json_encode(site_url($base . '/' . (int) $user->id . '/2fa')) ?>, { headers: { Accept: 'application/json' } })
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
  });

  document.getElementById('twofaClose').addEventListener('click', function () { backdrop.classList.remove('open'); });
  backdrop.addEventListener('click', function (e) { if (e.target === backdrop) { backdrop.classList.remove('open'); } });
})();
</script>
<?php endif ?>
</body>
</html>

<?php
/** @var string $node */ /** @var array<string, array<string, mixed>> $fields */ /** @var string $siteName */
/** @var string $logo */ /** @var string $accentColor */ /** @var string $fontFamily */ /** @var int $fontSize */
/** @var array<string,string> $fonts */ /** @var int $maxImageMb */ /** @var string $secretHash */ /** @var string $peerNode */
/** @var string $base */
$groups = [];

foreach ($fields as $key => $f) {
    $groups[$f['group']][$key] = $f;
}
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings · <?= esc($siteName) ?></title>
<?= view('AdGo\Sync\Views\ui\_theme') ?>
<style>
fieldset{background:var(--card);padding:14px;border:1px solid var(--line);border-radius:var(--r);margin-bottom:16px}
legend{font-weight:700}
label{display:block;margin:8px 0}
.logo-preview{max-height:40px;display:block;margin-bottom:8px}
.fp{font-family:ui-monospace,Consolas,monospace;font-size:12px;color:var(--mute)}
</style>
</head>
<body>
<?= view('AdGo\Sync\Views\ui\_topbar') ?>
<div class="appshell">
<aside id="appSidebar"><?= view('AdGo\Sync\Views\ui\_nav', ['active' => 'settings']) ?><?= $menu ?? '' ?></aside>
<div class="sb-resize" id="sbResize"></div>
<main class="appmain">
<h1>Settings</h1>
<?php if (session()->getFlashdata('message')): ?><div class="flash"><?= esc(session()->getFlashdata('message')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')): ?><div class="flash err"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>

<fieldset>
<legend>Branding</legend>
<form method="post" action="<?= site_url($base . '/branding') ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <label>Site name <input type="text" name="siteName" value="<?= esc($siteName) ?>" maxlength="80" required></label>
  <label>Accent colour <input type="text" name="accentColor" value="<?= esc($accentColor) ?>" placeholder="#1d5fd1" pattern="#[0-9a-fA-F]{6}"></label>
  <label>Font
    <select name="fontFamily">
      <option value="">(default)</option>
      <?php foreach ($fonts as $key => $css): ?><option value="<?= esc($key) ?>" <?= $key === $fontFamily ? 'selected' : '' ?>><?= esc($key) ?></option><?php endforeach ?>
    </select>
  </label>
  <label>Base font size (px) <input type="number" name="fontSize" min="12" max="20" value="<?= $fontSize > 0 ? $fontSize : '' ?>" placeholder="14"></label>
  <label>Logo
    <?php if ($logo !== ''): ?><img class="logo-preview" src="<?= esc($logo) ?>" alt=""><?php endif ?>
    <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp">
    <span class="hint">PNG, JPG, SVG or WebP, up to <?= (int) $maxImageMb ?> MiB.</span>
  </label>
  <button>Save</button>
</form>
<?php if ($logo !== ''): ?>
<form method="post" action="<?= site_url($base . '/logo/remove') ?>" style="margin-top:6px"><?= csrf_field() ?><button class="s">Remove logo</button></form>
<?php endif ?>
</fieldset>

<fieldset>
<legend>Node identity</legend>
<p class="hint">Renaming a node, or rotating the secret, only changes <b><?= esc($node) ?></b>; apply the matching value on the peer by hand.</p>
<p>Current secret fingerprint: <span class="fp"><?= esc($secretHash) ?></span></p>
<form method="post" action="<?= site_url($base . '/identity') ?>">
  <?= csrf_field() ?>
  <label>This node's name <input type="text" name="node" value="<?= esc($node) ?>" pattern="[a-z0-9][a-z0-9_-]{0,31}"></label>
  <label>Peer node's name <input type="text" name="peerNode" value="<?= esc($peerNode) ?>" pattern="[a-z0-9][a-z0-9_-]{0,31}"></label>
  <label>New shared secret <input type="text" name="secret" placeholder="leave empty to keep the current one" pattern="[A-Za-z0-9]{32,128}"></label>
  <button>Save</button>
</form>
</fieldset>

<?php foreach ($groups as $groupName => $items): if ($groupName === 'Application') { continue; } ?>
<fieldset>
<legend><?= esc($groupName) ?></legend>
<form method="post" action="<?= site_url($base . '/env') ?>">
  <?= csrf_field() ?>
  <?php foreach ($items as $key => $f): $id = str_replace('.', '__', $key); ?>
  <label><?= esc($f['label']) ?>
    <?php if ($f['type'] === 'bool'): ?>
      <select name="<?= $id ?>"><option value="true" <?= $f['value'] === 'true' ? 'selected' : '' ?>>true</option><option value="false" <?= $f['value'] === 'false' ? 'selected' : '' ?>>false</option></select>
    <?php elseif ($f['type'] === 'choice'): ?>
      <select name="<?= $id ?>"><?php foreach ($f['options'] as $o): ?><option value="<?= esc($o) ?>" <?= $f['value'] === $o ? 'selected' : '' ?>><?= esc($o) ?></option><?php endforeach ?></select>
    <?php elseif ($f['type'] === 'secret'): ?>
      <input type="password" name="<?= $id ?>" placeholder="<?= $f['set'] ? 'set — leave empty to keep it' : 'not set' ?>" autocomplete="off">
    <?php else: ?>
      <input type="text" name="<?= $id ?>" value="<?= esc($f['value']) ?>">
    <?php endif ?>
  </label>
  <?php if ($f['hint'] !== ''): ?><p class="hint"><?= esc($f['hint']) ?></p><?php endif ?>
  <?php endforeach ?>
  <button>Save</button>
</form>
</fieldset>
<?php endforeach ?>
</main>
</div>
<script>!function(){var s=document.getElementById('appSidebar'),r=document.getElementById('sbResize');if(!s||!r)return;try{var w=localStorage.getItem('appshell-sw');if(w)s.style.width=w;}catch(e){}var d=!1,x,W;r.addEventListener('mousedown',function(e){d=!0;x=e.clientX;W=s.offsetWidth;document.body.style.cssText+='cursor:col-resize;user-select:none'});document.addEventListener('mousemove',function(e){if(!d)return;var n=Math.max(150,Math.min(400,W+e.clientX-x));s.style.width=n+'px'});document.addEventListener('mouseup',function(){if(!d)return;d=!1;document.body.style.cursor='';document.body.style.userSelect='';try{localStorage.setItem('appshell-sw',s.style.width);}catch(e){}});}();</script>
</body>
</html>

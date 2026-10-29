<?php
/** @var string $node */ /** @var array<string, array<string, mixed>> $fields */ /** @var string $siteName */
/** @var string $logo */ /** @var string $accentColor */ /** @var string $fontFamily */ /** @var int $fontSize */
/** @var array<string,string> $fonts */ /** @var int $maxImageMb */ /** @var string $secretHash */ /** @var string $peerNode */
/** @var string $base */
$envGroups = [];
foreach ($fields as $key => $f) {
    if ($f['group'] === 'Application') continue;
    $envGroups[$f['group']][$key] = $f;
}
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings · <?= esc($siteName) ?></title>
<?= view('AdGo\Sync\Views\ui\_theme') ?>
<?= $head ?? '' ?>
<style>
.sg{display:grid;grid-template-columns:1fr 1fr;gap:10px 16px}
.sg.c3{grid-template-columns:1fr 1fr 1fr}
.sg .full{grid-column:1/-1}
.sf{display:flex;flex-direction:column;gap:3px}
.sf label{font-size:11px;font-weight:700;color:var(--mute);text-transform:uppercase;letter-spacing:.04em}
.sf input,.sf select,.sf textarea{padding:6px 10px;border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--fg);font-size:14px;width:100%;box-sizing:border-box}
.sf input:focus,.sf select:focus{outline:none;border-color:var(--acc);box-shadow:0 0 0 3px color-mix(in srgb,var(--acc) 18%,transparent)}
.color-row{display:flex;gap:8px;align-items:center}
.color-row input[type=color]{width:38px;height:38px;padding:2px;border:1px solid var(--line);border-radius:8px;cursor:pointer;flex-shrink:0;background:none}
.color-row input[type=text]{flex:1}
.logo-drop{border:2px dashed var(--line);border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:border-color .15s;position:relative}
.logo-drop:hover,.logo-drop.drag{border-color:var(--acc)}
.logo-drop input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.logo-drop img{max-height:48px;display:block;margin:0 auto 8px}
.logo-drop .drop-hint{font-size:12px;color:var(--mute)}
.fp{font-family:ui-monospace,Consolas,monospace;font-size:12px;color:var(--mute)}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:16px;margin-bottom:16px}
.card h2{margin:0 0 14px;font-size:15px;font-weight:700;border-bottom:1px solid var(--line);padding-bottom:10px}
.row-actions{display:flex;gap:8px;margin-top:14px;align-items:center}
</style>
</head>
<body>
<?= view('AdGo\Sync\Views\ui\_topbar') ?>
<div class="appshell">
<aside id="appSidebar"><?= view('AdGo\Sync\Views\ui\_nav', ['active' => 'settings']) ?><?= $menu ?? '' ?></aside>
<div class="sb-resize" id="sbResize"></div>
<main class="appmain">
<h1>Settings</h1>
<?= view('AdGo\Sync\Views\ui\_flash') ?>

<div class="card">
  <h2>Branding</h2>
  <form method="post" action="<?= site_url($base . '/branding') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="sg">
      <div class="sf">
        <label>Site name</label>
        <input type="text" name="siteName" value="<?= esc($siteName) ?>" maxlength="80" required>
      </div>
      <div class="sf">
        <label>Accent colour</label>
        <div class="color-row">
          <input type="color" id="colorPicker" value="<?= esc($accentColor ?: '#1d5fd1') ?>">
          <input type="text" name="accentColor" id="colorText" value="<?= esc($accentColor) ?>" placeholder="#1d5fd1" pattern="#[0-9a-fA-F]{6}">
        </div>
      </div>
      <div class="sf">
        <label>Font</label>
        <select name="fontFamily">
          <option value="">(default)</option>
          <?php foreach ($fonts as $key => $css): ?>
          <option value="<?= esc($key) ?>" <?= $key === $fontFamily ? 'selected' : '' ?>><?= esc($key) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="sf">
        <label>Font size (px)</label>
        <input type="number" name="fontSize" min="12" max="20" value="<?= $fontSize > 0 ? $fontSize : '' ?>" placeholder="14">
      </div>
      <div class="sf full">
        <label>Logo</label>
        <div class="logo-drop" id="logoDrop">
          <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" id="logoFile">
          <?php if ($logo !== ''): ?>
          <img src="<?= esc($logo) ?>" alt="" id="logoPreview">
          <div class="drop-hint" id="logoHint">Click or drag to replace</div>
          <?php else: ?>
          <img src="" alt="" id="logoPreview" style="display:none">
          <div class="drop-hint" id="logoHint"><i class="bi bi-image"></i> Click or drag to upload &middot; PNG, JPG, SVG, WebP &middot; max <?= (int) $maxImageMb ?> MiB</div>
          <?php endif ?>
        </div>
      </div>
    </div>
    <div class="row-actions">
      <button><i class="bi bi-check-lg"></i> Save branding</button>
    </div>
  </form>
  <?php if ($logo !== ''): ?>
  <form method="post" action="<?= site_url($base . '/logo/remove') ?>" style="margin-top:6px"><?= csrf_field() ?><button class="s"><i class="bi bi-x-lg"></i> Remove logo</button></form>
  <?php endif ?>
</div>

<div class="card">
  <h2>Node identity</h2>
  <p class="hint" style="margin:0 0 12px">Renaming this node or rotating the secret only changes <b><?= esc($node) ?></b>; apply the matching value on the peer by hand. Secret fingerprint: <span class="fp"><?= esc($secretHash) ?></span></p>
  <form method="post" action="<?= site_url($base . '/identity') ?>">
    <?= csrf_field() ?>
    <div class="sg">
      <div class="sf">
        <label>This node's name</label>
        <input type="text" name="node" value="<?= esc($node) ?>" pattern="[a-z0-9][a-z0-9_-]{0,31}">
      </div>
      <div class="sf">
        <label>Peer node's name</label>
        <input type="text" name="peerNode" value="<?= esc($peerNode) ?>" pattern="[a-z0-9][a-z0-9_-]{0,31}">
      </div>
      <div class="sf full">
        <label>New shared secret</label>
        <input type="text" name="secret" placeholder="leave empty to keep current" pattern="[A-Za-z0-9]{32,128}">
      </div>
    </div>
    <div class="row-actions"><button><i class="bi bi-check-lg"></i> Save identity</button></div>
  </form>
</div>

<?php foreach ($envGroups as $groupName => $items): ?>
<div class="card">
  <h2><?= esc($groupName) ?></h2>
  <form method="post" action="<?= site_url($base . '/env') ?>">
    <?= csrf_field() ?>
    <div class="sg<?= count($items) >= 4 ? ' c3' : '' ?>">
    <?php foreach ($items as $key => $f): $id = str_replace('.', '__', $key); ?>
      <div class="sf">
        <label><?= esc($f['label']) ?></label>
        <?php if ($f['type'] === 'bool'): ?>
          <select name="<?= $id ?>"><option value="true" <?= $f['value'] === 'true' ? 'selected' : '' ?>>true</option><option value="false" <?= $f['value'] === 'false' ? 'selected' : '' ?>>false</option></select>
        <?php elseif ($f['type'] === 'choice'): ?>
          <select name="<?= $id ?>"><?php foreach ($f['options'] as $o): ?><option value="<?= esc($o) ?>" <?= $f['value'] === $o ? 'selected' : '' ?>><?= esc($o) ?></option><?php endforeach ?></select>
        <?php elseif ($f['type'] === 'secret'): ?>
          <input type="password" name="<?= $id ?>" placeholder="<?= $f['set'] ? 'set — leave blank to keep' : 'not set' ?>" autocomplete="off">
        <?php else: ?>
          <input type="text" name="<?= $id ?>" value="<?= esc($f['value']) ?>">
        <?php endif ?>
        <?php if ($f['hint'] !== ''): ?><span class="hint" style="font-size:11px"><?= esc($f['hint']) ?></span><?php endif ?>
      </div>
    <?php endforeach ?>
    </div>
    <div class="row-actions"><button><i class="bi bi-check-lg"></i> Save</button></div>
  </form>
</div>
<?php endforeach ?>

</main>
</div>
<script>
(function(){
  var picker = document.getElementById('colorPicker');
  var text   = document.getElementById('colorText');
  if (picker && text) {
    picker.addEventListener('input', function(){ text.value = picker.value; });
    text.addEventListener('input', function(){
      if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value;
    });
  }
  var drop    = document.getElementById('logoDrop');
  var file    = document.getElementById('logoFile');
  var preview = document.getElementById('logoPreview');
  var hint    = document.getElementById('logoHint');
  if (file && preview && hint) {
    file.addEventListener('change', function(){
      var f = file.files[0];
      if (!f) return;
      hint.textContent = f.name;
      var reader = new FileReader();
      reader.onload = function(e){ preview.src = e.target.result; preview.style.display = 'block'; };
      reader.readAsDataURL(f);
    });
    drop.addEventListener('dragover', function(e){ e.preventDefault(); drop.classList.add('drag'); });
    drop.addEventListener('dragleave', function(){ drop.classList.remove('drag'); });
    drop.addEventListener('drop', function(e){
      e.preventDefault(); drop.classList.remove('drag');
      if (e.dataTransfer.files.length) { file.files = e.dataTransfer.files; file.dispatchEvent(new Event('change')); }
    });
  }
})();
</script>
</body>
</html>

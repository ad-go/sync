<?php
/** @var string $node */ /** @var array<string, array<string, mixed>> $fields */ /** @var string $siteName */
/** @var string $logo */ /** @var string $accentColor */ /** @var string $fontFamily */ /** @var int $fontSize */
/** @var array<string,string> $fonts */ /** @var string $loginField */ /** @var int $maxImageMb */ /** @var string $secretHash */ /** @var string $peerNode */
/** @var string $base */
$cfg = config('Sync');
$envGroups    = [];
$topEnvGroups = [];
foreach ($fields as $key => $f) {
    if ($f['group'] === 'Application') continue;
    if ($f['group'] === 'Database') {
        $topEnvGroups[$f['group']][$key] = $f;
    } else {
        $envGroups[$f['group']][$key] = $f;
    }
}
$title = 'Settings · ' . esc($siteName);
ob_start();
?>
<style>
/* ── tabs ───────────────────────────────────────────────── */
.stabs{display:flex;gap:0;border-bottom:2px solid var(--line);margin-bottom:14px}
.stab{background:transparent;border:1px solid transparent;border-bottom:none;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;color:var(--mute);border-radius:6px 6px 0 0;margin-bottom:-2px;transition:color .12s}
.stab:hover{color:var(--fg)}
.stab.active{color:var(--acc);background:var(--card);border-color:var(--line)}
.spanel{display:none}
.spanel.active{display:block}
.stab-iframe{width:100%;height:calc(100vh - 130px);border:none;display:block}
/* ── page grids ─────────────────────────────────────────── */
.s-top{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;align-items:start;margin-bottom:14px}
.s-env{display:grid;grid-template-columns:1fr 1fr;gap:14px}
/* ── card ───────────────────────────────────────────────── */
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:14px 16px}
.card h2{margin:0 0 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--mute);border-bottom:1px solid var(--line);padding-bottom:7px}
/* ── field grid ─────────────────────────────────────────── */
.sg{display:grid;grid-template-columns:1fr 1fr;gap:10px 14px}
.sg.c1{grid-template-columns:1fr}
.sg.c3{grid-template-columns:1fr 1fr 1fr}
.sg.narrow{grid-template-columns:repeat(auto-fill,minmax(120px,160px))}
.sg .full{grid-column:1/-1}
/* ── field ──────────────────────────────────────────────── */
.sf{display:flex;flex-direction:column;gap:3px}
.sf label{font-size:11px;font-weight:700;color:var(--mute);text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sf input,.sf select,.sf textarea{padding:7px 10px;border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--fg);font-size:13px;width:100%;box-sizing:border-box}
.sf input:focus,.sf select:focus,.sf textarea:focus{outline:none;border-color:var(--acc);box-shadow:0 0 0 3px color-mix(in srgb,var(--acc) 18%,transparent)}
.sf input.sm{max-width:90px;width:90px}
.sf-hint{font-size:11px;color:var(--mute);margin:1px 0 0;line-height:1.3}
/* ── color picker ───────────────────────────────────────── */
.color-row{display:flex;gap:8px;align-items:center}
.color-swatch{flex-shrink:0;width:40px;height:40px;border-radius:8px;border:1px solid var(--line);overflow:hidden;cursor:pointer;position:relative}
.color-swatch input[type=color]{position:absolute;inset:-4px;width:calc(100% + 8px);height:calc(100% + 8px);border:none;padding:0;cursor:pointer;opacity:1}
.color-row .hex{flex:1}
/* ── logo drop zone ─────────────────────────────────────── */
.logo-drop{border:2px dashed var(--line);border-radius:10px;padding:18px 14px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;min-height:96px}
.logo-drop:hover,.logo-drop.drag{border-color:var(--acc);background:var(--tint)}
.logo-drop input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.logo-drop img{max-height:52px;max-width:180px;display:block;border-radius:4px}
.logo-drop .drop-ico{font-size:28px;color:var(--mute);line-height:1;pointer-events:none}
.logo-drop .drop-title{font-size:13px;font-weight:600;color:var(--fg);pointer-events:none}
.logo-drop .drop-meta{font-size:11px;color:var(--mute);pointer-events:none}
/* ── row actions ────────────────────────────────────────── */
.row-actions{display:flex;gap:8px;margin-top:12px;align-items:center;flex-wrap:wrap}
/* ── misc ───────────────────────────────────────────────── */
.fp{font-family:ui-monospace,Consolas,monospace;font-size:11px;color:var(--mute);word-break:break-all}
/* ── responsive ─────────────────────────────────────────── */
@media(max-width:900px){.s-top{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.s-top,.s-env{grid-template-columns:1fr}}
@media(max-width:680px){.sg,.sg.c3{grid-template-columns:1fr}.sg .full,.sg .span2{grid-column:1}}
</style>
<?php $head = ob_get_clean(); ob_start(); ?>

<div class="stabs">
  <button class="stab active" data-tab="t-settings"><?= lang('Sync.nav.settings') ?></button>
  <button class="stab" data-tab="t-conflicts"><?= lang('Sync.nav.conflicts') ?></button>
  <button class="stab" data-tab="t-users"><?= lang('Sync.nav.users') ?></button>
</div>

<!-- ── Settings panel ──────────────────────────────────── -->
<div id="t-settings" class="spanel active">

<div class="s-top">

  <!-- ── Branding ─────────────────────────────────── -->
  <div class="card">
    <h2>Branding</h2>
    <form method="post" action="<?= site_url($base . '/branding') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="sg narrow">

        <div class="sf">
          <label>Site name</label>
          <input type="text" name="siteName" value="<?= esc($siteName) ?>" maxlength="80" required>
        </div>

        <div class="sf">
          <label>Accent colour</label>
          <div class="color-row">
            <div class="color-swatch" title="Open colour picker">
              <input type="color" id="colorPicker" value="<?= esc($accentColor ?: '#1d5fd1') ?>">
            </div>
            <input type="text" name="accentColor" id="colorText" class="hex"
                   value="<?= esc($accentColor) ?>" placeholder="#1d5fd1"
                   pattern="#[0-9a-fA-F]{6}" maxlength="7" spellcheck="false">
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
          <input type="number" name="fontSize" class="sm" min="12" max="20"
                 value="<?= $fontSize > 0 ? $fontSize : '' ?>" placeholder="14">
        </div>

        <div class="sf">
          <label>Login field</label>
          <select name="loginField">
            <option value="email"    <?= $loginField === 'email'    ? 'selected' : '' ?>>E-mail</option>
            <option value="username" <?= $loginField === 'username' ? 'selected' : '' ?>>Username</option>
            <option value="both"     <?= $loginField === 'both'     ? 'selected' : '' ?>>E-mail or username</option>
          </select>
        </div>

        <div class="sf full">
          <label>Logo</label>
          <div class="logo-drop" id="logoDrop">
            <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" id="logoFile">
            <?php if ($logo !== ''): ?>
              <img src="<?= esc($logo) ?>" alt="" id="logoPreview">
              <div class="drop-meta" id="logoHint">Click or drag to replace</div>
            <?php else: ?>
              <img src="" alt="" id="logoPreview" style="display:none">
              <i class="bi bi-image drop-ico" id="logoIcon"></i>
              <div class="drop-title" id="logoTitle">Click or drag to upload</div>
              <div class="drop-meta" id="logoHint">PNG · JPG · SVG · WebP &nbsp;·&nbsp; max <?= (int) $maxImageMb ?> MiB</div>
            <?php endif ?>
          </div>
        </div>

      </div><!-- .sg -->
      <div class="row-actions">
        <button><i class="bi bi-check-lg"></i> Save branding</button>
        <?php if ($logo !== ''): ?>
        <button type="button" id="rmLogoBtn" class="s"><i class="bi bi-x-lg"></i> Remove logo</button>
        <?php endif ?>
      </div>
    </form>
    <?php if ($logo !== ''): ?>
    <form method="post" action="<?= site_url($base . '/logo/remove') ?>" id="rmLogoForm" style="display:none"><?= csrf_field() ?></form>
    <?php endif ?>
  </div><!-- .card Branding -->

  <!-- ── Node identity ────────────────────────────── -->
  <div class="card">
    <h2>Node identity</h2>
    <p class="sf-hint" style="margin:0 0 10px">
      Changes apply locally only — update the peer by hand with matching values.<br>
      Secret fingerprint: <span class="fp"><?= esc($secretHash) ?></span>
    </p>
    <form method="post" action="<?= site_url($base . '/identity') ?>">
      <?= csrf_field() ?>
      <div class="sg">
        <div class="sf">
          <label>This node</label>
          <input type="text" name="node" value="<?= esc($node) ?>" pattern="[a-z0-9][a-z0-9_-]{0,31}" spellcheck="false">
        </div>
        <div class="sf">
          <label>Peer node</label>
          <input type="text" name="peerNode" value="<?= esc($peerNode) ?>" pattern="[a-z0-9][a-z0-9_-]{0,31}" spellcheck="false">
        </div>
        <div class="sf full">
          <label>New shared secret</label>
          <input type="text" name="secret" placeholder="leave blank to keep current"
                 pattern="[A-Za-z0-9]{32,128}" autocomplete="off" spellcheck="false">
        </div>
      </div>
      <div class="row-actions"><button><i class="bi bi-check-lg"></i> Save</button></div>
    </form>
  </div><!-- .card Node identity -->


<?php foreach ($topEnvGroups as $groupName => $items):
    $count = count($items);
    $cols  = $count >= 6 ? 'c3' : ($count >= 3 ? '' : 'c1');
?>
  <div class="card">
    <h2><?= esc($groupName) ?></h2>
    <form method="post" action="<?= site_url($base . '/env') ?>">
      <?= csrf_field() ?>
      <div class="sg <?= $cols ?>">
      <?php foreach ($items as $key => $f):
          $id = str_replace('.', '__', $key); ?>
        <div class="sf">
          <label title="<?= esc($key) ?>"><?= esc($f['label']) ?></label>
          <?php if ($f['type'] === 'bool'): ?>
            <select name="<?= $id ?>">
              <option value="true"  <?= $f['value'] === 'true'  ? 'selected' : '' ?>>true</option>
              <option value="false" <?= $f['value'] === 'false' ? 'selected' : '' ?>>false</option>
            </select>
          <?php elseif ($f['type'] === 'choice'): ?>
            <select name="<?= $id ?>">
              <?php foreach ($f['options'] as $o): ?>
              <option value="<?= esc($o) ?>" <?= $f['value'] === $o ? 'selected' : '' ?>><?= esc($o) ?></option>
              <?php endforeach ?>
            </select>
          <?php elseif ($f['type'] === 'secret'): ?>
            <input type="password" name="<?= $id ?>"
                   placeholder="<?= $f['set'] ? 'set — blank to keep' : 'not set' ?>"
                   autocomplete="off">
          <?php elseif ($f['type'] === 'int'): ?>
            <input type="number" name="<?= $id ?>" class="sm"
                   value="<?= esc($f['value']) ?>"
                   min="<?= (int) ($f['min'] ?? 0) ?>" max="<?= (int) ($f['max'] ?? 9999) ?>">
          <?php else: ?>
            <input type="text" name="<?= $id ?>" value="<?= esc($f['value']) ?>">
          <?php endif ?>
          <?php if ($f['hint'] !== ''): ?>
          <span class="sf-hint"><?= esc($f['hint']) ?></span>
          <?php endif ?>
        </div>
      <?php endforeach ?>
      </div>
      <div class="row-actions"><button><i class="bi bi-check-lg"></i> Save</button></div>
    </form>
  </div>
<?php endforeach ?>

</div><!-- .s-top -->

<!-- ── Env groups ──────────────────────────────────────── -->
<div class="s-env">
<?php foreach ($envGroups as $groupName => $items):
    $count = count($items);
    $cols  = $count >= 6 ? 'c3' : ($count >= 3 ? '' : 'c1');
?>
<div class="card">
  <h2><?= esc($groupName) ?></h2>
  <form method="post" action="<?= site_url($base . '/env') ?>">
    <?= csrf_field() ?>
    <div class="sg <?= $cols ?>">
    <?php foreach ($items as $key => $f):
        $id   = str_replace('.', '__', $key);
    ?>
      <div class="sf">
        <label title="<?= esc($key) ?>"><?= esc($f['label']) ?></label>
        <?php if ($f['type'] === 'bool'): ?>
          <select name="<?= $id ?>">
            <option value="true"  <?= $f['value'] === 'true'  ? 'selected' : '' ?>>true</option>
            <option value="false" <?= $f['value'] === 'false' ? 'selected' : '' ?>>false</option>
          </select>
        <?php elseif ($f['type'] === 'choice'): ?>
          <select name="<?= $id ?>">
            <?php foreach ($f['options'] as $o): ?>
            <option value="<?= esc($o) ?>" <?= $f['value'] === $o ? 'selected' : '' ?>><?= esc($o) ?></option>
            <?php endforeach ?>
          </select>
        <?php elseif ($f['type'] === 'secret'): ?>
          <input type="password" name="<?= $id ?>"
                 placeholder="<?= $f['set'] ? 'set — blank to keep' : 'not set' ?>"
                 autocomplete="off">
        <?php elseif ($f['type'] === 'int'): ?>
          <input type="number" name="<?= $id ?>" class="sm"
                 value="<?= esc($f['value']) ?>"
                 min="<?= (int) ($f['min'] ?? 0) ?>" max="<?= (int) ($f['max'] ?? 9999) ?>">
        <?php else: ?>
          <input type="text" name="<?= $id ?>" value="<?= esc($f['value']) ?>"
                 <?= ($f['type'] === 'url' || $f['type'] === 'baseurl') ? 'inputmode="url"' : '' ?>>
        <?php endif ?>
        <?php if ($f['hint'] !== ''): ?>
        <span class="sf-hint"><?= esc($f['hint']) ?></span>
        <?php endif ?>
      </div>
    <?php endforeach ?>
    </div>
    <div class="row-actions">
      <button><i class="bi bi-check-lg"></i> Save</button>
      <?php if ($groupName === 'Telegram alerts'): ?>
      <button type="submit" form="tg-test" class="s"><i class="bi bi-send"></i> Test</button>
      <?php endif ?>
    </div>
  </form>
  <?php if ($groupName === 'Telegram alerts'): ?>
  <form id="tg-test" method="post" action="<?= site_url($base . '/telegram/test') ?>" style="display:none"><?= csrf_field() ?></form>
  <?php endif ?>
</div>
<?php endforeach ?>
</div><!-- .s-env -->

</div><!-- #t-settings -->

<!-- ── Conflicts panel ─────────────────────────────────── -->
<div id="t-conflicts" class="spanel">
  <iframe class="stab-iframe" data-src="<?= site_url(trim($cfg->conflictsPath, '/')) ?>"></iframe>
</div>

<!-- ── Users panel ─────────────────────────────────────── -->
<div id="t-users" class="spanel">
  <iframe class="stab-iframe" data-src="<?= site_url(trim($cfg->usersPath, '/')) ?>"></iframe>
</div>

<?php $content = ob_get_clean(); ?>
<?php ob_start(); ?>
<script>
(function () {
  'use strict';

  /* ── tab switching ─────────────────────────────── */
  function switchTab(btn) {
    document.querySelectorAll('.stab').forEach(function (b) { b.classList.remove('active'); });
    document.querySelectorAll('.spanel').forEach(function (p) { p.classList.remove('active'); });
    btn.classList.add('active');
    var panel = document.getElementById(btn.dataset.tab);
    panel.classList.add('active');
    /* lazy-load iframe */
    var iframe = panel.querySelector('iframe[data-src]');
    if (iframe) { iframe.src = iframe.dataset.src; delete iframe.dataset.src; }
    /* persist */
    try { sessionStorage.setItem('sync-settings-tab', btn.dataset.tab); } catch (e) {}
    history.replaceState(null, '', location.pathname + '#' + btn.dataset.tab.replace('t-', ''));
  }

  document.querySelectorAll('.stab').forEach(function (btn) {
    btn.addEventListener('click', function () { switchTab(btn); });
  });

  /* restore from URL hash or sessionStorage */
  var hash = location.hash.replace('#', '');
  var saved = hash || (function () { try { return sessionStorage.getItem('sync-settings-tab'); } catch (e) { return null; } }());
  if (saved) {
    var target = document.querySelector('.stab[data-tab="t-' + saved + '"]')
               || document.querySelector('.stab[data-tab="' + saved + '"]');
    if (target) switchTab(target);
  }

  /* ── color picker ↔ hex text sync ─────────────── */
  var picker = document.getElementById('colorPicker');
  var text   = document.getElementById('colorText');
  if (picker && text) {
    picker.addEventListener('input', function () { text.value = picker.value; });
    text.addEventListener('input', function () {
      if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value;
    });
  }

  /* ── logo drop zone ────────────────────────────── */
  var drop    = document.getElementById('logoDrop');
  var file    = document.getElementById('logoFile');
  var preview = document.getElementById('logoPreview');
  var hint    = document.getElementById('logoHint');
  var icon    = document.getElementById('logoIcon');
  var title   = document.getElementById('logoTitle');
  if (file && preview && hint) {
    file.addEventListener('change', function () {
      var f = file.files[0];
      if (!f) return;
      if (hint)  { hint.textContent = f.name; }
      if (icon)  { icon.style.display = 'none'; }
      if (title) { title.style.display = 'none'; }
      var reader = new FileReader();
      reader.onload = function (e) { preview.src = e.target.result; preview.style.display = 'block'; };
      reader.readAsDataURL(f);
    });
    drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('drag'); });
    drop.addEventListener('dragleave', function () { drop.classList.remove('drag'); });
    drop.addEventListener('drop', function (e) {
      e.preventDefault(); drop.classList.remove('drag');
      if (e.dataTransfer.files.length) {
        file.files = e.dataTransfer.files;
        file.dispatchEvent(new Event('change'));
      }
    });
  }

  /* ── remove logo ───────────────────────────────── */
  var rmBtn  = document.getElementById('rmLogoBtn');
  var rmForm = document.getElementById('rmLogoForm');
  if (rmBtn && rmForm) {
    rmBtn.addEventListener('click', function () { rmForm.submit(); });
  }
}());
</script>
<?php $scripts = ob_get_clean(); ?>
<?= view('AdGo\Sync\Views\ui\_layout', compact('title', 'head', 'content', 'scripts')) ?>

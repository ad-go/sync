<?php
/** @var string $node @var string $peerNode @var array<string,array<string,mixed>> $fields
 *  @var string $siteName @var string $logo @var string $accentColor @var string $fontFamily
 *  @var int $fontSize @var array<string,string> $fonts @var string $loginField
 *  @var int $maxImageMb @var string $secretHash @var string $base */
$cfg = config('Sync');

/* Groups from $fields, preserving insertion order, skipping Application */
$groups = [];
foreach ($fields as $key => $f) {
    if ($f['group'] === 'Application') { continue; }
    $groups[$f['group']][$key] = $f;
}

/* Map internal group name → translated tab label */
$tabLabel = [
    'Synchronisation' => lang('Sync.settings.tab.synchronisation'),
    'Database'        => lang('Sync.settings.tab.database'),
    'Telegram alerts' => lang('Sync.settings.tab.alerts'),
];
$label = static fn (string $g): string => $tabLabel[$g] ?? esc($g);

/* Tab ID overrides: map internal group name → clean tab ID */
$tabIdOverrides = ['Telegram alerts' => 't-alerts'];

/* Return a grid-span class based on field type */
$fspan = static function (array $f): string {
    $t = $f['type'] ?? 'text';
    if (in_array($t, ['int', 'bool', 'choice'], true)) { return 'w1'; }
    if (in_array($t, ['url', 'path', 'baseurl', 'secret'], true)) { return 'w4'; }
    if ($t === 'host') { return 'w2'; }
    $pat = $f['pattern'] ?? '';
    if ($pat !== '' && preg_match('/\{1,(\d{1,2})\}/', $pat)) { return 'w1'; }
    return 'w2';
};

$title = 'Settings · ' . esc($siteName);
ob_start();
?>
<style>
/* ── Tabs ────────────────────────────────────────────────────────────── */
.stabs{display:flex;gap:2px;border-bottom:2px solid var(--line);margin-bottom:20px;overflow-x:auto;flex-wrap:nowrap;scrollbar-width:none}
.stabs::-webkit-scrollbar{display:none}
button.stab{padding:7px 15px;cursor:pointer;border-radius:8px 8px 0 0;font-size:.82rem;font-weight:600;color:var(--mute);border:1px solid transparent;border-bottom:none;background:transparent;transition:color .14s,background .14s;white-space:nowrap;line-height:1.4;letter-spacing:.01em}
button.stab:hover{color:var(--fg);background:var(--tint)}
button.stab.active{color:var(--acc);background:var(--card);border-color:var(--line);margin-bottom:-2px}
.spanel{display:none}.spanel.active{display:block}

/* ── Card ────────────────────────────────────────────────────────────── */
.scard{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:18px 20px;margin-bottom:14px}
.scard-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--mute);margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid var(--line)}

/* ── 4-column field grid ─────────────────────────────────────────────── */
.fgrid4{display:grid;grid-template-columns:repeat(4,1fr);gap:10px 16px;align-items:start}
@media(max-width:900px){.fgrid4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:560px){.fgrid4{grid-template-columns:1fr}}
.fg{display:flex;flex-direction:column;gap:3px}
.fg.w1{grid-column:span 1}.fg.w2{grid-column:span 2}.fg.w3{grid-column:span 3}.fg.w4{grid-column:span 4}
.fg label{font-size:.75rem;font-weight:700;color:var(--mute);text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fg input,.fg select{width:100%;padding:6px 9px;border:1px solid var(--line);border-radius:7px;background:var(--card);color:var(--fg);font-size:.83rem;box-sizing:border-box}
.fg.w1 input[type=number],.fg.w1 select{max-width:130px}
.fg.w1 input[type=text]{max-width:170px}
.fg.w4 input,.fg.w4 select{font-family:ui-monospace,Consolas,monospace;font-size:.79rem}
.fg input[type=password]{font-family:ui-monospace,Consolas,monospace}
.fg input:focus,.fg select:focus{outline:none;border-color:var(--acc);box-shadow:0 0 0 3px color-mix(in srgb,var(--acc) 18%,transparent)}
.fg .hint{font-size:.72rem;color:var(--mute);line-height:1.4;margin-top:1px}

/* ── Color picker ─────────────────────────────────────────────────────── */
.color-row{display:flex;gap:7px;align-items:center}
.color-swatch{flex-shrink:0;width:34px;height:34px;border-radius:7px;border:1px solid var(--line);overflow:hidden;cursor:pointer;position:relative}
.color-swatch input[type=color]{position:absolute;inset:-4px;width:calc(100% + 8px);height:calc(100% + 8px);border:none;padding:0;cursor:pointer;opacity:1}
.color-row input.hex{flex:1}

/* ── Logo ─────────────────────────────────────────────────────────────── */
.logo-preview{max-height:40px;display:block;margin-bottom:6px;border-radius:4px}

/* ── Fingerprint ──────────────────────────────────────────────────────── */
.fp{font-family:ui-monospace,Consolas,monospace;font-size:.78rem;color:var(--mute);background:var(--tint);padding:3px 8px;border-radius:5px;display:inline-block;word-break:break-all}

/* ── Form footer ──────────────────────────────────────────────────────── */
.form-footer{display:flex;align-items:center;gap:10px;margin-top:14px;padding-top:12px;border-top:1px solid var(--line)}
.btn-sm{padding:5px 14px;font-size:.8rem;border-radius:7px;font-weight:600}
.btn-outline{background:transparent;border:1px solid var(--line);color:var(--fg)}
.btn-outline:hover{border-color:var(--acc);color:var(--acc)}

/* ── Iframe panels ────────────────────────────────────────────────────── */
.stab-iframe{width:100%;border:none;display:block;height:calc(100vh - 160px);min-height:400px}

/* ── Empty placeholder ────────────────────────────────────────────────── */
.tab-empty{padding:40px 0;text-align:center;color:var(--mute);font-size:.9rem}
.tab-empty i{font-size:2rem;display:block;margin-bottom:10px;opacity:.4}
</style>
<?php $head = ob_get_clean(); ob_start(); ?>

<!-- ── Tab bar ───────────────────────────────────────────────────────── -->
<div class="stabs" role="tablist">
  <button class="stab active" data-tab="t-branding" role="tab"><?= lang('Sync.settings.tab.branding') ?></button>
  <button class="stab" data-tab="t-node" role="tab"><?= lang('Sync.settings.tab.node') ?></button>
  <?php foreach ($groups as $groupName => $_):
      $btnTabId = $tabIdOverrides[$groupName] ?? ('t-' . strtolower(str_replace(' ', '-', $groupName)));
  ?>
  <button class="stab" data-tab="<?= esc($btnTabId) ?>" role="tab"><?= $label($groupName) ?></button>
  <?php endforeach ?>
  <button class="stab" data-tab="t-users" role="tab"><?= lang('Sync.settings.tab.users') ?></button>
  <button class="stab" data-tab="t-conflicts" role="tab"><?= lang('Sync.settings.tab.conflicts') ?></button>
  <button class="stab" data-tab="t-application" role="tab"><?= lang('Sync.settings.tab.application') ?></button>
</div>

<!-- ── Branding ──────────────────────────────────────────────────────── -->
<div id="t-branding" class="spanel active">
  <div class="scard">
    <div class="scard-title"><?= lang('Sync.settings.tab.branding') ?></div>
    <form method="post" action="<?= site_url($base . '/branding') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="fgrid4">

        <div class="fg w2">
          <label>Site name</label>
          <input type="text" name="siteName" value="<?= esc($siteName) ?>" maxlength="80" required>
        </div>

        <div class="fg w1">
          <label>Accent colour</label>
          <div class="color-row">
            <div class="color-swatch" title="Pick a colour">
              <input type="color" id="colorPicker" value="<?= esc($accentColor ?: '#1d5fd1') ?>">
            </div>
            <input type="text" name="accentColor" id="colorText" class="hex"
                   value="<?= esc($accentColor) ?>" placeholder="#1d5fd1"
                   pattern="#[0-9a-fA-F]{6}" maxlength="7" spellcheck="false">
          </div>
        </div>

        <div class="fg w1">
          <label>Font size (px)</label>
          <input type="number" name="fontSize" min="12" max="20"
                 value="<?= $fontSize > 0 ? $fontSize : '' ?>" placeholder="14">
        </div>

        <div class="fg w2">
          <label>Font</label>
          <select name="fontFamily">
            <option value="">(default)</option>
            <?php foreach ($fonts as $key => $css): ?>
            <option value="<?= esc($key) ?>" <?= $key === $fontFamily ? 'selected' : '' ?>><?= esc($key) ?></option>
            <?php endforeach ?>
          </select>
        </div>

        <div class="fg w1">
          <label>Login field</label>
          <select name="loginField">
            <option value="email"    <?= $loginField === 'email'    ? 'selected' : '' ?>>E-mail</option>
            <option value="username" <?= $loginField === 'username' ? 'selected' : '' ?>>Username</option>
            <option value="both"     <?= $loginField === 'both'     ? 'selected' : '' ?>>E-mail or username</option>
          </select>
        </div>

        <div class="fg w4">
          <label>Logo</label>
          <?php if ($logo !== ''): ?>
          <img class="logo-preview" src="<?= esc($logo) ?>" alt="">
          <?php endif ?>
          <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp">
          <span class="hint">PNG · JPG · SVG · WebP — max <?= (int) $maxImageMb ?> MiB</span>
        </div>

      </div>
      <div class="form-footer">
        <button class="btn-sm"><i class="bi bi-check-lg"></i> Save</button>
        <?php if ($logo !== ''): ?>
        <button type="submit" form="rmLogoForm" class="btn-sm btn-outline"><i class="bi bi-x-lg"></i> Remove logo</button>
        <?php endif ?>
      </div>
    </form>
    <?php if ($logo !== ''): ?>
    <form id="rmLogoForm" method="post" action="<?= site_url($base . '/logo/remove') ?>" style="display:none"><?= csrf_field() ?></form>
    <?php endif ?>
  </div>
</div>

<!-- ── Node ──────────────────────────────────────────────────────────── -->
<div id="t-node" class="spanel">
  <div class="scard">
    <div class="scard-title"><?= lang('Sync.settings.tab.node') ?></div>
    <div class="fgrid4" style="margin-bottom:12px">
      <div class="fg w4">
        <label>Secret fingerprint</label>
        <span class="fp"><?= esc($secretHash) ?></span>
        <span class="hint">Renaming a node or rotating the secret only changes <strong><?= esc($node) ?></strong> — apply the matching value on the peer by hand.</span>
      </div>
    </div>
    <form method="post" action="<?= site_url($base . '/identity') ?>">
      <?= csrf_field() ?>
      <div class="fgrid4">
        <div class="fg w2">
          <label>This node</label>
          <input type="text" name="node" value="<?= esc($node) ?>" pattern="[a-z0-9][a-z0-9_-]{0,31}" spellcheck="false">
        </div>
        <div class="fg w2">
          <label>Peer node</label>
          <input type="text" name="peerNode" value="<?= esc($peerNode) ?>" pattern="[a-z0-9][a-z0-9_-]{0,31}" spellcheck="false">
        </div>
        <div class="fg w4">
          <label>New shared secret</label>
          <input type="text" name="secret" placeholder="leave blank to keep the current one"
                 pattern="[A-Za-z0-9]{32,128}" autocomplete="off" spellcheck="false">
          <span class="hint">Min 32 alphanumeric characters. Leave blank to keep the current secret.</span>
        </div>
      </div>
      <div class="form-footer">
        <button class="btn-sm"><i class="bi bi-check-lg"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Env group tabs (Synchronisation, Database, Alerts …) ─────────── -->
<?php foreach ($groups as $groupName => $items):
    $tabId = $tabIdOverrides[$groupName] ?? ('t-' . strtolower(str_replace(' ', '-', $groupName)));
?>
<div id="<?= esc($tabId) ?>" class="spanel">
  <div class="scard">
    <div class="scard-title"><?= $label($groupName) ?></div>
    <form method="post" action="<?= site_url($base . '/env') ?>">
      <?= csrf_field() ?>
      <?php
      /* For "Telegram alerts" split fields visually into Telegram / E-mail sections */
      $sections = [];
      if ($groupName === 'Telegram alerts') {
          foreach ($items as $k => $f) {
              $sections[str_starts_with($k, 'sync.mail.') ? 'email' : 'telegram'][$k] = $f;
          }
      } else {
          $sections['_all'] = $items;
      }
      ?>
      <?php foreach ($sections as $sectionKey => $sectionItems): ?>
      <?php if ($sectionKey === 'telegram'): ?>
      <p class="scard-title" style="margin-top:0">Telegram</p>
      <?php elseif ($sectionKey === 'email'): ?>
      <p class="scard-title" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--line)">E-mail</p>
      <?php endif ?>
      <div class="fgrid4" style="<?= $sectionKey !== '_all' ? 'margin-bottom:4px' : '' ?>">
      <?php foreach ($sectionItems as $key => $f):
          $id   = str_replace('.', '__', $key);
          $span = $fspan($f);
      ?>
        <div class="fg <?= $span ?>">
          <label title="<?= esc($key) ?>"><?= esc($f['label']) ?></label>
          <?php if ($f['type'] === 'bool'): ?>
            <select name="<?= $id ?>">
              <option value="true"  <?= $f['value'] === 'true'  ? 'selected' : '' ?>>true</option>
              <option value="false" <?= $f['value'] === 'false' ? 'selected' : '' ?>>false</option>
            </select>
          <?php elseif ($f['type'] === 'choice'): ?>
            <select name="<?= $id ?>">
              <?php foreach ($f['options'] as $o): ?>
              <option value="<?= esc($o) ?>" <?= $f['value'] === $o ? 'selected' : '' ?>><?= ($o === '' ? '(none)' : esc($o)) ?></option>
              <?php endforeach ?>
            </select>
          <?php elseif ($f['type'] === 'secret'): ?>
            <input type="password" name="<?= $id ?>"
                   placeholder="<?= $f['set'] ? 'set — leave blank to keep' : 'not set' ?>"
                   autocomplete="off">
          <?php elseif ($f['type'] === 'int'): ?>
            <input type="number" name="<?= $id ?>"
                   value="<?= esc($f['value']) ?>"
                   min="<?= (int) ($f['min'] ?? 0) ?>" max="<?= (int) ($f['max'] ?? 9999) ?>">
          <?php else: ?>
            <input type="text" name="<?= $id ?>" value="<?= esc($f['value']) ?>"
                   <?= in_array($f['type'], ['url', 'baseurl'], true) ? 'inputmode="url"' : '' ?>>
          <?php endif ?>
          <?php if ($f['hint'] !== ''): ?><span class="hint"><?= esc($f['hint']) ?></span><?php endif ?>
        </div>
      <?php endforeach ?>
      </div>
      <?php endforeach ?>
      <div class="form-footer">
        <button class="btn-sm"><i class="bi bi-check-lg"></i> Save</button>
        <?php if ($groupName === 'Telegram alerts'): ?>
        <button type="submit" form="tg-test" class="btn-sm btn-outline"><i class="bi bi-send"></i> Test Telegram</button>
        <?php endif ?>
      </div>
    </form>
    <?php if ($groupName === 'Telegram alerts'): ?>
    <form id="tg-test" method="post" action="<?= site_url($base . '/telegram/test') ?>" style="display:none"><?= csrf_field() ?></form>
    <?php endif ?>
  </div>
</div>
<?php endforeach ?>

<!-- ── Users (iframe, lazy) ──────────────────────────────────────────── -->
<div id="t-users" class="spanel">
  <iframe class="stab-iframe" data-src="<?= site_url(trim($cfg->usersPath, '/')) ?>" title="<?= lang('Sync.settings.tab.users') ?>"></iframe>
</div>

<!-- ── Conflicts (iframe, lazy) ──────────────────────────────────────── -->
<div id="t-conflicts" class="spanel">
  <iframe class="stab-iframe" data-src="<?= site_url(trim($cfg->conflictsPath, '/')) ?>" title="<?= lang('Sync.settings.tab.conflicts') ?>"></iframe>
</div>

<!-- ── Application (empty placeholder) ──────────────────────────────── -->
<div id="t-application" class="spanel">
  <div class="tab-empty">
    <i class="bi bi-gear"></i>
    <?= lang('Sync.settings.tab.application') ?>
  </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php ob_start(); ?>
<script>
(function () {
  'use strict';

  /* ── Tab switching ─────────────────────────────────────────── */
  function switchTab(btn) {
    document.querySelectorAll('.stab').forEach(function (b) { b.classList.remove('active'); });
    document.querySelectorAll('.spanel').forEach(function (p) { p.classList.remove('active'); });
    btn.classList.add('active');
    var panel = document.getElementById(btn.dataset.tab);
    if (!panel) { return; }
    panel.classList.add('active');
    var iframe = panel.querySelector('iframe[data-src]');
    if (iframe) { iframe.src = iframe.dataset.src; delete iframe.dataset.src; }
    try { sessionStorage.setItem('sync-settings-tab', btn.dataset.tab); } catch (e) {}
    history.replaceState(null, '', location.pathname + '#' + btn.dataset.tab.replace('t-', ''));
  }

  document.querySelectorAll('.stab').forEach(function (btn) {
    btn.addEventListener('click', function () { switchTab(btn); });
  });

  /* restore from hash or sessionStorage */
  (function () {
    var hash = location.hash.replace('#', '');
    var key  = hash ? 't-' + hash : null;
    if (!key) { try { key = sessionStorage.getItem('sync-settings-tab'); } catch (e) {} }
    if (key) {
      var btn = document.querySelector('.stab[data-tab="' + key + '"]');
      if (btn) { switchTab(btn); }
    }
  }());

  /* ── Color picker ↔ hex text sync ─────────────────────────── */
  var picker = document.getElementById('colorPicker');
  var text   = document.getElementById('colorText');
  if (picker && text) {
    picker.addEventListener('input', function () { text.value = picker.value; });
    text.addEventListener('input', function () {
      if (/^#[0-9a-fA-F]{6}$/.test(text.value)) { picker.value = text.value; }
    });
  }
}());
</script>
<?php $scripts = ob_get_clean(); ?>
<?= view('AdGo\Sync\Views\ui\_layout', compact('title', 'head', 'content', 'scripts')) ?>

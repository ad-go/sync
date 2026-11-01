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

/* Synchronisation: the directory field comes first and the other node's URL sits right after it on the same row */
if (isset($groups['Synchronisation'])) {
    $sync = $groups['Synchronisation'];
    $head = [];
    foreach (['sync.files.dir', 'sync.peerUrl'] as $k) { if (isset($sync[$k])) { $head[$k] = $sync[$k]; } }
    $groups['Synchronisation'] = $head + array_diff_key($sync, $head);
}

/* Map internal group name → translated tab label */
$tabLabel = [
    'Synchronisation' => lang('Sync.settings.tab.synchronisation'),
    'Database'        => lang('Sync.settings.tab.database'),
    'Telegram alerts' => lang('Sync.settings.tab.alerts'),
];
$label = static fn (string $g): string => $tabLabel[$g] ?? esc($g);

/* Tab ID for an env group: 'Telegram alerts' → t-alerts, otherwise t-<slug> */
$tabIdOverrides = ['Telegram alerts' => 't-alerts'];
$tabId = static fn (string $g): string => $tabIdOverrides[$g] ?? ('t-' . strtolower(str_replace(' ', '-', $g)));

/* Bootstrap Icons per tab; env groups without an entry get the generic gear */
$tabIcon = [
    't-branding'        => 'bi-palette',
    't-node'            => 'bi-hdd-network',
    't-synchronisation' => 'bi-arrow-repeat',
    't-database'        => 'bi-database',
    't-alerts'          => 'bi-bell',
    't-users'           => 'bi-people',
    't-conflicts'       => 'bi-exclamation-triangle',
    't-application'     => 'bi-sliders',
];

/* Return a grid-span class based on field type */
$fspan = static function (array $f): string {
    $t = $f['type'] ?? 'text';
    if (in_array($t, ['int', 'bool', 'choice'], true)) { return 'w1'; }
    if ($t === 'path') { return 'w2'; }
    if (in_array($t, ['url', 'baseurl', 'secret'], true)) { return 'w2'; }
    if ($t === 'host') { return 'w2'; }
    $pat = $f['pattern'] ?? '';
    if ($pat !== '' && preg_match('/\{1,(\d{1,2})\}/', $pat)) { return 'w1'; }
    return 'w2';
};

$title = lang('Settings.title') . ' · ' . esc($siteName);
ob_start();
?>
<style>
/* ── Tabs ────────────────────────────────────────────────────────────── */
.stabs{display:flex;gap:2px;border-bottom:2px solid var(--line);margin-bottom:20px;overflow-x:auto;flex-wrap:nowrap;scrollbar-width:none}
.stabs::-webkit-scrollbar{display:none}
button.stab{padding:7px 15px;cursor:pointer;border-radius:8px 8px 0 0;font-size:.82rem;font-weight:600;color:var(--mute);border:1px solid transparent;border-bottom:none;background:transparent;transition:color .14s,background .14s;white-space:nowrap;line-height:1.4;letter-spacing:.01em}
button.stab:hover{color:var(--fg);background:var(--tint)}
button.stab.active{color:var(--acc);background:var(--card);border-color:var(--line);margin-bottom:-2px}
/* tab icons take the brand colour; the active tab's icon goes back to the grey of the inactive tabs */
button.stab > i.bi{color:var(--acc)}
button.stab.active > i.bi{color:var(--mute)}
.spanel{display:none}.spanel.active{display:block}

/* ── Cards side by side (Telegram + e-mail); one column on narrow screens ──── */
.scards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;align-items:stretch}
.scards .scard{margin-bottom:0;display:flex;flex-direction:column;min-width:0}
.scards .scard > form{flex:1;display:flex;flex-direction:column}
/* alert cards: the footer buttons sit on the same line at the bottom of both cards, without the rule above them */
.scards .scard .alert-gap{flex:1 0 14px}
.scards .scard .form-footer{margin-top:0;border-top:1px solid var(--acc);padding-top:14px}
.scards .fgrid4{grid-template-columns:repeat(2,minmax(0,1fr))}
/* Telegram: its two columns sit close together, each as wide as its fields */
.scards .fgrid4.tg-grid{grid-template-columns:repeat(2,max-content);justify-content:start;column-gap:16px}
@media(max-width:900px){.scards{grid-template-columns:1fr}}

/* ── Card ────────────────────────────────────────────────────────────── */
.scard{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:18px 20px;margin-bottom:14px}
.scard-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--mute);margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid var(--line)}

/* ── 4-column field grid ─────────────────────────────────────────────── */
.fgrid4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px 12px;align-items:start}
.fgrid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px 12px;align-items:start}
/* e-mail rows: the inputs fill their cell so the columns line up */
.email-row .fg input,.email-row .fg select{width:100%!important;max-width:100%}
/* database: rows of three equal columns; the fields are narrower than their column */
/* database: one three-column grid, every field starts at the left of its column */
.spanel .db-grid{display:grid;grid-template-columns:20ch 18ch 20ch;column-gap:24px;row-gap:6px;justify-content:start;margin-bottom:6px}
.db-grid .fg input,.db-grid .fg select{width:100%!important;max-width:100%}
.db-grid .fg input[name="database__default__port"]{width:8ch!important}
.db-grid .fg input[name="database__default__database"]{width:18ch!important}
.db-grid .fg input[name="database__default__username"],.db-grid .fg input[name="database__default__password"]{width:18ch!important}
.db-grid .fg select[name="database__default__DBDriver"]{width:12ch!important}
/* a wrapper that only groups fields: its fields lay out in the parent grid or flex line */
.contents{display:contents}
/* SMTP port and encryption side by side: each cell as wide as its content, on one line */
.email-row .font-pair{flex-wrap:nowrap}
.email-row .font-pair .fg{flex:none;width:auto}
/* port and encryption only need a few characters: narrower than their column */
.email-row .fg input[name="sync__mail__port"]{width:8ch!important}
.email-row .fg select[name="sync__mail__crypto"]{width:12ch!important}
@media(max-width:900px){.fgrid4{grid-template-columns:repeat(2,minmax(0,1fr))}.fgrid3{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.fgrid4,.fgrid3{grid-template-columns:1fr}}
/* Six fields three per row, each in one column of the four-column grid above (aligned with it) */
.three-up>.fg:nth-child(3n+1){grid-column:1}
.three-up>.fg:nth-child(3n+2){grid-column:2}
.three-up>.fg:nth-child(3n+3){grid-column:3}
@media(max-width:900px){.three-up>.fg{grid-column:auto}}
.fg label i{color:var(--acc);margin-right:2px}

/* ── Branding: languages, one tick each with its flag ───────────────── */
.lang-checks{display:flex;flex-wrap:wrap;gap:8px 20px;align-items:center}
.lang-check{display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;white-space:nowrap}
.lang-check img{border-radius:2px;flex-shrink:0}
/* ── Branding: logo drop zone beside the fields ──────────────────────── */
.brand-grid{display:grid;grid-template-columns:minmax(180px,230px) minmax(0,1fr);gap:12px;align-items:stretch}
.brand-fields{display:flex;flex-direction:column;gap:10px;min-width:0}
@media(max-width:900px){.brand-grid{grid-template-columns:1fr}}
.logo-drop{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;min-height:160px;padding:14px;border:1.5px dashed var(--line);border-radius:var(--r);background:var(--tint);cursor:pointer;text-align:center;overflow:hidden;transition:border-color .15s,background .15s}
.logo-drop:hover,.logo-drop.dragging{border-color:var(--acc);background:var(--card)}
.logo-drop.has-logo{border-style:solid;background:var(--card)}
.logo-drop img{max-height:96px;max-width:100%;object-fit:contain}
.logo-drop-text{display:flex;flex-direction:column;align-items:center;gap:4px;color:var(--mute);font-size:12px}
.logo-drop-text i{font-size:24px;color:var(--acc)}
.logo-drop-text small{font-size:11px;opacity:.8}
.save-state{font-size:12px;color:var(--mute);display:inline-flex;align-items:center;gap:6px}
.font-pair{display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap}
.fg-sub{display:flex;flex-direction:column;gap:3px;min-width:0}
.secret-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.fp.fp-preview{border:1px dashed var(--acc);color:var(--acc)}
.gen-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;padding:0;border-radius:8px;font-size:15px;line-height:1}
.fg{display:flex;flex-direction:column;gap:3px}
.fg.w1{grid-column:span 1}.fg.w2{grid-column:span 2}.fg.w3{grid-column:span 3}.fg.w4{grid-column:1 / -1}
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
.fp{font-family:ui-monospace,Consolas,monospace;font-size:.78rem;color:var(--mute);background:var(--tint);padding:3px 8px;border-radius:5px;display:inline-block;word-break:break-all;border:1px dashed var(--acc)}
/* Secret fields: dashed frame on a grey background, like the fingerprint box */
.fg input.secret-input{font-family:ui-monospace,Consolas,monospace;font-size:.78rem;color:var(--fg);background:var(--tint);border:1px dashed var(--acc);border-radius:5px;padding:3px 8px}

/* ── Form footer ──────────────────────────────────────────────────────── */
.form-footer{display:flex;align-items:center;gap:10px;margin-top:14px;padding-top:12px;border-top:1px solid var(--line)}
.btn-sm{padding:5px 14px;font-size:.8rem;border-radius:7px;font-weight:600}
/* button:not(.btn-close) in the theme outranks a bare .btn-outline, so the outline rule names the button */
button.btn-outline{background:transparent;border:1px solid var(--line);color:var(--fg)}
button.btn-outline:hover{border-color:var(--acc);color:var(--acc);background:transparent}

/* ── Iframe panels ────────────────────────────────────────────────────── */
.stab-iframe{width:100%;border:none;display:block;height:calc(100vh - 160px);min-height:400px}

/* ── Empty placeholder ────────────────────────────────────────────────── */
.tab-empty{padding:40px 0;text-align:center;color:var(--mute);font-size:.9rem}
.tab-empty i{font-size:2rem;display:block;margin-bottom:10px;opacity:.4}
</style>
<link rel="stylesheet" href="<?= esc(\AdGo\Sync\Libraries\Assets::url('assets/vendor/jstree-3.3.17/themes/default/style.min.css')) ?>">
<style>
/* Directory field: the folder button sits beside its input */
.fg.with-browse{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:6px}
.fg.with-browse>label,.fg.with-browse>.hint{grid-column:1 / -1}
.fg.with-browse>input{width:100%!important}
.fg.with-browse>#browseDir{grid-column:2}
/* Folder picker modal */
.dir-modal{position:fixed;inset:0;background:rgba(10,12,16,.45);display:flex;align-items:center;justify-content:center;z-index:300;padding:16px}
.dir-modal[hidden]{display:none}
.dir-modal-box{background:var(--card);color:var(--fg);border:1px solid var(--line);border-radius:var(--r);width:min(460px,100%);max-height:80vh;display:flex;flex-direction:column;padding:14px 16px;box-shadow:0 12px 40px rgba(0,0,0,.25)}
.dir-modal-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding-bottom:10px;margin-bottom:10px;border-bottom:1px solid var(--line)}
.dir-modal-head .gen-btn{width:28px;height:28px}
#dirTree{flex:1;min-height:220px;max-height:50vh;overflow:auto;border:1px dashed var(--acc);border-radius:6px;padding:8px;background:var(--tint)}
/* smaller tree text: the folder names, rows a little tighter */
#dirTree,#dirTree .jstree-anchor{font-size:12px}
#dirTree .jstree-node,#dirTree .jstree-anchor{line-height:20px;height:20px}
#dirTree .jstree-icon.jstree-themeicon{font-size:12px;width:20px}
.dir-modal-foot{display:flex;align-items:center;gap:8px;margin-top:12px;flex-wrap:wrap}
.dir-modal-foot .hint{flex:1;min-width:0}
.dir-modal-foot button:disabled{opacity:.45;cursor:not-allowed}
</style>
<?php $head = ob_get_clean(); ob_start(); ?>

<!-- ── Tab bar ───────────────────────────────────────────────────────── -->
<div class="stabs" role="tablist">
  <button class="stab active" data-tab="t-branding" role="tab"><i class="bi <?= $tabIcon['t-branding'] ?>"></i> <?= lang('Sync.settings.tab.branding') ?></button>
  <button class="stab" data-tab="t-node" role="tab"><i class="bi <?= $tabIcon['t-node'] ?>"></i> <?= lang('Sync.settings.tab.node') ?></button>
  <?php foreach ($groups as $groupName => $_): $id = $tabId($groupName); ?>
  <button class="stab" data-tab="<?= esc($id) ?>" role="tab"><i class="bi <?= $tabIcon[$id] ?? 'bi-sliders' ?>"></i> <?= $label($groupName) ?></button>
  <?php endforeach ?>
  <button class="stab" data-tab="t-users" role="tab"><i class="bi <?= $tabIcon['t-users'] ?>"></i> <?= lang('Sync.settings.tab.users') ?></button>
  <button class="stab" data-tab="t-conflicts" role="tab"><i class="bi <?= $tabIcon['t-conflicts'] ?>"></i> <?= lang('Sync.settings.tab.conflicts') ?></button>
  <button class="stab" data-tab="t-application" role="tab"><i class="bi <?= $tabIcon['t-application'] ?>"></i> <?= lang('Sync.settings.tab.application') ?></button>
</div>

<!-- ── Branding (saved as you change it) ─────────────────────────────── -->
<div id="t-branding" class="spanel active">
  <div class="scard">
    <div class="scard-title"><i class="bi bi-palette"></i> <?= lang('Sync.settings.tab.branding') ?></div>
    <form id="brandForm" method="post" action="<?= site_url($base . '/branding') ?>" enctype="multipart/form-data" class="brand-form">
      <?= csrf_field() ?>
      <div class="brand-grid">
        <label class="logo-drop<?= $logo !== '' ? ' has-logo' : '' ?>" id="logoDrop" title="<?= esc(lang('Settings.brand.logo_title')) ?>">
          <img id="logoPreview" alt="" src="<?= esc($logo) ?>"<?= $logo === '' ? ' hidden' : '' ?>>
          <span class="logo-drop-text">
            <i class="bi bi-cloud-arrow-up"></i>
            <span id="logoText"><?= $logo === '' ? lang('Settings.brand.logo_empty') : lang('Settings.brand.logo_replace') ?></span>
            <small><?= lang('Settings.brand.logo_formats', [(string) (int) $maxImageMb]) ?></small>
          </span>
          <input type="file" name="logo" id="logoInput" accept="image/png,image/jpeg,image/svg+xml,image/webp" hidden>
        </label>

        <div class="brand-fields">
          <div class="fgrid3 brand-row">
            <div class="fg">
              <label><i class="bi bi-type"></i> <?= lang('Settings.brand.site_name') ?></label>
              <input type="text" name="siteName" value="<?= esc($siteName) ?>" maxlength="80" required style="width:100%">
            </div>
            <div class="fg">
              <label><i class="bi bi-droplet-half"></i> <?= lang('Settings.brand.accent') ?></label>
              <div class="color-row">
                <div class="color-swatch" title="<?= esc(lang('Settings.brand.pick_colour')) ?>">
                  <input type="color" id="colorPicker" value="<?= esc($accentColor ?: '#1d5fd1') ?>">
                </div>
                <input type="text" name="accentColor" id="colorText" class="hex"
                       value="<?= esc($accentColor) ?>" placeholder="#1d5fd1"
                       pattern="#[0-9a-fA-F]{6}" maxlength="7" spellcheck="false" style="width:9ch">
              </div>
            </div>
          </div>
          <div class="fgrid3 brand-row">
            <div class="fg">
              <div class="font-pair">
                <div class="fg-sub">
                  <label><i class="bi bi-fonts"></i> <?= lang('Settings.brand.font') ?></label>
                  <select name="fontFamily" id="fontSelect" style="width:16ch;max-width:100%">
                    <option value=""><?= lang('Settings.brand.font_default') ?></option>
                    <?php foreach ($fonts as $key => $css): ?>
                    <option value="<?= esc($key) ?>" <?= $key === $fontFamily ? 'selected' : '' ?>><?= esc($key) ?></option>
                    <?php endforeach ?>
                  </select>
                </div>
                <div class="fg-sub">
                  <label><i class="bi bi-text-paragraph"></i> <?= lang('Settings.brand.size') ?></label>
                  <input type="number" name="fontSize" id="fontSize" min="12" max="20" style="width:9ch"
                         value="<?= $fontSize > 0 ? $fontSize : '' ?>" placeholder="14" aria-label="<?= esc(lang('Settings.brand.size_aria')) ?>">
                </div>
              </div>
            </div>
            <div class="fg">
              <label><i class="bi bi-box-arrow-in-right"></i> <?= lang('Settings.brand.login_field') ?></label>
              <select name="loginField" style="width:25ch;max-width:100%">
                <option value="email"    <?= $loginField === 'email'    ? 'selected' : '' ?>><?= lang('Settings.brand.login_email') ?></option>
                <option value="username" <?= $loginField === 'username' ? 'selected' : '' ?>><?= lang('Settings.brand.login_username') ?></option>
                <option value="both"     <?= $loginField === 'both'     ? 'selected' : '' ?>><?= lang('Settings.brand.login_both') ?></option>
              </select>
            </div>
          </div>
          <?php $enabledLocales = \AdGo\Sync\Libraries\Branding::locales(); /* the languages the site offers: one tick each */ ?>
          <div class="brand-row">
            <div class="fg">
              <label><i class="bi bi-translate"></i> <?= lang('Settings.brand.languages') ?></label>
              <div class="lang-checks">
                <?php foreach (\AdGo\Sync\Libraries\Branding::availableLocales() as $code):
                    $cc = \AdGo\Sync\Libraries\Branding::flagCode($code); ?>
                <label class="lang-check">
                  <input type="checkbox" name="locales[]" value="<?= esc($code) ?>"<?= in_array($code, $enabledLocales, true) ? ' checked' : '' ?>>
                  <?php if ($cc !== null): ?><img src="<?= esc(site_url('flags/' . $cc . '.svg')) ?>" alt="" width="22" height="16"><?php endif ?>
                  <span><?= esc(\AdGo\Sync\Libraries\Branding::localeName($code)) ?></span>
                </label>
                <?php endforeach ?>
              </div>
              <span class="hint"><?= lang('Settings.brand.languages_hint') ?></span>
            </div>
          </div>
        </div>
      </div>
      <div class="form-footer">
        <span class="save-state" id="brandState"><i class="bi bi-check2-circle"></i> <?= lang('Settings.brand.autosave') ?></span>
        <?php if ($logo !== ''): ?>
        <button type="submit" form="rmLogoForm" class="btn-sm btn-outline"><i class="bi bi-x-lg"></i> <?= lang('Settings.brand.remove_logo') ?></button>
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
        <label><i class="bi bi-fingerprint"></i> <?= lang('Settings.node.fingerprint') ?></label>
        <div class="secret-row">
          <span class="fp" style="width:280px;max-width:100%;box-sizing:border-box"><?= esc($secretHash) ?></span>
          <button type="button" class="btn-sm btn-outline gen-btn" id="genFp" title="<?= esc(lang('Settings.node.gen_fp_title')) ?>" aria-label="<?= esc(lang('Settings.node.generate')) ?>"><i class="bi bi-arrow-repeat"></i></button>
          <button type="button" class="btn-sm btn-outline gen-btn copy-btn" data-copy="fp" title="<?= esc(lang('Settings.node.copy_fp_title')) ?>" aria-label="<?= esc(lang('Settings.node.copy_fp_title')) ?>"><i class="bi bi-clipboard"></i></button>
        </div>
        <span class="hint"><?= lang('Settings.node.fp_hint', ['<strong>' . esc($node) . '</strong>']) ?></span>
      </div>
    </div>
    <form method="post" action="<?= site_url($base . '/identity') ?>">
      <?= csrf_field() ?>
      <div class="fgrid3">
        <div class="fg w2">
          <div class="font-pair">
            <div class="fg-sub">
              <label><?= lang('Settings.node.this_node') ?></label>
              <input type="text" name="node" value="<?= esc($node) ?>" pattern="[a-z0-9][a-z0-9_-]{0,31}" maxlength="32" spellcheck="false" style="width:20ch;max-width:100%">
            </div>
            <div class="fg-sub">
              <label><?= lang('Settings.node.peer_node') ?></label>
              <input type="text" name="peerNode" value="<?= esc($peerNode) ?>" pattern="[a-z0-9][a-z0-9_-]{0,31}" maxlength="32" spellcheck="false" style="width:20ch;max-width:100%">
            </div>
          </div>
        </div>
        <div class="fg w4">
          <label><i class="bi bi-key"></i> <?= lang('Settings.node.new_secret') ?></label>
          <div class="secret-row">
            <input type="text" name="secret" class="secret-input" placeholder="<?= esc(lang('Settings.node.secret_placeholder')) ?>"
                   pattern="[A-Za-z0-9]{32,128}" maxlength="128" autocomplete="off" spellcheck="false" style="width:280px;max-width:100%">
            <button type="button" class="btn-sm btn-outline gen-btn" id="genSecret" title="<?= esc(lang('Settings.node.gen_secret_title')) ?>" aria-label="<?= esc(lang('Settings.node.gen_secret_aria')) ?>"><i class="bi bi-shuffle"></i></button>
            <button type="button" class="btn-sm btn-outline gen-btn copy-btn" data-copy="secret" title="<?= esc(lang('Settings.node.copy_secret_title')) ?>" aria-label="<?= esc(lang('Settings.node.copy_secret_title')) ?>"><i class="bi bi-clipboard"></i></button>
          </div>
          <span class="hint" id="secretHint"><?= lang('Settings.node.secret_hint') ?></span>
        </div>
      </div>
      <div class="form-footer">
        <button class="btn-sm"><i class="bi bi-check-lg"></i> <?= lang('Settings.btn.save') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- ── Env group tabs (Synchronisation, Database, Alerts …) ─────────── -->
<?php foreach ($groups as $groupName => $items):
    /* Telegram alerts is two cards, Telegram and e-mail, each saved on its own form. */
    $cards = $groupName === 'Telegram alerts'
        ? array_filter([
            'E-mail'   => array_filter($items, static fn (string $k): bool => str_starts_with($k, 'sync.mail.'), ARRAY_FILTER_USE_KEY),
            'Telegram' => array_filter($items, static fn (string $k): bool => ! str_starts_with($k, 'sync.mail.'), ARRAY_FILTER_USE_KEY),
        ])
        : [$label($groupName) => $items];
?>
<div id="<?= esc($tabId($groupName)) ?>" class="spanel">
<div class="<?= $groupName === 'Telegram alerts' ? 'scards' : '' ?>">
<?php foreach ($cards as $cardTitle => $cardItems): ?>
  <div class="scard">
    <div class="scard-title"><?= $cardTitle === 'E-mail' ? lang('Settings.card.email') : ($cardTitle === 'Telegram' ? lang('Settings.card.telegram') : esc($cardTitle)) ?></div>
    <form method="post" action="<?= site_url($base . '/env') ?>">
      <?= csrf_field() ?>
      <?php if ($groupName === 'Synchronisation'): /* directory and other node in a row, then the six numeric settings in a 3-column grid */ $headKeys = ['sync.files.dir', 'sync.peerUrl']; ?>
      <?= view('AdGo\Sync\Views\settings\_fields', ['items' => array_intersect_key($cardItems, array_flip($headKeys)), 'fspan' => $fspan, 'gridClass' => 'fgrid4']) ?>
      <?= view('AdGo\Sync\Views\settings\_fields', ['items' => array_diff_key($cardItems, array_flip($headKeys)), 'fspan' => $fspan, 'gridClass' => 'fgrid4 three-up']) ?>
      <?php elseif ($groupName === 'Database'): /* connection first (driver, host, port), then the account (name, user, password): one column each */
          $dbSpan = static fn (array $f): string => 'w1';
          $db     = static fn (array $keys): array => array_intersect_key($cardItems, array_flip($keys)); ?>
      <div class="db-grid">
        <?= view('AdGo\Sync\Views\settings\_fields', ['items' => $db(['database.default.DBDriver', 'database.default.hostname', 'database.default.port']), 'fspan' => $dbSpan, 'gridClass' => 'contents']) ?>
      <?php /* the second row of the same grid */ ?>
        <?= view('AdGo\Sync\Views\settings\_fields', ['items' => $db(['database.default.database','database.default.username', 'database.default.password']), 'fspan' => $dbSpan, 'gridClass' => 'contents']) ?>
      </div>
      <?php else: ?>
      <?php if ($groupName === 'Telegram alerts' && $cardTitle === 'E-mail'):
          /* E-mail: three rows, the addresses, the server and the account; one column per field */
          /* Three rows on one three-column grid. Encryption sits right after the SMTP port, in the same cell. The
             fields of a cell are rendered with display:contents, so they take part in the row's grid or flex line. */
          $mailSpan = static fn (array $f): string => 'w1';
          $mail     = static fn (array $keys): array => array_intersect_key($cardItems, array_flip($keys)); ?>
      <div class="fgrid3 email-row">
        <?= view('AdGo\Sync\Views\settings\_fields', ['items' => $mail(['sync.mail.to', 'sync.mail.from']), 'fspan' => $mailSpan, 'gridClass' => 'contents']) ?>
      </div>
      <div class="fgrid3 email-row">
        <?= view('AdGo\Sync\Views\settings\_fields', ['items' => $mail(['sync.mail.host']), 'fspan' => $mailSpan, 'gridClass' => 'contents']) ?>
        <div class="fg">
          <div class="font-pair">
            <?= view('AdGo\Sync\Views\settings\_fields', ['items' => $mail(['sync.mail.port', 'sync.mail.crypto']), 'fspan' => $mailSpan, 'gridClass' => 'contents']) ?>
          </div>
        </div>
      </div>
      <div class="fgrid3 email-row">
        <?= view('AdGo\Sync\Views\settings\_fields', ['items' => $mail(['sync.mail.user', 'sync.mail.pass']), 'fspan' => $mailSpan, 'gridClass' => 'contents']) ?>
      </div>
      <?php
      else: /* Telegram: its four fields in a 2 x 2 grid (one column each) */
          $cardSpan = ($groupName === 'Telegram alerts' && $cardTitle === 'Telegram') ? static fn (array $f): string => 'w1' : $fspan; ?>
      <?= view('AdGo\Sync\Views\settings\_fields', ['items' => $cardItems, 'fspan' => $cardSpan, 'gridClass' => 'fgrid4 tg-grid']) ?>
      <?php endif ?>
      <?php endif ?>
      <?php if ($groupName !== 'Synchronisation'): /* that tab saves as it changes: no button */ ?>
      <?php if ($groupName === 'Telegram alerts'): /* space above the footer: at least 14px, grows to push it down */ ?><div class="alert-gap"></div><?php endif ?>
      <div class="form-footer">
        <button class="btn-sm"><i class="bi bi-check-lg"></i> <?= lang('Settings.btn.save') ?></button>
        <?php if ($groupName === 'Telegram alerts' && $cardTitle === 'E-mail'): ?>
        <button type="submit" form="mail-test" class="btn-sm btn-outline"><i class="bi bi-envelope-check"></i> <?= lang('Settings.btn.test_email') ?></button>
        <?php endif ?>
        <?php if ($groupName === 'Telegram alerts' && $cardTitle === 'Telegram'): ?>
        <button type="submit" form="tg-test" class="btn-sm btn-outline"><i class="bi bi-send"></i> <?= lang('Settings.btn.test_telegram') ?></button>
        <?php endif ?>
      </div>
      <?php endif ?>
    </form>
  </div>
<?php endforeach ?>
</div>
<?php if ($groupName === 'Telegram alerts'): ?>
<form id="tg-test" method="post" action="<?= site_url($base . '/telegram/test') ?>" style="display:none"><?= csrf_field() ?></form>
<form id="mail-test" method="post" action="<?= site_url($base . '/email/test') ?>" style="display:none"><?= csrf_field() ?></form>
<?php endif ?>
</div>
<?php endforeach ?>

<!-- ── Users (iframe, lazy) ──────────────────────────────────────────── -->
<div id="t-users" class="spanel">
  <?= $usersPanel ?>
</div>

<!-- ── Conflicts (iframe, lazy) ──────────────────────────────────────── -->
<div id="t-conflicts" class="spanel">
  <?= $conflictsPanel ?>
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
<script src="<?= esc(\AdGo\Sync\Libraries\Assets::url('assets/vendor/jquery-3.7.1.min.js')) ?>"></script>
<script src="<?= esc(\AdGo\Sync\Libraries\Assets::url('assets/vendor/jstree-3.3.17/jstree.min.js')) ?>"></script>
<script>
(function () {
  'use strict';

  /* ── Folder picker for the synchronised directory: a modal with a lazy jsTree ── */
  (function () {
    var btn    = document.getElementById('browseDir');
    var modal  = document.getElementById('dirModal');
    var tree   = document.getElementById('dirTree');
    var input  = document.querySelector('input[name="sync__files__dir"]');
    var useBtn = document.getElementById('dirUse');
    var picked = document.getElementById('dirPicked');
    if (!btn || !modal || !window.jQuery || !jQuery.fn.jstree) { return; }

    var root = <?= json_encode($filesRoot) ?>;
    var sep  = root.indexOf('\\') !== -1 ? '\\' : '/';
    var chosen = null;                                   // relative id of the selected folder

    function setChosen(id) {
      chosen = id;
      useBtn.disabled = id === null;
      picked.textContent = id === null ? <?= json_encode(lang('Settings.folder.select')) ?> : root + sep + id.split('/').join(sep);
    }
    function open() { modal.hidden = false; setChosen(null); useBtn.focus(); }
    function close() { modal.hidden = true; btn.focus(); }

    jQuery(tree).jstree({
      core: {
        multiple: false,
        check_callback: false,
        data: {
          url: <?= json_encode($dirsUrl) ?>,
          dataType: 'json',
          data: function (node) { return { path: node.id === '#' ? '' : node.id }; }
        }
      }
    });
    jQuery(tree).on('select_node.jstree', function (e, data) { setChosen(data.node.id); });
    jQuery(tree).on('deselect_all.jstree', function () { setChosen(null); });

    btn.addEventListener('click', open);
    document.getElementById('dirClose').addEventListener('click', close);
    document.getElementById('dirCancel').addEventListener('click', close);
    modal.addEventListener('click', function (e) { if (e.target === modal) { close(); } });   // backdrop
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { close(); } });
    useBtn.addEventListener('click', function () {
      if (chosen === null) { return; }
      input.value = root + sep + chosen.split('/').join(sep);
      input.dispatchEvent(new Event('change', { bubbles: true }));
      close();
    });
  }());

  /* ── Tab switching ─────────────────────────────────────────── */
  function switchTab(btn) {
    document.querySelectorAll('.stab').forEach(function (b) { b.classList.remove('active'); });
    document.querySelectorAll('.spanel').forEach(function (p) { p.classList.remove('active'); });
    btn.classList.add('active');
    var panel = document.getElementById(btn.dataset.tab);
    if (!panel) { return; }
    panel.classList.add('active');
    // DataTables sizes its columns while the tab is hidden; a resize makes it recalculate once the tab shows.
    window.dispatchEvent(new Event('resize'));
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

  /* ── Node identity: generate a shared secret (filled in, saved with Save) ── */
  function randomSecret() {
    var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', bytes = new Uint32Array(64), out = '';
    crypto.getRandomValues(bytes);
    for (var i = 0; i < bytes.length; i++) { out += chars[bytes[i] % chars.length]; }
    return out;
  }
  /* Fingerprint = first 10 hex chars of SHA-256(secret), as NodeIdentity::fingerprint(). The box shows the current
     one; once a new secret is generated or typed it shows that secret's fingerprint, as a preview until Save. */
  var fpBox      = document.querySelector('.fp');
  var currentFp  = fpBox ? fpBox.textContent.trim() : '';
  var secretInput = document.querySelector('input[name="secret"]');
  var fpSeq = 0;

  function fingerprintOf(secret) {
    return crypto.subtle.digest('SHA-256', new TextEncoder().encode(secret)).then(function (buf) {
      return Array.from(new Uint8Array(buf)).map(function (b) { return b.toString(16).padStart(2, '0'); }).join('').slice(0, 10);
    });
  }

  function showPreview(secret) {
    var seq = ++fpSeq;
    if (!fpBox) { return; }
    if (!/^[A-Za-z0-9]{32,128}$/.test(secret)) {
      fpBox.textContent = currentFp;
      fpBox.classList.remove('fp-preview');
      return;
    }
    fingerprintOf(secret).then(function (fp) {
      if (seq !== fpSeq) { return; }                   // a newer value was typed meanwhile
      fpBox.textContent = fp;
      fpBox.classList.add('fp-preview');
            fpBox.title = <?= json_encode(lang('Settings.node.fp_preview', ['__CUR__'])) ?>.replace('__CUR__', currentFp);
    });
  }

  function fillSecret() {
    if (!secretInput) { return; }
    var value = randomSecret();
    secretInput.value = value;
    resetCopyIcons();
    secretInput.focus();
    // The field sits below the fingerprint: bring it into view and point at what changed.
    secretInput.scrollIntoView({ block: 'center', behavior: 'smooth' });
    secretInput.style.boxShadow = '0 0 0 3px color-mix(in srgb, var(--acc) 45%, transparent)';
    setTimeout(function () { secretInput.style.boxShadow = ''; }, 1600);
    var hint = document.getElementById('secretHint');
        if (hint) { hint.textContent = <?= json_encode(lang('Settings.node.secret_generated')) ?>; hint.style.color = 'var(--acc)'; }
    showPreview(value);
  }

  if (secretInput) {
    secretInput.addEventListener('input', function () { showPreview(secretInput.value); });
  }
  var genSecretBtn = document.getElementById('genSecret');
  var genFpBtn    = document.getElementById('genFp');
  if (genSecretBtn) { genSecretBtn.addEventListener('click', fillSecret); }
  if (genFpBtn)     { genFpBtn.addEventListener('click', fillSecret); }

  /* ── Copy the keys shown on the node tab (fingerprint, new secret) ── */
  function copyFallback(text) {
    var t = document.createElement('textarea');
    t.value = text; t.style.position = 'fixed'; t.style.opacity = '0';
    document.body.appendChild(t); t.select();
    try { document.execCommand('copy'); } catch (e) { /* no clipboard: nothing else to do */ }
    t.remove();
  }
  /* The check stays after a copy; it goes back to the clipboard only when the value changes. */
  function setCopied(btn, copied) {
    btn.querySelector('i').className = copied ? 'bi bi-check2' : 'bi bi-clipboard';
    btn.dataset.copied = copied ? '1' : '';
  }
  function copyText(text, btn) {
    resetCopyIcons();                                      // only one value can be in the clipboard: one check at a time
    setCopied(btn, true);                                  // at once: the write itself can take a moment
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).catch(function () { copyFallback(text); });
    } else {
      copyFallback(text);
    }
  }
  function resetCopyIcons() {
    document.querySelectorAll('.copy-btn').forEach(function (b) { setCopied(b, false); });
  }
  document.querySelectorAll('.copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.dataset.copy === 'fp'
        ? (fpBox ? fpBox.textContent.trim() : '')
        : (secretInput ? secretInput.value : '');
      if (text !== '') { copyText(text, btn); return; }
      var hint = document.getElementById('secretHint');
      if (btn.dataset.copy === 'secret' && hint) {
                hint.textContent = <?= json_encode(lang('Settings.node.nothing_to_copy')) ?>;
        hint.style.color = 'var(--err-fg)';
      }
    });
  });
  if (secretInput) {
    secretInput.addEventListener('input', resetCopyIcons);
  }

  /* ── Branding: live preview, then saved on every change ───────────── */
  var root      = document.documentElement;
  var fontCss   = <?= json_encode($fonts) ?>;
  var brandForm = document.getElementById('brandForm');
  var brandState = document.getElementById('brandState');
  var saveTimer = null;

  function setState(text, bad) {
    brandState.innerHTML = '<i class="bi ' + (bad ? 'bi-exclamation-triangle' : 'bi-check2-circle') + '"></i> ' + text;
    brandState.style.color = bad ? 'var(--err-fg)' : 'var(--mute)';
  }

  function saveBrand() {
        setState(<?= json_encode(lang('Settings.js.saving')) ?>, false);
    fetch(brandForm.action, {
      method: 'POST',
      body: new FormData(brandForm),
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false, message: (<?= json_encode(lang('Settings.js.bad_response', ['{0}'])) ?>).replace('{0}', r.status) }; });
    }).then(function (j) {
      if (j.tokenName && j.token) {                       // the CSRF token is regenerated on each save:
        // every form on the page carries it (the Remove logo form sits outside this one), so update all of them
        document.querySelectorAll('input[name="' + j.tokenName + '"]').forEach(function (t) { t.value = j.token; });
      }
            setState(j.ok ? <?= json_encode(lang('Settings.js.saved')) ?> : j.message, !j.ok);
      window.syncToast(j.message, j.ok);
        }).catch(function () { setState(<?= json_encode(lang('Settings.js.net_error')) ?>, true); });
  }

  function queueSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveBrand, 500);
  }

  // Live preview: the accent, the font and the size change at once; the save follows.
  if (picker) {
    picker.addEventListener('input', function () { root.style.setProperty('--acc', picker.value); });
  }
  var fontSel = document.getElementById('fontSelect');
  if (fontSel) {
    fontSel.addEventListener('change', function () {
      var css = fontCss[fontSel.value] || '';
      if (css) { root.style.setProperty('--font-family', css); } else { root.style.removeProperty('--font-family'); }
    });
  }
  var sizeInput = document.getElementById('fontSize');
  if (sizeInput) {
    sizeInput.addEventListener('input', function () {
      var n = parseInt(sizeInput.value, 10);
      if (n >= 12 && n <= 20) { root.style.setProperty('--font-size', n + 'px'); }
    });
  }

  // At least one language stays ticked: unticking the last one puts its tick back.
  brandForm.addEventListener('change', function (e) {
    if (e.target.name !== 'locales[]' || e.target.checked) { return; }
    var any = Array.prototype.some.call(brandForm.querySelectorAll('input[name="locales[]"]'), function (c) { return c.checked; });
    if (!any) { e.target.checked = true; }
  });

  brandForm.addEventListener('input', function (e) {
    if (e.target.name !== 'logo') { queueSave(); }
  });
  brandForm.addEventListener('change', function (e) {
    if (e.target.name === 'logo') { return; }
    queueSave();
  });

  // Logo: click or drop an image; the preview shows at once and the file is saved.
  var drop  = document.getElementById('logoDrop');
  var input = document.getElementById('logoInput');
  var prev  = document.getElementById('logoPreview');

  function showLogo(file) {
    if (!file) { return; }
    var reader = new FileReader();
    reader.onload = function () {
      prev.src = reader.result;
      prev.hidden = false;
      drop.classList.add('has-logo');
            document.getElementById('logoText').textContent = <?= json_encode(lang('Settings.brand.logo_replace')) ?>;
    };
    if (file.type.indexOf('image/') === 0) { reader.readAsDataURL(file); }
    saveBrand();
  }

  input.addEventListener('change', function () { showLogo(input.files[0]); });
  ['dragenter', 'dragover'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('dragging'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('dragging'); });
  });
  drop.addEventListener('drop', function (e) {
    if (e.dataTransfer && e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      showLogo(input.files[0]);
    }
  });
}());
  /* ── Synchronisation tab: each change is saved; the result is a toast ── */
  (function () {
    var syncTab = document.getElementById('t-synchronisation');
    var form    = syncTab ? syncTab.querySelector('form') : null;
    if (!form) { return; }
    var timer = null;

    function refreshTokens(name, value) {
      if (!name || !value) { return; }
      document.querySelectorAll('input[name="' + name + '"]').forEach(function (t) { t.value = value; });
    }
    function save() {
      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      }).then(function (r) {
        return r.json().catch(function () { return { ok: false, message: (<?= json_encode(lang('Settings.js.bad_response', ['{0}'])) ?>).replace('{0}', r.status) }; });
      }).then(function (j) {
        refreshTokens(j.tokenName, j.token);
        window.syncToast(j.message, j.ok);
      }).catch(function () { window.syncToast(<?= json_encode(lang('Settings.js.net_error')) ?>, false); });
    }
    function queue() { clearTimeout(timer); timer = setTimeout(save, 500); }

    form.addEventListener('input', queue);
    form.addEventListener('change', queue);
    form.addEventListener('submit', function (e) { e.preventDefault(); clearTimeout(timer); save(); });
  }());

  /* ── SMTP: the encryption sets the usual port, unless the port was set to something else ── */
  (function () {
    var enc  = document.querySelector('select[name="sync__mail__crypto"]');
    var port = document.querySelector('input[name="sync__mail__port"]');
    if (!enc || !port) { return; }
    var usual = { tls: '587', ssl: '465', '': '25' };         // STARTTLS, implicit TLS, none
    enc.addEventListener('change', function () {
      var current = port.value.trim();
      var known   = ['587', '465', '25', ''];
      if (known.indexOf(current) !== -1) { port.value = usual[enc.value] || '25'; }
    });
  }());

</script>
<?php $scripts = ob_get_clean(); ?>
<?= view('AdGo\Sync\Views\ui\_layout', compact('title', 'head', 'content', 'scripts')) ?>

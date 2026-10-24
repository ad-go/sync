<?php
/**
 * SyncUI's own chrome: top bar (sidebar toggle, brand, jump-to-table, language switch, user menu), a
 * resizable/collapsible sidebar of tables (an off-canvas drawer on narrow screens), the content section each
 * page fills in, and a footer. index.php / table.php / sql.php / showcase.php extend this.
 *
 * Vendored assets (jQuery, DataTables, Select2, Toastify, Lucide icons — see Assets/syncui/NOTICE.md) are
 * served by SyncUiAdmin::asset(), never fetched from a third-party CDN at runtime.
 */
/** @var string $title */
/** @var string $base */
/** @var Closure $asset */
/** @var string $activeTable */
/** @var list<array{name: string, rows: int}> $sidebarTables */
/** @var array<string, string> $locales */
/** @var string $activeLocale */
/** @var string $currentUrl */
/** @var string $username */
/** @var string $profileUrl */
/** @var string $logoutUrl */
/** @var string $menu */
/** @var string $head */
use AdGo\Sync\Libraries\Icon;
?>
<!doctype html>
<html lang="<?= esc($activeLocale) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($title) ?> · <?= lang('Sync.syncUi.brand') ?></title>
<?= $head ?>
<link rel="stylesheet" href="<?= esc($asset('select2/4.1.0/select2.min.css')) ?>">
<link rel="stylesheet" href="<?= esc($asset('datatables/3.1.4/dataTables.dataTables.min.css')) ?>">
<link rel="stylesheet" href="<?= esc($asset('toastify/1.12.0/toastify.min.css')) ?>">
<style>
/* Everything below is scoped under .syncui-shell (never a bare element selector) so it cannot be squashed —
   or bleed into — whatever the host application's own $menu/$head partials put on the page: we proved a
   host `body{...}` rule, injected later in the document via $menu, otherwise wins equal-specificity ties. */
:root{--border:#dde;--bg:#f6f7f9;--panel:#fff;--accent:#1d5fd1;--accent-ink:#fff;--text:#1c1f26;--muted:#6b7280;--radius:8px;--shadow:0 8px 24px rgba(20,24,30,.12)}
@media (prefers-color-scheme:dark){:root{--border:#2e333c;--bg:#12141a;--panel:#1b1e26;--text:#e7e9ee;--muted:#9aa2b1;--shadow:0 8px 24px rgba(0,0,0,.4)}}
html{margin:0;height:100%}
.syncui-shell,.syncui-shell *{box-sizing:border-box}
.syncui-shell{margin:0;min-height:100vh;max-width:none;display:flex;flex-direction:column;font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--text);background:var(--bg)}
.syncui-shell a{color:inherit}
.syncui-shell .icon{width:1em;height:1em;vertical-align:-0.15em}
.syncui-shell .topbar{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 14px;background:var(--panel);border-bottom:1px solid var(--border);flex:0 0 auto;position:sticky;top:0;z-index:50}
.syncui-shell .topbar .left{display:flex;align-items:center;gap:10px;min-width:0}
.syncui-shell .topbar .brand{display:flex;align-items:center;gap:6px;font-weight:700;color:var(--accent);text-decoration:none;font-size:16px;white-space:nowrap}
.syncui-shell .topbar .jump{width:220px;max-width:40vw}
.syncui-shell .topbar .right{display:flex;align-items:center;gap:4px;flex:0 0 auto}
.syncui-shell .iconbtn{background:none;color:inherit;border:1px solid var(--border);border-radius:var(--radius);padding:6px 9px;cursor:pointer;font:inherit;display:inline-flex;align-items:center;gap:6px;font-size:16px}
.syncui-shell .iconbtn:hover{background:var(--border)}
.syncui-shell .hostmenu{flex:0 0 auto}
.syncui-shell details.dropdown{position:relative}
.syncui-shell details.dropdown>summary{list-style:none;cursor:pointer;padding:6px 9px;border-radius:var(--radius);user-select:none;display:flex;align-items:center;gap:6px;font-size:16px}
.syncui-shell details.dropdown>summary::-webkit-details-marker{display:none}
.syncui-shell details.dropdown[open]>summary{background:var(--border)}
.syncui-shell details.dropdown .ddmenu{position:absolute;right:0;top:100%;margin-top:6px;background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);min-width:170px;box-shadow:var(--shadow);z-index:60;padding:4px}
.syncui-shell details.dropdown .ddmenu a,.syncui-shell details.dropdown .ddmenu button{display:flex;align-items:center;gap:8px;width:100%;text-align:left;padding:7px 10px;border:0;background:none;color:inherit;text-decoration:none;border-radius:6px;font:inherit;font-size:13px;cursor:pointer}
.syncui-shell details.dropdown .ddmenu a:hover,.syncui-shell details.dropdown .ddmenu button:hover{background:var(--border)}
.syncui-shell .shellbody{display:flex;flex:1;min-height:0;position:relative}
.syncui-shell #sidebarBackdrop{display:none}
.syncui-shell aside.sidebar{width:230px;min-width:150px;max-width:480px;resize:horizontal;overflow:auto;border-right:1px solid var(--border);background:var(--panel);flex:0 0 auto}
.syncui-shell.sidebar-hidden aside.sidebar{display:none}
.syncui-shell aside.sidebar nav a{display:flex;align-items:center;gap:8px;padding:7px 14px;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px}
.syncui-shell aside.sidebar nav a .icon{color:var(--muted);flex:0 0 auto}
.syncui-shell aside.sidebar nav a .cnt{margin-left:auto;color:var(--muted);font-size:11px}
.syncui-shell aside.sidebar nav a:hover,.syncui-shell aside.sidebar nav a.active{background:var(--border)}
.syncui-shell aside.sidebar nav a.active{font-weight:700}
.syncui-shell aside.sidebar .navsep{border-top:1px solid var(--border);margin:6px 0;padding-top:6px}
.syncui-shell main.content{flex:1;min-width:0;overflow:auto;padding:18px}
.syncui-shell footer.sitefoot{flex:0 0 auto;padding:8px 16px;border-top:1px solid var(--border);background:var(--panel);font-size:12px;color:var(--muted);text-align:center}
.syncui-shell h1{font-size:19px;margin:0 0 14px}
.syncui-shell h2{font-size:15px;margin:0 0 10px}
.syncui-shell table{border-collapse:collapse;width:100%;background:var(--panel);border-radius:var(--radius);overflow:hidden}
.syncui-shell th,.syncui-shell td{padding:7px 9px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top;word-break:break-word}
.syncui-shell thead th{background:color-mix(in srgb, var(--accent) 8%, var(--panel))}
.syncui-shell button,.syncui-shell a.btn{padding:6px 12px;border:0;border-radius:var(--radius);color:var(--accent-ink);background:var(--accent);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font:inherit;font-size:13px}
.syncui-shell a.btn.ghost{background:transparent;color:var(--accent);border:1px solid var(--border)}
.syncui-shell button.d{background:#b42318}
.syncui-shell input[type=text],.syncui-shell input[type=file],.syncui-shell textarea,.syncui-shell select{width:100%;font:inherit;padding:5px 7px;border:1px solid var(--border);border-radius:6px;background:var(--panel);color:var(--text)}
.syncui-shell textarea{font-family:ui-monospace,Consolas,monospace;font-size:13px}
.syncui-shell .pager a{margin-right:8px}
.syncui-shell .card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:16px}
.syncui-shell .widget-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px}
/* Select2: match the panel colours instead of its bootstrap-less defaults */
.syncui-shell .select2-container .select2-selection--single{height:30px;border-color:var(--border);border-radius:6px;background:var(--panel)}
.syncui-shell .select2-container--default .select2-selection--single .select2-selection__rendered{color:var(--text);line-height:28px}
.syncui-shell .select2-dropdown{background:var(--panel);color:var(--text);border-color:var(--border)}
.syncui-shell .select2-container--default .select2-results__option--highlighted[aria-selected]{background:var(--accent)}
/* DataTables: drop its own spacing/skin quirks, keep our table look */
.syncui-shell table.dataTable{margin:0 !important}
.syncui-shell .dt-search input{background:var(--panel);color:var(--text);border-color:var(--border)}
.syncui-shell .dt-length,.syncui-shell .dt-info{display:none}
@media (max-width: 900px){
  .syncui-shell .topbar .jump{display:none}
  .syncui-shell aside.sidebar{position:fixed;top:0;left:0;height:100%;z-index:70;resize:none;max-width:85vw;width:280px;transform:translateX(-100%);transition:transform .2s ease;box-shadow:var(--shadow)}
  .syncui-shell.sidebar-open aside.sidebar{transform:translateX(0)}
  .syncui-shell.sidebar-hidden aside.sidebar{display:block}
  .syncui-shell.sidebar-open #sidebarBackdrop{display:block;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:65}
}
@media (max-width: 480px){
  .syncui-shell .dd-label{display:none}
  .syncui-shell .topbar .brand span{display:none}
}
</style>
<?= $this->renderSection('pageHead') ?>
</head>
<body class="syncui-shell">
<header class="topbar">
  <div class="left">
    <button type="button" class="iconbtn" id="sidebarToggle" title="<?= esc(lang('Sync.syncUi.toggleSidebar')) ?>" aria-label="<?= esc(lang('Sync.syncUi.toggleSidebar')) ?>"><?= Icon::svg('menu') ?></button>
    <a class="brand" href="<?= esc(site_url($base)) ?>"><?= Icon::svg('database') ?> <span><?= lang('Sync.syncUi.brand') ?></span></a>
  </div>
  <select class="jump" id="jumpToTable" style="width:220px">
    <option value=""><?= esc(lang('Sync.syncUi.jumpToTable')) ?></option>
    <?php foreach ($sidebarTables as $t): ?>
    <option value="<?= esc(site_url($base . '/' . $t['name'])) ?>"<?= $t['name'] === $activeTable ? ' selected' : '' ?>><?= esc($t['name']) ?></option>
    <?php endforeach ?>
  </select>
  <div class="right">
    <?php if (count($locales) > 1): ?>
    <details class="dropdown">
      <summary title="<?= esc(lang('Sync.syncUi.language')) ?>"><?= Icon::svg('globe') ?> <span class="dd-label"><?= esc(strtoupper($activeLocale)) ?></span></summary>
      <div class="ddmenu">
        <?php foreach ($locales as $code => $label): ?>
        <a href="<?= esc(site_url($base . '/locale/' . $code) . '?back=' . urlencode($currentUrl)) ?>"><?= esc($label) ?><?= $code === $activeLocale ? ' ' . Icon::svg('check') : '' ?></a>
        <?php endforeach ?>
      </div>
    </details>
    <?php endif ?>
    <details class="dropdown">
      <summary><?= Icon::svg('user') ?> <span class="dd-label"><?= esc($username) ?></span></summary>
      <div class="ddmenu">
        <?php if ($profileUrl !== ''): ?><a href="<?= esc($profileUrl) ?>"><?= Icon::svg('user') ?> <?= lang('Sync.syncUi.profile') ?></a><?php endif ?>
        <form method="post" action="<?= esc($logoutUrl) ?>"><?= csrf_field() ?><button type="submit"><?= Icon::svg('arrow-left') ?> <?= lang('Sync.syncUi.logout') ?></button></form>
      </div>
    </details>
  </div>
</header>
<?php if ($menu !== ''): ?><div class="hostmenu"><?= $menu ?></div><?php endif ?>
<div class="shellbody">
  <div id="sidebarBackdrop"></div>
  <aside class="sidebar" id="syncUiSidebar">
    <nav>
      <?php foreach ($sidebarTables as $t): ?>
      <a href="<?= esc(site_url($base . '/' . $t['name'])) ?>" class="<?= $t['name'] === $activeTable ? 'active' : '' ?>"><?= Icon::svg('table-2') ?> <?= esc($t['name']) ?><span class="cnt"><?= (int) $t['rows'] ?></span></a>
      <?php endforeach ?>
      <div class="navsep">
        <a href="<?= esc(site_url($base . '/sql')) ?>" class="<?= $activeTable === 'sql' ? 'active' : '' ?>"><?= Icon::svg('play') ?> <?= lang('Sync.syncUi.runSql') ?></a>
        <a href="<?= esc(site_url($base . '/showcase')) ?>" class="<?= $activeTable === 'showcase' ? 'active' : '' ?>"><?= Icon::svg('image') ?> <?= lang('Sync.syncUi.showcase') ?></a>
      </div>
    </nav>
  </aside>
  <main class="content">
    <?= $this->renderSection('content') ?>
  </main>
</div>
<footer class="sitefoot"><?= lang('Sync.syncUi.footer') ?></footer>

<script src="<?= esc($asset('jquery/3.7.1/jquery.min.js')) ?>"></script>
<script src="<?= esc($asset('datatables/3.1.4/dataTables.min.js')) ?>"></script>
<script src="<?= esc($asset('datatables/3.1.4/dataTables.dataTables.min.js')) ?>"></script>
<script src="<?= esc($asset('select2/4.1.0/select2.min.js')) ?>"></script>
<script src="<?= esc($asset('toastify/1.12.0/toastify.min.js')) ?>"></script>
<script>
(function ($) {
  'use strict';
  var WKEY = 'syncui-sidebar-width', HKEY = 'syncui-sidebar-hidden';
  var body = document.body, side = document.getElementById('syncUiSidebar'), toggle = document.getElementById('sidebarToggle');
  var narrow = function () { return window.matchMedia('(max-width: 900px)').matches; };

  try {
    if (!narrow() && localStorage.getItem(HKEY) === '1') { body.classList.add('sidebar-hidden'); }
    var w = localStorage.getItem(WKEY);
    if (w && side) { side.style.width = w + 'px'; }
  } catch (e) {}

  if (side && window.ResizeObserver) {
    new ResizeObserver(function () {
      if (narrow()) { return; }
      try { localStorage.setItem(WKEY, String(Math.round(side.getBoundingClientRect().width))); } catch (e) {}
    }).observe(side);
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      if (narrow()) {
        body.classList.toggle('sidebar-open');
        return;
      }
      body.classList.toggle('sidebar-hidden');
      try { localStorage.setItem(HKEY, body.classList.contains('sidebar-hidden') ? '1' : '0'); } catch (e) {}
    });
  }

  var backdrop = document.getElementById('sidebarBackdrop');
  if (backdrop) { backdrop.addEventListener('click', function () { body.classList.remove('sidebar-open'); }); }

  $('#jumpToTable').select2({ width: '220px', placeholder: <?= json_encode(lang('Sync.syncUi.jumpToTable')) ?> });
  $('#jumpToTable').on('change', function () {
    var url = $(this).val();
    if (url) { window.location.href = url; }
  });

  <?php if (session()->getFlashdata('message')): ?>
  Toastify({ text: <?= json_encode((string) session()->getFlashdata('message')) ?>, duration: 4000, gravity: 'top', position: 'right', style: { background: '#1d8a4c' } }).showToast();
  <?php endif ?>
  <?php if (session()->getFlashdata('error')): ?>
  Toastify({ text: <?= json_encode((string) session()->getFlashdata('error')) ?>, duration: 6000, gravity: 'top', position: 'right', style: { background: '#b42318' } }).showToast();
  <?php endif ?>
})(jQuery);
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>

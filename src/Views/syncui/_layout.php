<?php
/**
 * SyncUI's own chrome: top bar (toggle, brand, language switch, user menu), a resizable/collapsible sidebar of
 * tables, the content section each page fills in, and a footer. index.php / table.php / sql.php extend this.
 */
/** @var string $title */
/** @var string $base */
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
?>
<!doctype html>
<html lang="<?= esc($activeLocale) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($title) ?> · <?= lang('Sync.syncUi.brand') ?></title>
<?= $head ?>
<style>
:root{--border:#dde;--bg:#f6f7f9;--panel:#fff;--accent:#1d5fd1;--text:#1c1f26}
@media (prefers-color-scheme:dark){:root{--border:#343944;--bg:#14161a;--panel:#1e2128;--text:#e6e8ec}}
*{box-sizing:border-box}
html,body{margin:0;height:100%}
body{display:flex;flex-direction:column;min-height:100vh;font:14px/1.45 system-ui,sans-serif;color:var(--text);background:var(--bg)}
a{color:inherit}
header.topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 16px;background:var(--panel);border-bottom:1px solid var(--border);flex:0 0 auto}
header.topbar .left{display:flex;align-items:center;gap:12px}
header.topbar .brand{font-weight:700;color:var(--accent);text-decoration:none;font-size:16px}
header.topbar .right{display:flex;align-items:center;gap:6px}
#sidebarToggle{background:none;color:inherit;border:1px solid var(--border);border-radius:5px;padding:4px 9px;cursor:pointer;font:inherit}
.hostmenu{flex:0 0 auto}
details.dropdown{position:relative}
details.dropdown>summary{list-style:none;cursor:pointer;padding:6px 9px;border-radius:5px;user-select:none}
details.dropdown>summary::-webkit-details-marker{display:none}
details.dropdown[open]>summary{background:var(--border)}
details.dropdown .ddmenu{position:absolute;right:0;top:100%;margin-top:4px;background:var(--panel);border:1px solid var(--border);border-radius:6px;min-width:160px;box-shadow:0 4px 14px rgba(0,0,0,.15);z-index:30;padding:4px}
details.dropdown .ddmenu a,details.dropdown .ddmenu button{display:block;width:100%;text-align:left;padding:6px 10px;border:0;background:none;color:inherit;text-decoration:none;border-radius:4px;font:inherit;cursor:pointer}
details.dropdown .ddmenu a:hover,details.dropdown .ddmenu button:hover{background:var(--border)}
.body{display:flex;flex:1;min-height:0}
aside.sidebar{width:220px;min-width:140px;max-width:480px;resize:horizontal;overflow:auto;border-right:1px solid var(--border);background:var(--panel);flex:0 0 auto}
body.sidebar-hidden aside.sidebar{display:none}
aside.sidebar nav a{display:block;padding:6px 14px;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
aside.sidebar nav a:hover,aside.sidebar nav a.active{background:var(--border)}
aside.sidebar nav a.active{font-weight:700}
aside.sidebar .sqllink{border-top:1px solid var(--border);margin-top:6px;padding-top:6px}
main.content{flex:1;min-width:0;overflow:auto;padding:16px}
footer.sitefoot{flex:0 0 auto;padding:8px 16px;border-top:1px solid var(--border);background:var(--panel);font-size:12px;opacity:.65;text-align:center}
table{border-collapse:collapse;width:100%;background:var(--panel)}
th,td{padding:6px 8px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top;word-break:break-word}
.flash{padding:8px 12px;border-radius:6px;margin-bottom:12px;background:#e7f4ea}.err{background:#fde8e8}
@media (prefers-color-scheme:dark){.flash{background:#1d3b27}.err{background:#4a2020}}
button,a.btn{padding:5px 10px;border:0;border-radius:5px;color:#fff;background:var(--accent);cursor:pointer;text-decoration:none;display:inline-block;font:inherit}
button.d{background:#b42318}
input[type=text]{width:100%;box-sizing:border-box;font:inherit}
textarea{width:100%;box-sizing:border-box;font-family:ui-monospace,monospace;font-size:13px}
.pager a{margin-right:8px}
</style>
</head>
<body>
<header class="topbar">
  <div class="left">
    <button type="button" id="sidebarToggle" title="<?= esc(lang('Sync.syncUi.toggleSidebar')) ?>" aria-label="<?= esc(lang('Sync.syncUi.toggleSidebar')) ?>">&#9776;</button>
    <a class="brand" href="<?= esc(site_url($base)) ?>"><?= lang('Sync.syncUi.brand') ?></a>
  </div>
  <div class="right">
    <?php if (count($locales) > 1): ?>
    <details class="dropdown">
      <summary title="<?= esc(lang('Sync.syncUi.language')) ?>">&#127760; <?= esc(strtoupper($activeLocale)) ?></summary>
      <div class="ddmenu">
        <?php foreach ($locales as $code => $label): ?>
        <a href="<?= esc(site_url($base . '/locale/' . $code) . '?back=' . urlencode($currentUrl)) ?>"><?= esc($label) ?><?= $code === $activeLocale ? ' &#10003;' : '' ?></a>
        <?php endforeach ?>
      </div>
    </details>
    <?php endif ?>
    <details class="dropdown">
      <summary>&#128100; <?= esc($username) ?></summary>
      <div class="ddmenu">
        <?php if ($profileUrl !== ''): ?><a href="<?= esc($profileUrl) ?>"><?= lang('Sync.syncUi.profile') ?></a><?php endif ?>
        <form method="post" action="<?= esc($logoutUrl) ?>"><?= csrf_field() ?><button type="submit"><?= lang('Sync.syncUi.logout') ?></button></form>
      </div>
    </details>
  </div>
</header>
<?php if ($menu !== ''): ?><div class="hostmenu"><?= $menu ?></div><?php endif ?>
<div class="body">
  <aside class="sidebar" id="syncUiSidebar">
    <nav>
      <?php foreach ($sidebarTables as $t): ?>
      <a href="<?= esc(site_url($base . '/' . $t['name'])) ?>" class="<?= $t['name'] === $activeTable ? 'active' : '' ?>"><?= esc($t['name']) ?> <small>(<?= (int) $t['rows'] ?>)</small></a>
      <?php endforeach ?>
      <div class="sqllink"><a href="<?= esc(site_url($base . '/sql')) ?>"><?= lang('Sync.syncUi.runSql') ?></a></div>
    </nav>
  </aside>
  <main class="content">
    <?php if (session()->getFlashdata('message')): ?><div class="flash"><?= esc(session()->getFlashdata('message')) ?></div><?php endif ?>
    <?php if (session()->getFlashdata('error')): ?><div class="flash err"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
    <?= $this->renderSection('content') ?>
  </main>
</div>
<footer class="sitefoot"><?= lang('Sync.syncUi.footer') ?></footer>
<script>
(function () {
  var WKEY = 'syncui-sidebar-width';
  var HKEY = 'syncui-sidebar-hidden';
  var body = document.body;
  var side = document.getElementById('syncUiSidebar');
  var toggle = document.getElementById('sidebarToggle');
  try {
    if (localStorage.getItem(HKEY) === '1') { body.classList.add('sidebar-hidden'); }
    var w = localStorage.getItem(WKEY);
    if (w && side) { side.style.width = w + 'px'; }
  } catch (e) {}
  if (side && window.ResizeObserver) {
    new ResizeObserver(function () {
      try { localStorage.setItem(WKEY, String(Math.round(side.getBoundingClientRect().width))); } catch (e) {}
    }).observe(side);
  }
  if (toggle) {
    toggle.addEventListener('click', function () {
      body.classList.toggle('sidebar-hidden');
      try { localStorage.setItem(HKEY, body.classList.contains('sidebar-hidden') ? '1' : '0'); } catch (e) {}
    });
  }
})();
</script>
</body>
</html>

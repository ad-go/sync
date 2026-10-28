<?php
/** @var list<\CodeIgniter\Shield\Entities\User> $users */ /** @var list<string> $groups */ /** @var string $base */
use AdGo\Sync\Filters\AuthWriteLock;

$banner = AuthWriteLock::banner();
$readonly = $banner !== '';
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Users · <?= esc(\AdGo\Sync\Libraries\Branding::siteName()) ?></title>
<?= view('AdGo\Sync\Views\ui\_theme') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-dt@2.2.2/css/dataTables.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-buttons-dt@3.2.3/css/buttons.dataTables.min.css">
<style>
.off{opacity:.55}
td form{display:inline;margin-left:6px}
tr.rowlink{cursor:pointer}
/* sidebar add-user form */
.sb-add{padding:10px;border-top:1px solid var(--line);margin-top:4px}
.sb-add h4{font-size:12px;font-weight:700;color:var(--mute);text-transform:uppercase;letter-spacing:.05em;margin:0 0 8px}
.sb-add input,.sb-add select{width:100%;box-sizing:border-box;margin-bottom:6px}
.sb-add .grp{display:flex;flex-wrap:wrap;gap:2px 8px;margin-bottom:6px;font-size:12px}
.sb-add .grp label{display:flex;align-items:center;gap:3px;cursor:pointer}
.sb-add .row2{display:flex;gap:6px;align-items:center;margin-bottom:6px;font-size:12px}
.sb-add button{width:100%;margin-top:2px}
/* DT overrides to match package theme */
table.dataTable{width:100%!important}
table.dataTable thead th{background:var(--card);color:var(--mute);font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;border-bottom:1px solid var(--line)}
table.dataTable tbody tr:hover{background:var(--tint)}
table.dataTable td,table.dataTable th{border-bottom:1px solid var(--line)}
.dt-container .dt-search input,.dt-container .dt-length select{background:var(--card);color:var(--fg);border:1px solid var(--line);border-radius:6px;padding:4px 8px}
.dt-container .dt-buttons{margin-bottom:6px;display:flex;gap:6px;flex-wrap:wrap}
.dt-button{background:var(--card);color:var(--fg);border:1px solid var(--line);border-radius:6px;padding:4px 10px;font-size:13px;cursor:pointer}
.dt-button:hover{background:var(--tint);color:var(--acc)}
label.sb-import{display:inline-flex;align-items:center;gap:5px;background:var(--card);color:var(--fg);border:1px solid var(--line);border-radius:6px;padding:4px 10px;font-size:13px;cursor:pointer}
label.sb-import:hover{background:var(--tint);color:var(--acc)}
label.sb-import input{display:none}
</style>
</head>
<body>
<?= view('AdGo\Sync\Views\ui\_topbar') ?>
<div class="appshell">
<aside id="appSidebar">
  <?= view('AdGo\Sync\Views\ui\_nav', ['active' => 'users']) ?>
  <?= $menu ?? '' ?>
  <?php if (! $readonly): ?>
  <div class="sb-add">
    <h4>Add user</h4>
    <form method="post" action="<?= site_url($base) ?>" id="addForm">
      <?= csrf_field() ?>
      <input type="text" name="username" placeholder="Username" maxlength="30" required>
      <input type="email" name="email" placeholder="E-mail" maxlength="254" required autocomplete="off">
      <input type="password" name="password" placeholder="Password" autocomplete="new-password" required>
      <div class="grp"><?php foreach ($groups as $g): ?><label><input type="checkbox" name="groups[]" value="<?= esc($g) ?>" <?= $g === 'user' ? 'checked' : '' ?>><?= esc($g) ?></label><?php endforeach ?></div>
      <div class="row2"><label><input type="checkbox" name="active" value="1" checked> active</label></div>
      <button type="submit">Add</button>
    </form>
  </div>
  <?php endif ?>
</aside>
<div class="sb-resize" id="sbResize"></div>
<main class="appmain">
<h1>Users</h1>
<?= view('AdGo\Sync\Views\ui\_flash') ?>
<?php if ($readonly): ?><div class="flash warn">&#128274; <?= esc($banner) ?></div><?php endif ?>

<table id="usersTable" class="<?= $readonly ? '' : 'rowlink' ?>">
<thead><tr><th>#</th><th>Username</th><th>Email</th><th>Groups</th><th>Active</th><th></th></tr></thead>
<tbody>
<?php foreach ($users as $u): ?>
<?= view('AdGo\Sync\Views\users\_row', ['u' => $u, 'readonly' => $readonly, 'base' => $base]) ?>
<?php endforeach ?>
<?php if ($users === []): ?><tr><td colspan="6">No users.</td></tr><?php endif ?>
</tbody>
</table>

<div class="modal-backdrop" id="twofaBackdrop">
  <div class="modal">
    <button type="button" class="close" id="twofaClose" aria-label="Close">&times;</button>
    <h3>Authenticator (2FA)</h3>
    <div id="twofaBody">Loading&hellip;</div>
  </div>
</div>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.2.2/js/dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@3.2.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@3.2.3/js/buttons.html5.min.js"></script>
<script>
(function () {
  'use strict';

  /* DataTables */
  var dt = new DataTable('#usersTable', {
    pageLength: 25,
    layout: {
      topStart: 'buttons',
      topEnd:   'search',
      bottomStart: 'info',
      bottomEnd:   'paging'
    },
    buttons: [
      {
        extend: 'csvHtml5',
        text: 'Export CSV',
        exportOptions: { columns: [0,1,2,3,4] }
      },
      {
        text: '<label class="sb-import" title="Import CSV"><svg width="13" height="13" viewBox="0 0 13 13" fill="currentColor"><path d="M6.5 0v8M3 5l3.5 4L10 5M1 10v2h11v-2"/></svg> Import CSV<input type="file" id="importFile" accept=".csv"></label>',
        action: function() {}
      }
    ],
    columnDefs: [{ targets: 5, orderable: false, searchable: false }],
    language: { search: '', searchPlaceholder: 'Search…' }
  });

  /* Import CSV */
  document.addEventListener('change', function(e) {
    if (e.target.id !== 'importFile') return;
    var f = e.target.files[0];
    if (!f) return;
    var fd = new FormData();
    fd.append('csv', f);
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    fetch(<?= json_encode(site_url($base . '/import')) ?>, { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d){ alert(d.message || 'Done.'); if(d.ok) location.reload(); })
      .catch(function(){ alert('Import failed.'); });
    e.target.value = '';
  });

  /* 2FA modal */
  var backdrop = document.getElementById('twofaBackdrop');
  var body     = document.getElementById('twofaBody');

  function openModal(id) {
    body.textContent = 'Loading…';
    backdrop.classList.add('open');
    fetch(<?= json_encode(site_url($base)) ?> + '/' + id + '/2fa', { headers: { Accept: 'application/json' } })
      .then(function(r){ return r.json(); })
      .then(function(data) {
        if (data.error) { body.textContent = data.error; return; }
        var html = '<div class="qrwrap">' + data.qr + '</div>';
        html += '<p><small>Manual key:</small><code class="key">' + data.secret.replace(/[&<>]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]; }) + '</code></p>';
        html += '<p><small>Recovery codes (struck through = already used):</small></p><div class="codes">';
        data.codes.forEach(function(c){ html += '<div class="' + (c.used ? 'used' : '') + '">' + c.code + '</div>'; });
        html += '</div>';
        body.innerHTML = html;
      })
      .catch(function(){ body.textContent = 'Could not load.'; });
  }

  document.getElementById('usersTable').addEventListener('click', function(e) {
    var btn = e.target.closest('[data-view2fa]');
    if (btn) { openModal(btn.getAttribute('data-view2fa')); return; }
    if (e.target.closest('a,button,form,input,label')) { return; }
    var tr = e.target.closest('tr[data-edit-url]');
    if (tr) { window.location.href = tr.getAttribute('data-edit-url'); }
  });

  document.getElementById('twofaClose').addEventListener('click', function(){ backdrop.classList.remove('open'); });
  backdrop.addEventListener('click', function(e){ if(e.target === backdrop) backdrop.classList.remove('open'); });
})();
</script>
</body>
</html>

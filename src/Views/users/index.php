<?php
/** @var list<string> $groups */ /** @var string $base */ /** @var string $dteUrl */
use AdGo\Sync\Libraries\Branding;
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Users · <?= esc(Branding::siteName()) ?></title>
<?= view('AdGo\Sync\Views\ui\_theme') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= esc($dteUrl) ?>datatables.min.css">
<style>
/* undo Bootstrap resets that break our layout */
html,body{max-width:none!important;margin:0!important;padding:0!important;display:flex;flex-direction:column;min-height:100vh}
body{font:14px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;background:var(--bg);color:var(--fg)}
h1{font-size:22px;font-weight:700;margin:4px 0 14px}
/* DTE modal theme */
.DTED .modal-content{background:var(--card);color:var(--fg);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--shadow)}
.DTED .modal-header{border-bottom:1px solid var(--line);background:var(--card)}
.DTED .modal-body{background:var(--card)}
.DTED .modal-footer{border-top:1px solid var(--line);background:var(--card)}
.DTED .btn-primary{background:var(--acc)!important;border-color:var(--acc)!important}
.DTED .btn-primary:hover{background:var(--acc-d)!important;border-color:var(--acc-d)!important}
.DTED input.form-control,.DTED select.form-select,.DTED textarea.form-control{background:var(--card);color:var(--fg);border-color:var(--line)}
.DTED input.form-control:focus,.DTED select.form-select:focus{border-color:var(--acc);box-shadow:0 0 0 3px color-mix(in srgb,var(--acc) 22%,transparent)}
/* DT table */
table.dataTable{font-size:14px}
table.dataTable thead th{background:var(--card);color:var(--mute);font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
table.dataTable tbody td{cursor:pointer;background:var(--card)}
table.dataTable tbody tr:hover>td{background:var(--tint)!important}
table.dataTable tbody tr.selected>td{background:color-mix(in srgb,var(--acc) 12%,var(--card))!important}
div.dt-container .dt-search input,div.dt-container .dt-length select{background:var(--card);color:var(--fg);border:1px solid var(--line)}
/* DT Buttons — reuse BS5 btn-secondary baseline */
div.dt-buttons{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px}
div.dt-buttons .btn{background:var(--card);color:var(--fg);border:1px solid var(--line);font-size:13px;font-weight:600}
div.dt-buttons .btn:hover{background:var(--tint);color:var(--acc)}
div.dt-buttons .btn.btn-new{background:var(--acc);color:#fff;border-color:var(--acc)}
div.dt-buttons .btn.btn-new:hover{background:var(--acc-d);border-color:var(--acc-d)}
div.dt-buttons .btn.btn-delete{color:#c62828;border-color:#c62828}
div.dt-buttons .btn.btn-delete:hover{background:#c62828;color:#fff}
label.btn-import{display:inline-flex;align-items:center;gap:5px;cursor:pointer}
label.btn-import input{display:none}
/* 2FA modal (our own, not BS5's) */
.twofa-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9000;align-items:center;justify-content:center}
.twofa-backdrop.open{display:flex}
.sync-modal{background:var(--card);color:var(--fg);border-radius:var(--r);padding:20px;max-width:420px;width:92vw;max-height:85vh;overflow-y:auto;box-shadow:var(--shadow)}
.sync-modal h3{margin-top:0}.sync-modal .qrwrap{max-width:200px;margin:12px auto}.sync-modal .qrwrap svg{width:100%;height:auto}
.sync-modal code.key{word-break:break-all;display:block;background:var(--tint);padding:6px 8px;border-radius:6px;margin:6px 0}
.sync-modal .codes{display:grid;grid-template-columns:1fr 1fr;gap:4px;font-family:ui-monospace,Consolas,monospace;font-size:13px;margin-top:8px}
.sync-modal .codes .used{text-decoration:line-through;color:var(--mute)}
.sync-modal .close-btn{float:right;background:none;border:0;font-size:18px;cursor:pointer;color:var(--fg)}
/* restore our button style everywhere except DT/DTE/BS5-managed buttons */
.appmain button:not([class*="btn"]):not([class*="dt-"]):not(.close-btn){background:var(--acc);color:#fff;border:1px solid var(--acc);border-radius:8px;padding:8px 16px;font-weight:700;cursor:pointer}
.appmain button.s:not([class*="btn"]){background:transparent;color:var(--acc);border:1px solid var(--acc)}
.appmain button.d:not([class*="btn"]){background:transparent;color:#c62828;border:1px solid #c62828}
.close-btn{background:none!important;border:0!important;font-size:18px;cursor:pointer;color:var(--fg)!important;padding:0!important}
/* topbar and sidebar — exclude BS5 resets */
.apptopbar summary,.apptopbar .tb-burger{background:transparent;border:0;cursor:pointer;color:var(--fg);padding:5px 8px}
.apptopbar summary:hover,.apptopbar .tb-burger:hover{background:var(--tint)}
</style>
</head>
<body>
<?= view('AdGo\Sync\Views\ui\_topbar') ?>
<div class="appshell">
<aside id="appSidebar">
  <?= view('AdGo\Sync\Views\ui\_nav', ['active' => 'users']) ?>
  <?= $menu ?? '' ?>
</aside>
<div class="sb-resize" id="sbResize"></div>
<main class="appmain">
<h1>Users</h1>
<?= view('AdGo\Sync\Views\ui\_flash') ?>

<table id="usersTable" style="width:100%">
<thead><tr>
  <th>ID</th><th>Username</th><th>Email</th><th>Groups</th><th>Active</th><th data-dt-column="orderable:false;searchable:false"></th>
</tr></thead>
</table>

<div class="twofa-backdrop" id="twofaBackdrop">
  <div class="sync-modal">
    <button type="button" class="close-btn" id="twofaClose" aria-label="Close">&times;</button>
    <h3>Authenticator (2FA)</h3>
    <div id="twofaBody">Loading&hellip;</div>
  </div>
</div>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= esc($dteUrl) ?>datatables.min.js"></script>
<script src="<?= esc($dteUrl) ?>key.js"></script>
<script>
(function () {
  'use strict';

  /* tell DTE to use the Bootstrap 5 instance loaded above */
  DataTable.use(bootstrap);

  var BASE    = <?= json_encode(site_url($base)) ?>;
  var GROUPS  = <?= json_encode($groups) ?>;
  var CSRFN   = <?= json_encode(csrf_token()) ?>;
  var CSRF    = <?= json_encode(csrf_hash()) ?>;

  /* refresh CSRF from meta after each mutating call */
  function freshCsrf(data) {
    if (data && data[CSRFN]) { CSRF = data[CSRFN]; }
  }

  var editor = new DataTable.Editor({
    ajax: {
      url:  BASE + '/ajax',
      data: function (d) { d[CSRFN] = CSRF; },
      success: function(json) { freshCsrf(json); return json; }
    },
    table: '#usersTable',
    idSrc:  'DT_RowId',
    fields: [
      { label: 'Username', name: 'username' },
      { label: 'E-mail',   name: 'email',    type: 'text' },
      { label: 'Password', name: 'password', type: 'password', attr: { placeholder: 'Leave blank to keep', autocomplete: 'new-password' } },
      { label: 'Groups',   name: 'groups',   type: 'text',    attr: { placeholder: GROUPS.join(' | ') } },
      { label: 'Active',   name: 'active',   type: 'select',  options: [{label:'Yes', value:'1'},{label:'No', value:'0'}], def: '1' }
    ]
  });

  /* hide password on edit (optional — keep for resets) */
  editor.on('open', function (e, mode) {
    if (mode === 'edit') {
      editor.field('password').set('');
    }
  });

  var table = new DataTable('#usersTable', {
    ajax: {
      url:  BASE + '/ajax',
      type: 'GET',
      data: function(d) { d[CSRFN] = CSRF; }
    },
    columns: [
      { data: 'id',       className: 'dt-center', width: '40px' },
      { data: 'username' },
      { data: 'email' },
      { data: 'groups' },
      { data: 'active', render: function(v){ return v === '1' ? '&#10003;' : '&ndash;'; }, className: 'dt-center' },
      {
        data: null, orderable: false, searchable: false, className: 'dt-center', width: '36px',
        render: function(row) {
          var g = row.groups || '';
          if (!g.split('|').some(function(x){ return x.trim() === 'superadmin'; })) return '';
          return '<button class="btn-2fa s" data-id="' + row.id + '" title="View 2FA" style="padding:2px 7px;font-size:13px">&#x2399;</button>';
        }
      }
    ],
    select:  { style: 'os', selector: 'td:not(:last-child)' },
    layout: {
      topStart: 'buttons',
      topEnd:   'search',
      bottomStart: 'info',
      bottomEnd:   'paging'
    },
    buttons: [
      { extend: 'create', editor: editor, text: 'New', className: 'btn-new' },
      { extend: 'edit',   editor: editor, text: 'Edit' },
      { extend: 'remove', editor: editor, text: 'Delete', className: 'btn-delete' },
      { extend: 'csvHtml5', text: 'Export CSV', exportOptions: { columns: [0,1,2,3,4] } },
      {
        text: '<label class="btn-import"><svg width="13" height="13" viewBox="0 0 14 14" fill="currentColor"><path d="M7 0v9M4 6l3 4 3-4M1 11v2h12v-2"/></svg> Import CSV<input type="file" id="importFile" accept=".csv"></label>',
        action: function(){}
      }
    ]
  });

  /* Import CSV */
  document.addEventListener('change', function(e) {
    if (e.target.id !== 'importFile') return;
    var f = e.target.files[0];
    if (!f) return;
    var fd = new FormData();
    fd.append('csv', f);
    fd.append(CSRFN, CSRF);
    fetch(BASE + '/import', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d){ freshCsrf(d); alert(d.message || 'Done.'); if(d.ok) table.ajax.reload(); })
      .catch(function(){ alert('Import failed.'); });
    e.target.value = '';
  });

  /* 2FA modal */
  var backdrop = document.getElementById('twofaBackdrop');
  var twofaBody = document.getElementById('twofaBody');

  document.getElementById('usersTable').addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-2fa');
    if (!btn) return;
    e.stopPropagation();
    twofaBody.textContent = 'Loading…';
    backdrop.classList.add('open');
    fetch(BASE + '/' + btn.dataset.id + '/2fa', { headers: { Accept: 'application/json' } })
      .then(function(r){ return r.json(); })
      .then(function(data) {
        if (data.error) { twofaBody.textContent = data.error; return; }
        var html = '<div class="qrwrap">' + data.qr + '</div>';
        html += '<p><small>Manual key:</small><code class="key">' + data.secret.replace(/[&<>]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]; }) + '</code></p>';
        html += '<p><small>Recovery codes (struck through = used):</small></p><div class="codes">';
        data.codes.forEach(function(c){ html += '<div class="'+(c.used?'used':'')+'">' + c.code + '</div>'; });
        html += '</div>';
        twofaBody.innerHTML = html;
      })
      .catch(function(){ twofaBody.textContent = 'Could not load.'; });
  });

  document.getElementById('twofaClose').addEventListener('click', function(){ backdrop.classList.remove('open'); });
  backdrop.addEventListener('click', function(e){ if(e.target === backdrop) backdrop.classList.remove('open'); });
})();
</script>
</body>
</html>

<?php
/** @var list<string> $groups */ /** @var string $base */ /** @var string $dteUrl */
use AdGo\Sync\Libraries\Branding;
$title = 'Users · ' . esc(Branding::siteName());
ob_start();
?>
<link rel="stylesheet" href="<?= esc($dteUrl) ?>datatables.min.css">
<style>
/*
 * Bootstrap 5 loaded inside a CSS @layer so it has LOWER priority than any
 * unlayered rule (our _theme.php + overrides below). DTE BS5 bundle needs
 * Bootstrap CSS for its modal/form markup; this scopes the impact cleanly.
 */
@import url("https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css") layer(bootstrap);

/* Restore Bootstrap's .modal-backdrop: _theme.php sets display:none on it (for our own modals),
   but DTE uses Bootstrap's real .modal-backdrop and needs it visible. */
.modal-backdrop { display:block }

/* Reset _theme.php's .modal rules: _theme.php styles .modal as an inner card (max-width:420px etc.)
   but Bootstrap's DTE uses .modal as the full-viewport wrapper. */
.modal { background:transparent; border-radius:0; padding:0; max-width:none; width:100%; max-height:none; box-shadow:none;
         position:fixed; top:0; left:0; z-index:1055; display:none; overflow-x:hidden; overflow-y:auto; color:inherit }

/* --- DTE modal (Bootstrap 5 modal rendered by DTE) --- */
.DTED .modal-content{background:var(--card);color:var(--fg);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--shadow)}
.DTED .modal-header{border-bottom:1px solid var(--line);background:var(--card);padding:14px 18px}
.DTED .modal-title{font-size:16px;font-weight:700;margin:0}
.DTED .modal-body{background:var(--card);padding:16px 18px}
.DTED .modal-footer{border-top:1px solid var(--line);background:var(--card);padding:10px 18px;gap:8px}
.DTED .col-form-label{font-size:12px;font-weight:600;color:var(--mute);text-transform:uppercase;letter-spacing:.04em;padding:0;margin-bottom:4px}
.DTED .form-control,.DTED .form-select{background:var(--card);color:var(--fg);border-color:var(--line);border-radius:8px}
.DTED .form-control:focus,.DTED .form-select:focus{border-color:var(--acc);box-shadow:0 0 0 3px color-mix(in srgb,var(--acc) 22%,transparent)}
.DTED .btn-primary{background:var(--acc);border-color:var(--acc);color:#fff}
.DTED .btn-primary:hover{background:var(--acc-d);border-color:var(--acc-d)}
.DTED .btn-secondary{background:transparent;border-color:var(--line);color:var(--fg)}
.DTED .btn-secondary:hover{background:var(--tint)}
.DTED .btn-danger{background:transparent;border-color:#c62828;color:#c62828}
.DTED .btn-danger:hover{background:#c62828;color:#fff}
.DTED .btn-close{opacity:.7;background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%231c1f26'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e")}
.DTED .btn-close:hover{opacity:1}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]) .DTED .btn-close{background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23e6e8ec'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e")}}
:root[data-theme="dark"] .DTED .btn-close{background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23e6e8ec'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e")}
.DTED .DTE_Field_Error{color:var(--err-fg);font-size:12px;margin-top:3px}

/* --- DT toolbar buttons --- */
div.dt-buttons{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px}
div.dt-buttons .btn{background:var(--card);color:var(--fg);border:1px solid var(--line);font-size:13px;font-weight:600;border-radius:6px;padding:5px 12px}
div.dt-buttons .btn:hover{background:var(--tint);color:var(--acc)}
div.dt-buttons .btn.btn-new{background:var(--acc);color:#fff;border-color:var(--acc)}
div.dt-buttons .btn.btn-new:hover{background:var(--acc-d);border-color:var(--acc-d)}
div.dt-buttons .btn.btn-delete{background:transparent;color:#c62828;border-color:#c62828}
div.dt-buttons .btn.btn-delete:hover{background:#c62828;color:#fff}
label.btn-import{display:inline-flex;align-items:center;gap:5px;cursor:pointer;font-weight:600;font-size:13px}
label.btn-import input{display:none}

/* --- DT table --- */
table.dataTable{font-size:14px}
table.dataTable thead th{background:var(--card);color:var(--mute);font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;border-bottom:1px solid var(--line)}
table.dataTable tbody td{cursor:pointer;background:var(--card);color:var(--fg);border-bottom:1px solid var(--line)}
table.dataTable tbody tr:hover>td{background:var(--tint)}
table.dataTable tbody tr.selected>td{background:color-mix(in srgb,var(--acc) 12%,var(--card))}
div.dt-container .dt-search input{background:var(--card);color:var(--fg);border:1px solid var(--line);border-radius:6px;padding:4px 8px}
div.dt-container .dt-length select{background:var(--card);color:var(--fg);border:1px solid var(--line);border-radius:6px;padding:4px 6px}

/* --- DT pagination (Bootstrap 5 .pagination structure) --- */
div.dt-paging .pagination{display:flex;gap:3px;list-style:none;margin:0;padding:0}
div.dt-paging .page-item .page-link{display:inline-flex;align-items:center;justify-content:center;min-width:30px;height:30px;padding:0 8px;background:var(--card);color:var(--fg);border:1px solid var(--line);border-radius:6px;cursor:pointer;font-size:13px;text-decoration:none;transition:background .12s}
div.dt-paging .page-item .page-link:hover{background:var(--tint);color:var(--acc)}
div.dt-paging .page-item.active .page-link{background:var(--acc);color:#fff;border-color:var(--acc)}
div.dt-paging .page-item.disabled .page-link{opacity:.45;pointer-events:none}

/* DTE checkbox list — BS5 DTE renders <div class="form-check"><input class="form-check-input"><label class="form-check-label"> */
.DTED .DTE_Field_Type_checkbox .checkbox{display:flex;flex-direction:column;gap:6px;margin-top:4px}
.DTED .DTE_Field_Type_checkbox .form-check{display:flex;align-items:center;gap:8px;padding-left:0;min-height:unset}
.DTED .DTE_Field_Type_checkbox .form-check-input{float:none;margin:0;flex-shrink:0;width:15px;height:15px;accent-color:var(--acc);cursor:pointer;position:static}
.DTED .DTE_Field_Type_checkbox .form-check-label{font-size:13px;font-weight:400;cursor:pointer;margin:0}
/* fallback: DTE with <label><span><input></span><span>text</span></label> structure */
.DTED .DTE_Field_Type_checkbox label{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:400;margin:0;text-transform:none;letter-spacing:0;cursor:pointer}
.DTED .DTE_Field_Type_checkbox input[type=checkbox]{width:15px;height:15px;accent-color:var(--acc);cursor:pointer;flex-shrink:0}

/* --- 2FA modal (our own, unrelated to BS5) --- */
.twofa-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9000;align-items:center;justify-content:center}
.twofa-backdrop.open{display:flex}
.sync-modal{background:var(--card);color:var(--fg);border-radius:var(--r);padding:20px;max-width:420px;width:92vw;max-height:85vh;overflow-y:auto;box-shadow:var(--shadow)}
.sync-modal h3{margin-top:0}.sync-modal .qrwrap{max-width:200px;margin:12px auto}.sync-modal .qrwrap svg{width:100%;height:auto}
.sync-modal code.key{word-break:break-all;display:block;background:var(--tint);padding:6px 8px;border-radius:6px;margin:6px 0}
.sync-modal .codes{display:grid;grid-template-columns:1fr 1fr;gap:4px;font-family:ui-monospace,Consolas,monospace;font-size:13px;margin-top:8px}
.sync-modal .codes .used{text-decoration:line-through;color:var(--mute)}
.sync-modal .close-btn{float:right;background:none!important;border:0!important;font-size:18px;cursor:pointer;color:var(--fg);padding:0}
</style>
<?php $head = ob_get_clean(); ?>
<?php ob_start(); ?>

<h1>Users</h1>

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

<?php $content = ob_get_clean(); ?>
<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= esc($dteUrl) ?>datatables.min.js"></script>
<script src="<?= esc($dteUrl) ?>key.bak"></script>
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
      { label: 'Groups', name: 'groups', type: 'checkbox',
        options: GROUPS.map(function(g){ return { label: g, value: g }; }) },
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
      { data: 'groups', render: function(v){
          return (Array.isArray(v) ? v : String(v || '').split('|').filter(Boolean)).join(', ');
        } },
      { data: 'active', render: function(v){ return v === '1' ? '&#10003;' : '&ndash;'; }, className: 'dt-center' },
      {
        data: null, orderable: false, searchable: false, className: 'dt-center', width: '60px',
        render: function(row) {
          var g = Array.isArray(row.groups) ? row.groups : String(row.groups || '').split('|').filter(Boolean);
          var h = '';
          var act = 'display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;padding:0;font-size:14px';
          if (g.some(function(x){ return x.trim() === 'superadmin'; })) {
            h += '<button class="btn-2fa s" data-id="' + row.id + '" title="View 2FA" style="' + act + ';margin-right:4px"><i class="bi bi-shield-lock"></i></button>';
          }
          h += '<button class="btn-del s" data-id="' + row.id + '" title="Delete" style="' + act + ';color:#c62828;border-color:#c62828"><i class="bi bi-trash3"></i></button>';
          return h;
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
      { extend: 'csvHtml5', text: 'Export CSV', exportOptions: { columns: [0,1,2,3,4] } },
      {
        text: '<label class="btn-import"><i class="bi bi-upload"></i> Import CSV<input type="file" id="importFile" accept=".csv"></label>',
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

  /* click row -> edit */
  table.on('click', 'tbody tr', function(e) {
    if (e.target.closest('button')) return;
    editor.edit(this);
  });

  /* 2FA modal */
  var backdrop = document.getElementById('twofaBackdrop');
  var twofaBody = document.getElementById('twofaBody');

  document.getElementById('usersTable').addEventListener('click', function(e) {
    var delBtn = e.target.closest('.btn-del');
    if (delBtn) {
      e.stopPropagation();
      editor.remove(delBtn.closest('tr'), { title: 'Delete user', message: 'This action cannot be undone.', buttons: { label: 'Delete', className: 'btn-danger' } });
      return;
    }
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
<?php $scripts = ob_get_clean(); ?>
<?= view('AdGo\Sync\Views\ui\_layout', compact('title', 'head', 'content', 'scripts')) ?>

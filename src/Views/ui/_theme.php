<?php
/**
 * App chrome CSS: CSS variables (blue accent #1d5fd1), dark mode, topbar, appshell/sidebar, nav, tables, forms.
 * Include in <head> before page-specific styles so those can override safely.
 * Ends with Branding::css() so a custom accent / font set via Settings wins the cascade.
 */
use AdGo\Sync\Libraries\Branding;
?>
<script>try{var _t=localStorage.getItem('theme');if(_t)document.documentElement.setAttribute('data-theme',_t);}catch(e){}</script>
<style>
:root{--bg:#f6f7f9;--fg:#1c1f26;--card:#fff;--line:#dde;--acc:#1d5fd1;--acc-d:#1552b5;--tint:#eef2fb;--mute:#6b7380;--ok-bg:#e8f6ee;--ok-fg:#14532d;--err-bg:#fdecec;--err-fg:#7a1212;--warn-bg:#fff4d6;--warn-fg:#5c4400;--shadow:0 1px 3px rgba(29,36,48,.08),0 4px 14px rgba(29,36,48,.05);--r:10px}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){--bg:#14161a;--fg:#e6e8ec;--card:#1e2128;--line:#343944;--acc:#6aa2ff;--acc-d:#4d8bf0;--tint:#1a2535;--mute:#9aa3b2;--ok-bg:#143322;--ok-fg:#bfeacf;--err-bg:#431414;--err-fg:#fbd0d0;--warn-bg:#3d3000;--warn-fg:#ffe9a8;--shadow:0 1px 3px rgba(0,0,0,.4)}}
html[data-theme="dark"]{--bg:#14161a;--fg:#e6e8ec;--card:#1e2128;--line:#343944;--acc:#6aa2ff;--acc-d:#4d8bf0;--tint:#1a2535;--mute:#9aa3b2;--ok-bg:#143322;--ok-fg:#bfeacf;--err-bg:#431414;--err-fg:#fbd0d0;--warn-bg:#3d3000;--warn-fg:#ffe9a8;--shadow:0 1px 3px rgba(0,0,0,.4)}
html[data-theme="light"]{--bg:#f6f7f9;--fg:#1c1f26;--card:#fff;--line:#dde;--acc:#1d5fd1;--acc-d:#1552b5;--tint:#eef2fb;--mute:#6b7380;--ok-bg:#e8f6ee;--ok-fg:#14532d;--err-bg:#fdecec;--err-fg:#7a1212;--warn-bg:#fff4d6;--warn-fg:#5c4400;--shadow:0 1px 3px rgba(29,36,48,.08),0 4px 14px rgba(29,36,48,.05)}
html,body{max-width:none;margin:0;padding:0}
body{font:14px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;background:var(--bg);color:var(--fg);display:flex;flex-direction:column;min-height:100vh}
h1{font-size:22px;font-weight:700;margin:4px 0 14px;letter-spacing:-.01em}
h2{font-size:17px;font-weight:700}
a{color:var(--acc);text-decoration:none}a:hover{color:var(--acc-d);text-decoration:underline}
/* topbar */
.apptopbar{flex:0 0 auto;position:sticky;top:0;z-index:50;display:flex;align-items:center;gap:12px;padding:8px 14px;background:var(--card)}
.tb-burger{display:none;background:transparent;border:0;cursor:pointer;padding:5px 7px;color:var(--fg);font-size:20px;line-height:1;border-radius:6px;align-items:center;justify-content:center;flex:0 0 auto}
.tb-burger:hover{background:var(--tint)}
.apptopbar .tb-brand{font-weight:800;font-size:16px;color:var(--acc);text-decoration:none}
.apptopbar .tb-sp{flex:1}
.apptopbar details.tb-dd{position:relative}
.apptopbar details.tb-dd>summary{list-style:none;cursor:pointer;padding:5px 8px;border:0;background:transparent;font-size:13px;font-weight:700;color:var(--fg);border-radius:6px;transition:background .12s;display:flex;align-items:center;gap:5px}
.apptopbar details.tb-dd>summary::-webkit-details-marker{display:none}
.apptopbar details.tb-dd>summary:hover{background:var(--tint)}
.apptopbar .tb-ddmenu{position:absolute;right:0;top:calc(100% + 4px);background:var(--card);border:1px solid var(--line);border-radius:8px;box-shadow:0 8px 24px rgba(20,24,30,.12);padding:4px;min-width:120px;z-index:60}
.apptopbar .tb-ddmenu a{display:block;padding:7px 11px;border-radius:6px;color:var(--fg);text-decoration:none;font-size:13px}
.apptopbar .tb-ddmenu a:hover{background:var(--tint);color:var(--fg)}
.apptopbar .tb-ddmenu hr{border:0;border-top:1px solid var(--line);margin:3px 4px}
.tb-flag{width:22px;height:16px;vertical-align:middle;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.15);object-fit:cover}
.tb-switch{display:inline-flex;align-items:center;gap:4px;flex:0 0 auto;cursor:pointer}
.tb-sw-icon{font-size:13px;line-height:1;user-select:none;flex:0 0 auto;color:var(--fg)}
.tb-sw-track{position:relative;display:inline-block;width:28px;height:16px;flex:0 0 28px}
.tb-sw-track input{opacity:0;width:0;height:0;position:absolute}
.tb-slider{position:absolute;inset:0;background:var(--line);border-radius:16px;cursor:pointer;transition:background .2s}
.tb-slider::before{content:'';position:absolute;height:10px;width:10px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.25)}
.tb-sw-track input:checked+.tb-slider{background:var(--acc)}
.tb-sw-track input:checked+.tb-slider::before{transform:translateX(12px)}
/* topbar/content drag handle */
#tbResize{flex:0 0 1px;cursor:row-resize;background:transparent;position:relative;z-index:49}
#tbResize:hover,#tbResize.dragging{background:var(--acc);opacity:.25}
/* appshell */
.appshell{flex:1;display:flex;min-height:0;overflow:hidden}
.appshell>#appSidebar{width:220px;min-width:150px;max-width:400px;overflow-y:auto;background:var(--card);flex:0 0 auto;border-right:0}
.appshell>.sb-resize{flex:0 0 2px;cursor:col-resize;background:transparent}
.appshell>.appmain{flex:1;min-width:0;padding:16px;overflow:auto}
/* sidebar nav.menu */
.appshell nav.menu{display:flex;flex-direction:column;flex-wrap:nowrap;align-items:stretch;gap:2px;padding:10px;background:transparent;border:0;border-radius:0;box-shadow:none;font-size:13px;font-weight:600}
.appshell nav.menu .brand,.appshell nav.menu .sp,.appshell nav.menu .who,.appshell nav.menu a.lang{display:none}
.appshell nav.menu a{padding:7px 10px;border-radius:6px;border-bottom:0;color:inherit;text-decoration:none}
.appshell nav.menu a:hover,.appshell nav.menu a[aria-current=page]{background:var(--tint);color:var(--acc)}
/* sidebar backdrop (mobile) */
#sbBackdrop{position:fixed;inset:0;background:rgba(0,0,0,.38);z-index:199;opacity:0;pointer-events:none;transition:opacity .22s}
#sbBackdrop.open{opacity:1;pointer-events:auto}
/* responsive: tablet & phone */
@media (max-width:768px){
  .tb-burger{display:flex}
  .apptopbar{gap:8px;padding:7px 12px}
  .appshell{overflow:visible}
  .appshell>#appSidebar{position:fixed;top:0;left:0;bottom:0;z-index:200;transform:translateX(-110%);transition:transform .22s ease;box-shadow:4px 0 24px rgba(0,0,0,.18);width:240px!important;min-width:unset;max-width:unset}
  .appshell>#appSidebar.open{transform:translateX(0)}
  .appshell>.sb-resize{display:none}
  .appshell>.appmain{padding:12px}
}
nav.menu{background:var(--card);border:1px solid var(--line);border-bottom:3px solid var(--acc);border-radius:var(--r);box-shadow:var(--shadow);padding:10px 16px;gap:4px 20px;font-size:14px}
nav.menu a{color:var(--fg);font-weight:600;padding:4px 2px;border-bottom:2px solid transparent;text-decoration:none}
nav.menu a:hover{color:var(--acc);border-bottom-color:var(--acc)}
nav.menu a[aria-current=page]{color:var(--acc);border-bottom-color:var(--acc)}
nav.menu a.brand{font-size:20px;font-weight:800;color:var(--acc);letter-spacing:-.02em;border-bottom:0;margin-right:10px}
nav.menu .who{color:var(--mute)}
nav.menu .badge{background:var(--acc);border-radius:999px;padding:1px 7px}
table{border-collapse:separate;border-spacing:0;background:var(--card);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--shadow);overflow:hidden}
th{background:var(--tint);color:var(--mute);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
th,td{border-bottom:1px solid var(--line);padding:9px 12px}
tbody tr:last-child td,table tr:last-child th,table tr:last-child td{border-bottom:0}
tbody tr:hover td{background:var(--tint)}
form.card{border-radius:var(--r);box-shadow:var(--shadow)}
input[type=text],input[type=email],input[type=number],input[type=password],textarea,select{background:var(--card);color:var(--fg);border:1px solid var(--line);border-radius:8px;padding:8px 10px;transition:border-color .12s,box-shadow .12s}
input:focus,textarea:focus,select:focus{outline:0;border-color:var(--acc);box-shadow:0 0 0 3px color-mix(in srgb,var(--acc) 22%,transparent)}
input[type=checkbox],input[type=radio],input[type=color]{accent-color:var(--acc)}
button,a.btn{background:var(--acc);color:#fff;border:1px solid var(--acc);border-radius:8px;padding:8px 16px;font-weight:700;cursor:pointer;text-decoration:none;transition:background .12s}
button:hover,a.btn:hover{background:var(--acc-d);border-color:var(--acc-d);color:#fff;text-decoration:none}
button.s{background:transparent;color:var(--acc);border:1px solid var(--acc)}
button.s:hover{background:var(--tint);color:var(--acc-d)}
td button.d,button.d{background:transparent;color:#c62828;border:1px solid #c62828;border-radius:8px;padding:3px 10px}
td button.d:hover,button.d:hover{background:#c62828;color:#fff}
.flash{border-radius:8px;border-left:4px solid #2e9e5b;background:var(--ok-bg);color:var(--ok-fg);padding:10px 14px}
.flash.err,.err{border-left-color:#d32f2f;background:var(--err-bg);color:var(--err-fg)}
.flash.warn,.warn,.banner,.warnbox{border-left-color:var(--acc);background:var(--warn-bg);color:var(--warn-fg)}
.hint,.note,.state{color:var(--mute)}
.tag{border-radius:999px;background:var(--tint);color:var(--acc-d)}.tag.danger,.danger{background:var(--err-bg);color:var(--err-fg)}
tr.fresh{animation:fresh 1.6s ease-out}@keyframes fresh{from{background:var(--tint)}to{background:transparent}}
#toast{border-radius:10px;box-shadow:var(--shadow)}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:90;align-items:center;justify-content:center}
.modal-backdrop.open{display:flex}
.modal{background:var(--card);color:var(--fg);border-radius:var(--r);padding:20px;max-width:420px;width:92vw;max-height:85vh;overflow:auto;box-shadow:var(--shadow)}
.modal h3{margin-top:0}.modal .qrwrap{max-width:200px;margin:12px auto}.modal .qrwrap svg{width:100%;height:auto}
.modal code.key{word-break:break-all;display:block;background:var(--tint);padding:6px 8px;border-radius:6px;margin:6px 0}
.modal .codes{display:grid;grid-template-columns:1fr 1fr;gap:4px;font-family:ui-monospace,Consolas,monospace;font-size:13px;margin-top:8px}
.modal .codes .used{text-decoration:line-through;color:var(--mute)}
.modal .close{float:right;background:none;border:0;font-size:18px;cursor:pointer;color:var(--fg)}
<?= Branding::css() ?>
</style>

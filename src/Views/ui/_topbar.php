<?php
/**
 * Sticky topbar: brand, language dropdown with flag SVGs, dark-mode toggle, user dropdown (profile/logout).
 * Followed by #tbResize drag handle (row-resize between topbar and appshell).
 * Uses Branding::siteName() (reads Sync.siteName setting, falls back to 'Sync').
 */
use AdGo\Sync\Libraries\Branding;

$user = auth()->user();

$flag = static function (string $code): string {
    $cc = \AdGo\Sync\Libraries\Branding::flagCode($code);
    if ($cc === null) {
        return '';
    }
    return '<img class="tb-flag" src="' . esc(site_url("flags/{$cc}.svg")) . '" alt="" width="22" height="16">';
};

$locale  = service('request')->getLocale();
$locales = \AdGo\Sync\Libraries\Branding::locales();
$langs   = array_diff($locales, [$locale]);
try {
    $logoutUrl = url_to('logout');
} catch (\Throwable) {
    $logoutUrl = site_url('logout');
}
$syncCfg    = config('Sync');
$usersBase  = rtrim($syncCfg->usersPath ?? 'admin/users', '/');
$profileUrl = $user !== null ? site_url($usersBase . '/' . $user->id . '/edit') : '#';
$userName   = $user !== null ? (string) ($user->username ?? $user->email ?? '') : '';
$nodeName   = esc($syncCfg->node);
$siteName   = Branding::siteName();
$logoUrl    = Branding::logoUrl();
$dtePay     = (bool) (setting('Sync.pay') ?? $syncCfg->pay);
$dteUrl     = site_url(rtrim($syncCfg->settingsPath, '/') . '/dte');
?>
<header class="apptopbar" id="appTopbar">
  <button id="tbBurger" class="tb-burger" aria-label="<?= esc(lang('Ui.menu_label')) ?>">&#9776;</button>
  <a class="tb-brand" href="<?= site_url('/') ?>">
    <?php if ($logoUrl !== ''): ?>
      <img src="<?= esc($logoUrl) ?>" alt="<?= esc($siteName) ?>" style="max-height:28px;vertical-align:middle">
    <?php else: ?>
      <?= esc($siteName) ?>
    <?php endif ?>
  </a>
  <span class="tb-sp"></span>
  <?php if (count($locales) > 2): /* three or more languages: one language icon that opens a list of flags and names */ ?>
  <details class="tb-dd tb-lang-dd">
    <summary class="tb-lang-btn" title="<?= esc(lang('Ui.language_label')) ?>" aria-label="<?= esc(lang('Ui.language_label')) ?>"><i class="bi bi-translate"></i></summary>
    <div class="tb-ddmenu">
      <?php foreach ($locales as $code): ?>
      <a href="<?= site_url('lang/' . $code) ?>"<?= $code === $locale ? ' aria-current="true"' : '' ?> class="tb-lang-item"><?= $flag($code) ?> <span><?= esc(\AdGo\Sync\Libraries\Branding::localeName($code)) ?></span></a>
      <?php endforeach ?>
    </div>
  </details>
  <style>
    .tb-lang-btn{list-style:none;cursor:pointer;display:inline-flex;align-items:center;padding:4px 8px;border-radius:6px;font-size:16px;color:var(--acc)}
    .tb-lang-btn::-webkit-details-marker{display:none}
    .tb-lang-item{display:flex;align-items:center;gap:8px;text-decoration:none;white-space:nowrap}
    .tb-lang-item[aria-current="true"]{font-weight:700;color:var(--acc)}
  </style>
  <?php else: /* one or two languages: a flag per language the page is NOT in; a click switches to it */ ?>
  <?php foreach ($langs as $code): ?>
  <a class="tb-lang" href="<?= site_url('lang/' . $code) ?>" title="<?= esc(strtoupper($code)) ?>"
     style="display:inline-flex;align-items:center;padding:4px 4px;border-radius:6px;line-height:0;text-decoration:none"><?= $flag($code) ?: esc(strtoupper($code)) ?></a>
  <?php endforeach ?>
  <?php endif ?>
  <form method="post" action="<?= esc($dteUrl) ?>" style="display:inline;margin:0" title="<?= esc($dtePay ? lang('Ui.dte_on_title') : lang('Ui.dte_off_title')) ?>">
    <?= csrf_field() ?>
    <button type="submit" style="background:none;border:none;cursor:pointer;padding:4px 6px;border-radius:6px;line-height:1;font-size:15px;color:<?= $dtePay ? 'var(--ok-fg)' : 'var(--mute)' ?>;transition:color .15s" title="<?= esc($dtePay ? lang('Ui.dte_on') : lang('Ui.dte_off')) ?>"><i class="bi bi-patch-<?= $dtePay ? 'check-fill' : 'question' ?>"></i></button>
  </form>
  <label class="tb-switch" title="<?= esc(lang('Ui.theme_toggle')) ?>"><span class="tb-sw-icon" id="tbThemeIcon">&#9788;</span><span class="tb-sw-track"><input type="checkbox" id="tbTheme"><span class="tb-slider"></span></span></label>
  <details class="tb-dd tb-user">
    <summary><?= $nodeName ?><?= $userName !== '' ? ' &middot; ' . esc($userName) : '' ?></summary>
    <div class="tb-ddmenu">
      <a href="<?= esc($profileUrl) ?>"><?= lang('Ui.profile') ?></a>
      <hr>
      <a href="<?= esc($logoutUrl) ?>"><?= lang('Ui.sign_out') ?></a>
    </div>
  </details>
</header>
<div id="tbResize"></div>
<script>
!function(){
  /* dark mode toggle switch */
  var chk=document.getElementById('tbTheme');
  if(chk){
    function applyTheme(t){
      document.documentElement.setAttribute('data-theme',t);
      chk.checked=(t==='dark');
      var ic=document.getElementById('tbThemeIcon');
      if(ic)ic.textContent=t==='dark'?'☾':'☀';
      try{localStorage.setItem('theme',t);}catch(e){}
    }
    var cur=document.documentElement.getAttribute('data-theme')||(window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
    applyTheme(cur);
    chk.addEventListener('change',function(){applyTheme(chk.checked?'dark':'light');});
  }
  /* topbar resize */
  var tb=document.getElementById('appTopbar'),r=document.getElementById('tbResize');
  if(tb&&r){
    try{var th=localStorage.getItem('appshell-th');if(th)tb.style.minHeight=th;}catch(e){}
    var d=false,y0,H0;
    r.addEventListener('mousedown',function(e){d=true;y0=e.clientY;H0=tb.offsetHeight;r.classList.add('dragging');document.body.style.cssText+='cursor:row-resize;user-select:none';e.preventDefault();});
    document.addEventListener('mousemove',function(e){if(!d)return;var n=Math.max(36,Math.min(120,H0+(e.clientY-y0)));tb.style.minHeight=n+'px';});
    document.addEventListener('mouseup',function(){if(!d)return;d=false;r.classList.remove('dragging');document.body.style.cursor='';document.body.style.userSelect='';try{localStorage.setItem('appshell-th',tb.style.minHeight);}catch(e){};});
  }
  /* burger + sidebar overlay */
  document.addEventListener('DOMContentLoaded',function(){
    var burger=document.getElementById('tbBurger');
    var sb=document.getElementById('appSidebar');
    var bd=document.createElement('div');bd.id='sbBackdrop';document.body.appendChild(bd);
    function open(){if(sb){sb.classList.add('open');bd.classList.add('open');}}
    function close(){if(sb){sb.classList.remove('open');bd.classList.remove('open');}}
    if(burger)burger.addEventListener('click',function(){sb&&sb.classList.contains('open')?close():open();});
    bd.addEventListener('click',close);
    if(sb)sb.querySelectorAll('a').forEach(function(a){a.addEventListener('click',close);});
    /* sidebar column-resize handle */
    var r2=document.getElementById('sbResize');
    if(sb&&r2){
      try{var w=localStorage.getItem('appshell-sw');if(w)sb.style.width=w;}catch(e){}
      var d2=false,x2,W2;
      r2.addEventListener('mousedown',function(e){d2=true;x2=e.clientX;W2=sb.offsetWidth;document.body.style.cssText+='cursor:col-resize;user-select:none';});
      document.addEventListener('mousemove',function(e){if(!d2)return;var n=Math.max(150,Math.min(400,W2+e.clientX-x2));sb.style.width=n+'px';});
      document.addEventListener('mouseup',function(){if(!d2)return;d2=false;document.body.style.cursor='';document.body.style.userSelect='';try{localStorage.setItem('appshell-sw',sb.style.width);}catch(e){};});
    }
  });
}();
</script>

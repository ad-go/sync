<?php
/**
 * Sticky topbar: brand, language dropdown with flag SVGs, dark-mode toggle, user dropdown (profile/logout).
 * Followed by #tbResize drag handle (row-resize between topbar and appshell).
 * Uses Branding::siteName() (reads Sync.siteName setting, falls back to 'Sync').
 */
use AdGo\Sync\Libraries\Branding;

$user = auth()->user();

$flag = static function (string $code): string {
    $cc = [
        'en' => 'gb', 'ro' => 'ro', 'de' => 'de', 'fr' => 'fr', 'es' => 'es', 'it' => 'it',
        'pt' => 'pt', 'nl' => 'nl', 'pl' => 'pl', 'ru' => 'ru', 'tr' => 'tr', 'ar' => 'sa',
        'zh' => 'cn', 'ja' => 'jp',
    ][$code] ?? null;
    if ($cc === null) {
        return '';
    }
    return '<img class="tb-flag" src="' . esc(site_url("flags/{$cc}.svg")) . '" alt="" width="22" height="16">';
};

$locale = service('request')->getLocale();
$langs  = array_diff(config('App')->supportedLocales, [$locale]);
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
?>
<header class="apptopbar" id="appTopbar">
  <button id="tbBurger" class="tb-burger" aria-label="Menu">&#9776;</button>
  <a class="tb-brand" href="<?= site_url('/') ?>">
    <?php if ($logoUrl !== ''): ?>
      <img src="<?= esc($logoUrl) ?>" alt="<?= esc($siteName) ?>" style="max-height:28px;vertical-align:middle">
    <?php else: ?>
      <?= esc($siteName) ?>
    <?php endif ?>
  </a>
  <span class="tb-sp"></span>
  <?php if ($langs !== []): ?>
  <details class="tb-dd">
    <summary><?= $flag($locale) ?></summary>
    <div class="tb-ddmenu">
      <?php foreach ($langs as $code): ?>
      <a href="<?= site_url('lang/' . $code) ?>"><?= $flag($code) ?> <?= esc(strtoupper($code)) ?></a>
      <?php endforeach ?>
    </div>
  </details>
  <?php endif ?>
  <label class="tb-switch" title="Toggle dark/light"><span class="tb-sw-icon" id="tbThemeIcon">&#9788;</span><span class="tb-sw-track"><input type="checkbox" id="tbTheme"><span class="tb-slider"></span></span></label>
  <details class="tb-dd tb-user">
    <summary><?= $nodeName ?><?= $userName !== '' ? ' &middot; ' . esc($userName) : '' ?></summary>
    <div class="tb-ddmenu">
      <a href="<?= esc($profileUrl) ?>">Profile</a>
      <hr>
      <a href="<?= esc($logoutUrl) ?>">Sign out</a>
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

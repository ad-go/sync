<?php
/**
 * The `.cfshell`/`.cftopbar` chrome CSS shared by every admin page (conflicts, status, settings, users):
 * sticky top bar, resizable sidebar, the `_adminNav` page-switcher styling, dark mode, and the responsive
 * collapse under 760px. Included inside each page's own `<style>` block, right after its page-specific rules,
 * so the page's own selectors can still override it where needed. Ends with the live branding override
 * (`Branding::css()`), which must stay last to win the cascade over the defaults above it.
 */
?>
html body{max-width:none;margin:0;padding:0;display:flex;flex-direction:column;min-height:100vh}
.cftopbar{flex:0 0 auto;position:sticky;top:0;z-index:50;display:flex;align-items:center;gap:10px;padding:8px 14px;background:#fff;border-bottom:1px solid transparent;min-height:20px;box-sizing:border-box}
.cftopbar .cftb-brand{font-weight:800;font-size:16px;color:#1d5fd1;text-decoration:none}
.cftopbar .cftb-logo{height:18px;vertical-align:middle;margin-right:6px}
.cftopbar .cftb-sp{flex:1}
.cftopbar .cftb-who{color:#6b7380;font-size:12px}
.cftopbar .cftb-dd{position:relative}
.cftopbar .cftb-dd>summary{list-style:none;cursor:pointer;padding:4px 9px;border:1px solid #dde;border-radius:6px;font-size:12px;font-weight:700;color:#1c1f26}
.cftopbar .cftb-dd>summary::-webkit-details-marker{display:none}
.cftopbar .cftb-dd .cftb-ddmenu{position:absolute;right:0;top:100%;margin-top:6px;background:#fff;border:1px solid #dde;border-radius:8px;box-shadow:0 8px 24px rgba(20,24,30,.12);padding:4px;min-width:90px;z-index:60}
.cftopbar .cftb-dd .cftb-ddmenu a{display:block;padding:6px 10px;border-radius:6px;color:#1c1f26;text-decoration:none;font-size:13px}
.cftopbar .cftb-dd .cftb-ddmenu a:hover{background:#eef2fb}
.cftopbar .cftb-signout{color:#1c1f26;font-weight:600;font-size:13px;text-decoration:none;padding:4px 9px;border:1px solid #dde;border-radius:6px}
.cftopbar .cftb-signout:hover{background:#eef2fb}
.cftopbar .cftb-flag{width:16px;height:12px;vertical-align:middle;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.15);object-fit:cover}
.tb-resize{flex:0 0 6px;cursor:row-resize;background:transparent}
.cfshell{flex:1;display:flex;min-height:0}
.cfshell>.sidebar{width:220px;min-width:150px;max-width:400px;overflow:auto;background:#fff;flex:0 0 auto}
.cfshell>.sb-resize{flex:0 0 6px;cursor:col-resize;background:transparent}
.cfshell>.appmain{flex:1;min-width:0;padding:16px;overflow:auto}
.cfshell .sidebar nav.cfnav,.cfshell .sidebar nav.menu{display:flex;flex-direction:column;flex-wrap:nowrap;align-items:stretch;gap:2px;padding:12px 10px;border:0;box-shadow:none;background:transparent}
.cfshell .sidebar nav.cfnav{padding-bottom:0;font-size:13px;font-weight:600}
.cfshell .sidebar nav.cfnav+nav.menu{border-top:1px solid #dde;margin-top:6px}
.cfshell .sidebar nav.cfnav a,.cfshell .sidebar nav.menu a{padding:6px 8px;border-radius:6px;border-bottom:0;color:inherit;text-decoration:none}
.cfshell .sidebar nav.cfnav a:hover,.cfshell .sidebar nav.cfnav a[aria-current=page],.cfshell .sidebar nav.menu a:hover,.cfshell .sidebar nav.menu a[aria-current=page]{background:#eef2fb;border-bottom-color:transparent}
.cfshell .sidebar nav.menu .brand,.cfshell .sidebar nav.menu .sp,.cfshell .sidebar nav.menu .who,.cfshell .sidebar nav.menu a.lang,.cfshell .sidebar nav.menu>a:last-child{display:none}
@media (prefers-color-scheme:dark){
  .cftopbar{background:#1e2128}
  .cftopbar .cftb-who{color:#9aa2b1}
  .cftopbar .cftb-dd>summary,.cftopbar .cftb-signout{border-color:#343944;color:#e6e8ec}
  .cftopbar .cftb-dd .cftb-ddmenu{background:#1e2128;border-color:#343944}
  .cftopbar .cftb-dd .cftb-ddmenu a{color:#e6e8ec}
  .cftopbar .cftb-dd .cftb-ddmenu a:hover,.cftopbar .cftb-signout:hover{background:#28303e}
  .cfshell>.sidebar{background:#1e2128}
  .cfshell .sidebar nav.cfnav+nav.menu{border-color:#343944}
  .cfshell .sidebar nav.cfnav a:hover,.cfshell .sidebar nav.cfnav a[aria-current=page],.cfshell .sidebar nav.menu a:hover,.cfshell .sidebar nav.menu a[aria-current=page]{background:#28303e}
}
@media (max-width:760px){
  .cfshell{flex-direction:column}
  .cfshell>.sidebar{width:100%;max-width:none}
  .cfshell .sidebar nav.cfnav,.cfshell .sidebar nav.menu{flex-direction:row;flex-wrap:wrap}
  .cfshell>.sb-resize,.tb-resize{display:none}
}
<?= \AdGo\Sync\Libraries\Branding::css() ?>

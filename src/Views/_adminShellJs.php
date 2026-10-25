<?php
/**
 * Drags `#cfSidebar`'s width via the invisible `#cfSbResize` handle and `#cfTopbar`'s height via `#cfTbResize`
 * (see `_adminChrome.php`) and remembers both per browser, shared by every admin page (conflicts, status,
 * settings, users). No jQuery: the package does not vendor it.
 */
?>
<script>
(function () {
  'use strict';
  var WKEY = 'sync-conflicts-sidebar-width', HKEY = 'sync-conflicts-topbar-height';
  var sidebar = document.getElementById('cfSidebar');
  var topbar  = document.getElementById('cfTopbar');

  try {
    var w = localStorage.getItem(WKEY);
    if (w && sidebar) { sidebar.style.width = w + 'px'; }
    var h = localStorage.getItem(HKEY);
    if (h && topbar) { topbar.style.minHeight = h + 'px'; }
  } catch (e) {}

  function dragResize(handle, onMove) {
    if (!handle) { return; }
    handle.addEventListener('mousedown', function (e) {
      e.preventDefault();
      var prevSelect = document.body.style.userSelect;
      document.body.style.userSelect = 'none';
      function move(ev) { onMove(ev); }
      function up() {
        document.removeEventListener('mousemove', move);
        document.removeEventListener('mouseup', up);
        document.body.style.userSelect = prevSelect;
      }
      document.addEventListener('mousemove', move);
      document.addEventListener('mouseup', up);
    });
  }

  dragResize(document.getElementById('cfSbResize'), function (e) {
    if (!sidebar) { return; }
    var w = Math.max(150, Math.min(400, e.clientX - sidebar.getBoundingClientRect().left));
    sidebar.style.width = w + 'px';
    try { localStorage.setItem(WKEY, String(Math.round(w))); } catch (err) {}
  });

  dragResize(document.getElementById('cfTbResize'), function (e) {
    if (!topbar) { return; }
    var h = Math.max(36, Math.min(120, e.clientY - topbar.getBoundingClientRect().top));
    topbar.style.minHeight = h + 'px';
    try { localStorage.setItem(HKEY, String(Math.round(h))); } catch (err) {}
  });
})();
</script>

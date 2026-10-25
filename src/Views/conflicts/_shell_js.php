<?php
/**
 * Drags `#cfSidebar`'s width via the invisible `#cfSbResize` handle (see the `.cfshell` CSS in index.php /
 * show.php) and remembers it per browser. No jQuery: the conflicts admin page does not vendor it.
 */
?>
<script>
(function () {
  'use strict';
  var WKEY = 'sync-conflicts-sidebar-width';
  var sidebar = document.getElementById('cfSidebar');
  var handle = document.getElementById('cfSbResize');

  try {
    var w = localStorage.getItem(WKEY);
    if (w && sidebar) { sidebar.style.width = w + 'px'; }
  } catch (e) {}

  if (handle && sidebar) {
    handle.addEventListener('mousedown', function (e) {
      e.preventDefault();
      var prevSelect = document.body.style.userSelect;
      document.body.style.userSelect = 'none';
      function move(ev) {
        var w = Math.max(150, Math.min(400, ev.clientX - sidebar.getBoundingClientRect().left));
        sidebar.style.width = w + 'px';
        try { localStorage.setItem(WKEY, String(Math.round(w))); } catch (err) {}
      }
      function up() {
        document.removeEventListener('mousemove', move);
        document.removeEventListener('mouseup', up);
        document.body.style.userSelect = prevSelect;
      }
      document.addEventListener('mousemove', move);
      document.addEventListener('mouseup', up);
    });
  }
})();
</script>

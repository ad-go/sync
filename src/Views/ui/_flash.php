<?php
/* Flash messages from the last redirect are shown as a toast (see syncToast in _theme), not as a banner. */
$flashMessage = session()->getFlashdata('message');
$flashError   = session()->getFlashdata('error');

if ($flashMessage || $flashError): ?>
<script>
window.addEventListener('DOMContentLoaded', function () {
  if (window.syncToast) { syncToast(<?= json_encode((string) ($flashError ?: $flashMessage)) ?>, <?= $flashError ? 'false' : 'true' ?>); }
});
</script>
<?php endif ?>

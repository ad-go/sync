<?php
/** @var \CodeIgniter\Shield\Entities\User $u */ /** @var bool $readonly the other node is editing users right now */
/** @var string $base */
$groups    = $u->getGroups() ?? [];
$deletable = ! $readonly && (int) $u->id !== (int) auth()->id();
?>
<tr class="<?= $u->active ? '' : 'off' ?> rowlink" data-id="<?= (int) $u->id ?>" <?= $readonly ? '' : 'data-edit-url="' . esc(site_url($base . '/' . (int) $u->id . '/edit')) . '"' ?>>
  <td><?= (int) $u->id ?></td>
  <td><?= esc($u->username) ?></td>
  <td><?= esc((string) $u->email) ?></td>
  <td><?= esc(implode(', ', $groups)) ?></td>
  <td><?= $u->active ? 'yes' : 'no' ?></td>
  <td><?php if ($readonly): ?><span style="color:var(--mute);font-size:12px">read-only</span><?php endif ?>
    <?php if (in_array('superadmin', $groups, true)): ?>
      <button type="button" class="s" data-view2fa="<?= (int) $u->id ?>" title="View 2FA / QR code"><svg width="15" height="15" viewBox="0 0 15 15" fill="currentColor" aria-hidden="true" style="vertical-align:middle"><rect x="0" y="0" width="6" height="6"/><rect x="1" y="1" width="4" height="4" fill="var(--bg,#fff)"/><rect x="2" y="2" width="2" height="2"/><rect x="9" y="0" width="6" height="6"/><rect x="10" y="1" width="4" height="4" fill="var(--bg,#fff)"/><rect x="11" y="2" width="2" height="2"/><rect x="0" y="9" width="6" height="6"/><rect x="1" y="10" width="4" height="4" fill="var(--bg,#fff)"/><rect x="2" y="11" width="2" height="2"/><rect x="9" y="9" width="2" height="2"/><rect x="13" y="9" width="2" height="2"/><rect x="9" y="11" width="2" height="2"/><rect x="11" y="11" width="4" height="2"/><rect x="11" y="13" width="2" height="2"/><rect x="13" y="13" width="2" height="2"/></svg></button>
    <?php endif ?>
    <?php if ($deletable): ?>
      <form method="post" action="<?= site_url($base . '/' . (int) $u->id . '/delete') ?>"><?= csrf_field('del' . (int) $u->id) ?><button class="d" onclick="return confirm('Delete user <?= esc($u->username, 'js') ?> for good? This cannot be undone.')">Delete</button></form>
    <?php endif ?>
  </td>
</tr>

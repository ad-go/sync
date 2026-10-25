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
  <td><?php if (! $readonly): ?><a href="<?= site_url($base . '/' . (int) $u->id . '/edit') ?>">edit</a><?php else: ?>read-only<?php endif ?>
    <?php if (in_array('superadmin', $groups, true)): ?>
      <button type="button" class="s" data-view2fa="<?= (int) $u->id ?>">View 2FA</button>
    <?php endif ?>
    <?php if ($deletable): ?>
      <form method="post" action="<?= site_url($base . '/' . (int) $u->id . '/delete') ?>"><?= csrf_field('del' . (int) $u->id) ?><button class="d" onclick="return confirm('Delete user <?= esc($u->username, 'js') ?> for good? This cannot be undone.')">Delete</button></form>
    <?php endif ?>
  </td>
</tr>

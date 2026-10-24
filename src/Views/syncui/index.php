<?php
/** @var list<array{name: string, rows: int}> $sidebarTables */
$this->extend('AdGo\Sync\Views\syncui\_layout');
?>
<?= $this->section('content') ?>
<h1><?= lang('Sync.syncUi.title') ?></h1>
<table>
<tr><th><?= lang('Sync.syncUi.th.table') ?></th><th><?= lang('Sync.syncUi.th.rows') ?></th><th></th></tr>
<?php foreach ($sidebarTables as $t): ?>
<tr>
  <td><?= esc($t['name']) ?></td>
  <td><?= (int) $t['rows'] ?></td>
  <td><a href="<?= site_url($base . '/' . $t['name']) ?>"><?= lang('Sync.syncUi.browse') ?></a></td>
</tr>
<?php endforeach ?>
<?php if ($sidebarTables === []): ?><tr><td colspan="3"><?= lang('Sync.syncUi.noTables') ?></td></tr><?php endif ?>
</table>
<?= $this->endSection() ?>

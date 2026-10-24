<?php
/** @var list<array{name: string, rows: int}> $sidebarTables */
use AdGo\Sync\Libraries\Icon;
$this->extend('AdGo\Sync\Views\syncui\_layout');
?>
<?= $this->section('content') ?>
<h1><?= lang('Sync.syncUi.title') ?></h1>
<table id="tablesList">
<thead><tr><th><?= lang('Sync.syncUi.th.table') ?></th><th><?= lang('Sync.syncUi.th.rows') ?></th><th></th></tr></thead>
<tbody>
<?php foreach ($sidebarTables as $t): ?>
<tr>
  <td><?= esc($t['name']) ?></td>
  <td><?= (int) $t['rows'] ?></td>
  <td><a class="btn ghost" href="<?= site_url($base . '/' . $t['name']) ?>"><?= Icon::svg('search') ?> <?= lang('Sync.syncUi.browse') ?></a></td>
</tr>
<?php endforeach ?>
<?php if ($sidebarTables === []): ?><tr><td colspan="3"><?= lang('Sync.syncUi.noTables') ?></td></tr><?php endif ?>
</tbody>
</table>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
jQuery(function ($) {
  $('#tablesList').DataTable({ paging: false, info: false, ordering: true, language: { search: '' }, scrollX: true });
});
</script>
<?= $this->endSection() ?>

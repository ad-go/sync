<?php
/** @var string $sql */
/** @var ?array{columns: list<string>, rows: list<array<string, mixed>>, affected: int} $result */
/** @var ?string $error */
/** @var string $action */
use AdGo\Sync\Libraries\Icon;
$this->extend('AdGo\Sync\Views\syncui\_layout');
?>
<?= $this->section('content') ?>
<h1><?= lang('Sync.syncUi.sqlTitle') ?></h1>
<form method="post" action="<?= esc($action) ?>">
<?= csrf_field() ?>
<textarea name="sql" rows="6" placeholder="SELECT * FROM ..."><?= esc($sql) ?></textarea>
<p><button type="submit"><?= Icon::svg('play') ?> <?= lang('Sync.syncUi.run') ?></button></p>
</form>
<?php if ($result !== null): ?>
<?php if ($result['columns'] === []): ?>
<p><?= lang('Sync.syncUi.affected', [(string) $result['affected']]) ?></p>
<?php else: ?>
<table id="sqlResultTable">
<thead><tr><?php foreach ($result['columns'] as $c): ?><th><?= esc($c) ?></th><?php endforeach ?></tr></thead>
<tbody>
<?php foreach ($result['rows'] as $row): ?>
<tr><?php foreach ($result['columns'] as $c): ?><td><?= $row[$c] === null ? '<i>NULL</i>' : esc((string) $row[$c]) ?></td><?php endforeach ?></tr>
<?php endforeach ?>
<?php if ($result['rows'] === []): ?><tr><td colspan="<?= count($result['columns']) ?>"><?= lang('Sync.syncUi.noRows') ?></td></tr><?php endif ?>
</tbody>
</table>
<?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
jQuery(function ($) {
  if ($('#sqlResultTable').length) {
    $('#sqlResultTable').DataTable({ ordering: true, language: { search: '' }, scrollX: true });
  }
  <?php if ($error !== null): ?>
  Toastify({ text: <?= json_encode($error) ?>, duration: 8000, gravity: 'top', position: 'right', style: { background: '#b42318' } }).showToast();
  <?php endif ?>
});
</script>
<?= $this->endSection() ?>

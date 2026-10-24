<?php
/** @var string $sql */
/** @var ?array{columns: list<string>, rows: list<array<string, mixed>>, affected: int} $result */
/** @var ?string $error */
/** @var string $action */
$this->extend('AdGo\Sync\Views\syncui\_layout');
?>
<?= $this->section('content') ?>
<h1><?= lang('Sync.syncUi.sqlTitle') ?></h1>
<?php if ($error !== null): ?><div class="flash err"><?= esc($error) ?></div><?php endif ?>
<form method="post" action="<?= esc($action) ?>">
<?= csrf_field() ?>
<textarea name="sql" rows="6" placeholder="SELECT * FROM ..."><?= esc($sql) ?></textarea>
<p><button type="submit"><?= lang('Sync.syncUi.run') ?></button></p>
</form>
<?php if ($result !== null): ?>
<?php if ($result['columns'] === []): ?>
<p><?= lang('Sync.syncUi.affected', [(string) $result['affected']]) ?></p>
<?php else: ?>
<table>
<tr><?php foreach ($result['columns'] as $c): ?><th><?= esc($c) ?></th><?php endforeach ?></tr>
<?php foreach ($result['rows'] as $row): ?>
<tr><?php foreach ($result['columns'] as $c): ?><td><?= $row[$c] === null ? '<i>NULL</i>' : esc((string) $row[$c]) ?></td><?php endforeach ?></tr>
<?php endforeach ?>
<?php if ($result['rows'] === []): ?><tr><td colspan="<?= count($result['columns']) ?>"><?= lang('Sync.syncUi.noRows') ?></td></tr><?php endif ?>
</table>
<?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>

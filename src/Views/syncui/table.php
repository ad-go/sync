<?php
/** @var string $table */
/** @var list<object{name: string}> $columns */
/** @var list<array<string, mixed>> $rows */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $sort */
/** @var string $dir */
/** @var array<string, string> $filters */
/** @var list<string> $pk */
/** @var bool $editing */
/** @var array<string, mixed> $editPk */
/** @var ?array<string, mixed> $editRow */
/** @var string $base */
/** @var string $action */
/** @var string $delAction */
/** @var string $self */
use AdGo\Sync\Libraries\Icon;
$this->extend('AdGo\Sync\Views\syncui\_layout');

$names = array_map(static fn (object $c): string => $c->name, $columns);

$qs = static function (array $over) use ($page, $sort, $dir, $filters): string {
    $q = array_merge(['page' => $page, 'sort' => $sort, 'dir' => $dir, 'f' => $filters], $over);

    return '?' . http_build_query(array_filter($q, static fn ($v) => $v !== '' && $v !== [] && $v !== null));
};

// http_build_query() (used by $qs below) url-encodes this value itself; no extra encoding here.
$encodeKey = static fn (array $row): string => json_encode(array_intersect_key($row, array_flip($pk)), JSON_UNESCAPED_SLASHES);

$pages = (int) max(1, ceil($total / max(1, $perPage)));
?>
<?= $this->section('content') ?>
<h1><?= esc($table) ?> <small>(<?= lang('Sync.syncUi.rowsOf', [(string) $total]) ?>)</small></h1>

<?php if ($editing): ?>
<div class="card">
<h2><?= $editPk === [] ? lang('Sync.syncUi.newRow') : lang('Sync.syncUi.editRow') ?></h2>
<form method="post" action="<?= esc($action) ?>">
<?= csrf_field() ?>
<?php foreach ($editPk as $col => $val): ?><input type="hidden" name="pk[<?= esc($col) ?>]" value="<?= esc((string) $val) ?>"><?php endforeach ?>
<table>
<?php foreach ($names as $n): ?>
<tr>
  <th><?= esc($n) ?><?= in_array($n, $pk, true) ? ' <small>(' . lang('Sync.syncUi.key') . ')</small>' : '' ?></th>
  <td>
  <?php if (in_array($n, $pk, true) && $editPk !== []): ?>
    <?= esc((string) ($editRow[$n] ?? '')) ?>
  <?php else: ?>
    <input type="text" name="v[<?= esc($n) ?>]" value="<?= esc((string) ($editRow[$n] ?? '')) ?>">
  <?php endif ?>
  </td>
</tr>
<?php endforeach ?>
</table>
<p><button type="submit"><?= Icon::svg('check') ?> <?= $editPk === [] ? lang('Sync.syncUi.insert') : lang('Sync.syncUi.save') ?></button>
<a class="btn ghost" href="<?= esc($self) ?>"><?= Icon::svg('x') ?> <?= lang('Sync.syncUi.cancel') ?></a></p>
</form>
</div>
<?php endif ?>

<form method="get" action="<?= esc($self) ?>">
<table id="rowsTable">
<thead>
<tr>
<?php foreach ($names as $n): $isSort = $sort === $n; $nextDir = $isSort && $dir === 'asc' ? 'desc' : 'asc'; ?>
  <th><a href="<?= esc($self . $qs(['sort' => $n, 'dir' => $nextDir, 'page' => 1])) ?>"><?= esc($n) ?><?= $isSort ? ($dir === 'asc' ? ' ▲' : ' ▼') : '' ?></a></th>
<?php endforeach ?>
  <th></th>
</tr>
<tr>
<?php foreach ($names as $n): ?>
  <td><input type="text" name="f[<?= esc($n) ?>]" value="<?= esc($filters[$n] ?? '') ?>" placeholder="<?= esc(lang('Sync.syncUi.filter')) ?>"></td>
<?php endforeach ?>
  <td><button type="submit"><?= Icon::svg('search') ?> <?= lang('Sync.syncUi.filter') ?></button></td>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
<?php foreach ($names as $n): ?>
  <td><?= $row[$n] === null ? '<i>NULL</i>' : esc((string) $row[$n]) ?></td>
<?php endforeach ?>
  <td style="white-space:nowrap">
    <a class="btn ghost" href="<?= esc($self . $qs(['edit' => $encodeKey($row)])) ?>"><?= Icon::svg('pencil') ?></a>
    <form method="post" action="<?= esc($delAction) ?>" style="display:inline" onsubmit="return confirm('<?= esc(lang('Sync.syncUi.confirmDelete')) ?>')">
    <?= csrf_field() ?>
    <?php foreach (array_intersect_key($row, array_flip($pk)) as $col => $val): ?><input type="hidden" name="pk[<?= esc($col) ?>]" value="<?= esc((string) $val) ?>"><?php endforeach ?>
    <button type="submit" class="d"><?= Icon::svg('trash-2') ?></button>
    </form>
  </td>
</tr>
<?php endforeach ?>
<?php if ($rows === []): ?><tr><td colspan="<?= count($names) + 1 ?>"><?= lang('Sync.syncUi.noRows') ?></td></tr><?php endif ?>
</tbody>
</table>
</form>

<p><a class="btn" href="<?= esc($self . $qs(['edit' => 'new'])) ?>"><?= Icon::svg('plus') ?> <?= lang('Sync.syncUi.newRow') ?></a></p>

<p class="pager">
<?php if ($page > 1): ?><a href="<?= esc($self . $qs(['page' => $page - 1])) ?>">&larr; <?= lang('Sync.syncUi.prev') ?></a><?php endif ?>
<?= lang('Sync.syncUi.pageOf', [(string) $page, (string) $pages]) ?>
<?php if ($page < $pages): ?><a href="<?= esc($self . $qs(['page' => $page + 1])) ?>"><?= lang('Sync.syncUi.next') ?> &rarr;</a><?php endif ?>
</p>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
jQuery(function ($) {
  $('#rowsTable').DataTable({ paging: false, info: false, ordering: false, language: { search: '' }, scrollX: true });
});
</script>
<?= $this->endSection() ?>

<?php
/** @var array $stats */ /** @var array $cursors */ /** @var array $recent */
/** @var \AdGo\Sync\Config\Sync $cfg */ /** @var string $menu */ /** @var string $head */ /** @var string $base */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= lang('Sync.dashboard.title') ?> · <?= esc($cfg->node) ?></title>
<?= view('AdGo\Sync\Views\ui\_theme') ?>
<?= $head ?? '' ?>
</head>
<body>
<?= view('AdGo\Sync\Views\ui\_topbar') ?>
<div class="appshell">
<aside id="appSidebar"><?= view('AdGo\Sync\Views\ui\_nav', ['active' => 'dashboard']) ?><?= $menu ?? '' ?></aside>
<div class="sb-resize" id="sbResize"></div>
<main class="appmain" id="appMain">
<?= view('AdGo\Sync\Views\ui\_flash') ?>

<div style="display:flex;align-items:baseline;gap:12px;margin-bottom:18px">
  <h1 style="margin:0;font-size:1.25rem;font-weight:700"><?= lang('Sync.dashboard.title') ?></h1>
  <span style="font-size:.8rem;color:var(--mute)"><?= esc($cfg->node) ?></span>
</div>

<!-- Stat cards -->
<div class="cf-stats" style="margin-bottom:24px">
  <div class="cf-stat">
    <div class="cf-stat-val"><?= $stats['total'] ?></div>
    <div class="cf-stat-lbl"><?= lang('Sync.dashboard.stat_changes') ?></div>
  </div>
  <div class="cf-stat">
    <div class="cf-stat-val" style="color:var(--acc)"><?= $stats['pending_local'] ?></div>
    <div class="cf-stat-lbl"><?= lang('Sync.dashboard.stat_pending') ?></div>
  </div>
  <div class="cf-stat">
    <div class="cf-stat-val"><?= $stats['applied_remote'] ?></div>
    <div class="cf-stat-lbl"><?= lang('Sync.dashboard.stat_applied') ?></div>
  </div>
  <a class="cf-stat" href="<?= site_url(trim($cfg->conflictsPath, '/')) ?>" style="text-decoration:none;<?= $stats['conflicts_pend'] > 0 ? 'border-left:3px solid var(--err-fg)' : '' ?>">
    <div class="cf-stat-val" style="<?= $stats['conflicts_pend'] > 0 ? 'color:var(--err-fg)' : '' ?>"><?= $stats['conflicts_pend'] ?><?php if ($stats['conflicts_all'] > $stats['conflicts_pend']): ?><span style="font-size:.75rem;font-weight:400;color:var(--mute);margin-left:4px">/ <?= $stats['conflicts_all'] ?></span><?php endif ?></div>
    <div class="cf-stat-lbl"><?= lang('Sync.dashboard.stat_conflicts') ?></div>
  </a>
  <div class="cf-stat">
    <div class="cf-stat-val"><?= $stats['files'] ?></div>
    <div class="cf-stat-lbl"><?= lang('Sync.dashboard.stat_files') ?></div>
  </div>
  <div class="cf-stat">
    <div class="cf-stat-val"><?= $stats['size_gb'] ?> <span style="font-size:.85rem;font-weight:500">GB</span></div>
    <div class="cf-stat-lbl"><?= lang('Sync.dashboard.stat_size') ?></div>
  </div>
</div>

<!-- Peer cursors -->
<?php if ($cursors !== []): ?>
<h2 style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--mute);margin-bottom:10px"><?= lang('Sync.dashboard.peers') ?></h2>
<table style="margin-bottom:24px">
  <thead><tr>
    <th><?= lang('Sync.dashboard.col_peer') ?></th>
    <th><?= lang('Sync.dashboard.col_last_seq') ?></th>
    <th><?= lang('Sync.dashboard.col_clock_offset') ?></th>
    <th><?= lang('Sync.dashboard.col_clock_at') ?></th>
  </tr></thead>
  <tbody>
  <?php foreach ($cursors as $c): ?>
    <tr>
      <td><?= esc($c['peer_node']) ?></td>
      <td><?= (int) $c['last_seq'] ?></td>
      <td><?php
        $us = (int) ($c['clock_offset_us'] ?? 0);
        echo $us === 0 ? '—' : sprintf('%+.3f s', $us / 1_000_000);
      ?></td>
      <td style="color:var(--mute)"><?php
        $at = (int) ($c['clock_at'] ?? 0);
        echo $at > 0 ? date('d.m H:i', intdiv($at, 1_000_000)) : '—';
      ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
<?php endif ?>

<!-- Recent changes -->
<h2 style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--mute);margin-bottom:10px"><?= lang('Sync.dashboard.recent') ?></h2>
<table>
  <thead><tr>
    <th>#</th>
    <th><?= lang('Sync.dashboard.col_table') ?></th>
    <th><?= lang('Sync.dashboard.col_op') ?></th>
    <th><?= lang('Sync.dashboard.col_uuid') ?></th>
    <th><?= lang('Sync.dashboard.col_origin') ?></th>
    <th><?= lang('Sync.dashboard.col_seen') ?></th>
    <th><?= lang('Sync.dashboard.col_changed_at') ?></th>
  </tr></thead>
  <tbody>
  <?php foreach ($recent as $r): ?>
    <tr>
      <td style="color:var(--mute);font-variant-numeric:tabular-nums"><?= (int) $r['seq'] ?></td>
      <td><code style="font-size:.82rem"><?= esc($r['table_name']) ?></code></td>
      <td><span class="badge badge-<?= $r['op'] === 'I' ? 'active' : ($r['op'] === 'D' ? 'deleted' : 'expired') ?>" style="font-family:monospace"><?= esc($r['op']) ?></span></td>
      <td style="color:var(--mute);font-size:.8rem;font-family:monospace"><?= esc(substr($r['uuid'], 0, 12)) ?>…</td>
      <td><?= esc($r['node_origin'] ?? $r['origin'] ?? '—') ?></td>
      <td><?= (int) $r['seen'] > 0 ? '<span style="color:var(--ok-fg)">✓</span>' : '<span style="color:var(--mute)">—</span>' ?></td>
      <td style="color:var(--mute);white-space:nowrap;font-size:.82rem"><?php
        $ts = (int) $r['changed_at'];
        echo $ts > 1_000_000_000_000 ? date('d.m H:i:s', intdiv($ts, 1_000_000)) : date('d.m H:i:s', $ts);
      ?></td>
    </tr>
  <?php endforeach ?>
  <?php if (empty($recent)): ?>
    <tr><td colspan="7" style="text-align:center;color:var(--mute);padding:24px"><?= lang('Sync.dashboard.no_changes') ?></td></tr>
  <?php endif ?>
  </tbody>
</table>

</main>
</div>
</body>
</html>

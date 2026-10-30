<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use AdGo\Sync\Libraries\Kernel;
use CodeIgniter\Controller;

class DashboardAdmin extends Controller
{
    use AdminChrome;

    protected string $configKey = 'dashboardPath';

    public function index(): string
    {
        $cfg = config('Sync');
        $db  = Kernel::instance()->db;

        $totalChanges  = (int) $db->table('sync_changes')->countAll();
        $pendingLocal  = (int) $db->table('sync_changes')->where('seen', 0)->countAll();
        $appliedRemote = (int) $db->table('sync_changes')->where('seen >', 0)->countAll();
        $conflictsPend = (int) $db->table('sync_conflicts')->where('status', 'pending')->countAll();
        $conflictsAll  = (int) $db->table('sync_conflicts')->countAll();
        $filesCount    = (int) $db->table('sync_files')->countAll();
        $sizeRow       = $db->table('sync_files')->selectSum('size')->get()->getRowArray();
        $sizeGb        = round(($sizeRow['size'] ?? 0) / 1073741824, 3);

        $cursors = $db->table('sync_cursor')->get()->getResultArray();

        $recent = $db->table('sync_changes')
            ->orderBy('seq', 'DESC')
            ->limit(20)
            ->get()->getResultArray();

        $stats = [
            'total'          => $totalChanges,
            'pending_local'  => $pendingLocal,
            'applied_remote' => $appliedRemote,
            'conflicts_pend' => $conflictsPend,
            'conflicts_all'  => $conflictsAll,
            'files'          => $filesCount,
            'size_gb'        => $sizeGb,
        ];

        return view('AdGo\Sync\Views\dashboard\index', [
            'stats'   => $stats,
            'cursors' => $cursors,
            'recent'  => $recent,
            'cfg'     => $cfg,
            'menu'    => $this->menu('dashboard'),
            'head'    => $this->head(),
            'base'    => $this->base(),
        ]);
    }
}

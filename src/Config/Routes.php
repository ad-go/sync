<?php

declare(strict_types=1);

/**
 * Auto-discovered by CodeIgniter ("route discovery"). $routes is provided by the framework.
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

use AdGo\Sync\Config\Sync;

/** @var Sync $syncCfg */
$syncCfg = config('Sync');
$prefix  = trim($syncCfg->routePrefix, '/');
$ns      = 'AdGo\Sync\Controllers';

// Peer-to-peer API: HMAC-signed POSTs only.
$routes->group($prefix, ['namespace' => $ns, 'filter' => 'syncHmac'], static function ($routes): void {
    $routes->post('ping', 'SyncController::ping');
    $routes->post('probe', 'SyncController::probe');
    $routes->post('pull', 'SyncController::pull');
    $routes->post('push', 'SyncController::push');
    $routes->post('conflict/resolve', 'SyncController::resolve');
    $routes->post('auth-lock/status', 'SyncController::lockStatus');
    $routes->post('auth-lock/acquire', 'SyncController::lockAcquire');
    $routes->post('auth-lock/heartbeat', 'SyncController::lockHeartbeat');
    $routes->post('auth-lock/release', 'SyncController::lockRelease');
    $routes->post('auth-lock/sync', 'SyncController::lockSync');
    $routes->post('files/manifest', 'SyncController::filesManifest');
    $routes->post('files/get', 'SyncController::filesGet');
    $routes->post('files/put', 'SyncController::filesPut');
    $routes->post('bootstrap/page', 'SyncController::bootstrapPage');
    $routes->post('telegram/decide', 'SyncController::telegramDecide');
});

// Telegram webhook (authenticated by the webhook secret token + chat id, not by HMAC).
$routes->post($prefix . '/telegram', 'SyncController::telegram', ['namespace' => $ns]);

// Conflict admin UI (Shield session + permission).
$routes->group(trim($syncCfg->conflictsPath, '/'), [
    'namespace' => $ns,
    'filter'    => ['session', 'permission:' . $syncCfg->adminPermission],
], static function ($routes): void {
    $routes->get('/', 'ConflictAdmin::index');
    $routes->get('(:num)', 'ConflictAdmin::show/$1');
    $routes->post('(:num)/resolve', 'ConflictAdmin::resolve/$1');
});

// SyncUI: Adminer-style database browser/editor (Shield session + permission, same guard as the conflict admin).
$routes->group(trim($syncCfg->syncUiPath, '/'), [
    'namespace' => $ns,
    'filter'    => ['session', 'permission:' . $syncCfg->adminPermission],
], static function ($routes): void {
    $routes->get('/', 'SyncUiAdmin::index');
    $routes->get('sql', 'SyncUiAdmin::sql');
    $routes->post('sql', 'SyncUiAdmin::sql');
    $routes->get('locale/(:segment)', 'SyncUiAdmin::locale/$1');
    $routes->get('(:segment)', 'SyncUiAdmin::table/$1');
    $routes->post('(:segment)/save', 'SyncUiAdmin::save/$1');
    $routes->post('(:segment)/delete', 'SyncUiAdmin::delete/$1');
});

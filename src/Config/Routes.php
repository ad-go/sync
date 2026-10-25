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

// Status admin page (Shield session + permission).
$routes->group(trim($syncCfg->statusPath, '/'), [
    'namespace' => $ns,
    'filter'    => ['session', 'permission:' . $syncCfg->adminPermission],
], static function ($routes): void {
    $routes->get('/', 'StatusAdmin::index');
    $routes->post('sync-now', 'StatusAdmin::syncNow');
    $routes->post('lock', 'StatusAdmin::lock');
});

// Settings admin page (Shield session + the tighter superPermission).
$routes->group(trim($syncCfg->settingsPath, '/'), [
    'namespace' => $ns,
    'filter'    => ['session', 'permission:' . $syncCfg->superPermission],
], static function ($routes): void {
    $routes->get('/', 'SettingsAdmin::index');
    $routes->post('branding', 'SettingsAdmin::saveBranding');
    $routes->post('logo/remove', 'SettingsAdmin::removeLogo');
    $routes->post('identity', 'SettingsAdmin::identity');
    $routes->post('env', 'SettingsAdmin::saveEnv');
});

// Users admin pages (Shield session + the tighter superPermission).
$routes->group(trim($syncCfg->usersPath, '/'), [
    'namespace' => $ns,
    'filter'    => ['session', 'permission:' . $syncCfg->superPermission],
], static function ($routes): void {
    $routes->get('/', 'UsersAdmin::index');
    $routes->post('/', 'UsersAdmin::create');
    $routes->get('(:num)/edit', 'UsersAdmin::edit/$1');
    $routes->post('(:num)', 'UsersAdmin::save/$1');
    $routes->post('(:num)/delete', 'UsersAdmin::delete/$1');
    $routes->get('(:num)/2fa', 'UsersAdmin::twofa/$1');
});

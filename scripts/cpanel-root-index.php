<?php

// tisilo-cpanel-root-front-controller-v1

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// A copied production bundle may not preserve symbolic links. Recreate the
// standard public storage link on the first request when the host permits it.
$publicStorage = __DIR__.'/public/storage';
$storedUploads = __DIR__.'/storage/app/public';

if (! file_exists($publicStorage) && is_dir($storedUploads)) {
    @symlink('../storage/app/public', $publicStorage);
}

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$app->handleRequest(Request::capture());

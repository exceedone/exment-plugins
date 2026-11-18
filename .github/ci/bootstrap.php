<?php
// PHPStan bootstrap for the plugins.
// The plugins run inside Exment, so load what the Exment app normally loads at boot:
// the helper functions (is_nullorempty, exmtrans ...) and the class aliases
// ("Exment", "DB", "Log" ...) that a Laravel application registers.
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/exceedone/exment/src/Services/Helpers.php';

$aliases = [
    'Exment' => Exceedone\Exment\Facades\ExmentFacade::class,
    'DB' => Illuminate\Support\Facades\DB::class,
    'Log' => Illuminate\Support\Facades\Log::class,
    'Schema' => Illuminate\Support\Facades\Schema::class,
    'Storage' => Illuminate\Support\Facades\Storage::class,
    'Cache' => Illuminate\Support\Facades\Cache::class,
    'Auth' => Illuminate\Support\Facades\Auth::class,
    'Request' => Illuminate\Support\Facades\Request::class,
    'Route' => Illuminate\Support\Facades\Route::class,
    'Validator' => Illuminate\Support\Facades\Validator::class,
];
foreach ($aliases as $alias => $class) {
    if (!class_exists($alias, false) && class_exists($class)) {
        class_alias($class, $alias);
    }
}

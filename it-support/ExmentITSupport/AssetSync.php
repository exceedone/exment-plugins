<?php

namespace App\Plugins\ExmentITSupport;

/**
 * The connectors of the data sync (Exment 7.3, ISS00113) that bring the hosts of monitoring
 * tools into the IT assets (ISS02928, phase 3): Mackerel and Zabbix. Registered only when Exment
 * has the data sync; nothing runs until a system administrator makes a connection and a mapping
 * on Exment's page "データ同期" (a feature that is off at first).
 */
class AssetSync
{
    /** @var bool */
    protected static $registered = false;

    public static function available(): bool
    {
        foreach (['SyncConnector', 'SyncWriter', 'SyncMappingFields', 'SyncFinisher'] as $name) {
            if (!interface_exists('\\Exceedone\\Exment\\Services\\Sync\\' . $name)) {
                return false;
            }
        }

        return class_exists('\\Exceedone\\Exment\\Services\\Sync\\SyncConnectors');
    }

    public static function register(): void
    {
        if (static::$registered || !static::available()) {
            return;
        }
        static::$registered = true;
        // they implement the interfaces of Exment 7.3: loaded only here
        require_once __DIR__ . '/Sync/AssetConnector.php';
        require_once __DIR__ . '/Sync/MackerelAssetConnector.php';
        require_once __DIR__ . '/Sync/ZabbixAssetConnector.php';
        foreach (['MackerelAssetConnector', 'ZabbixAssetConnector'] as $class) {
            // by name: on Exment 7.2 (and in its static analysis) these classes do not exist
            $connector = (new \ReflectionClass(__NAMESPACE__ . '\\Sync\\' . $class))->newInstance();
            call_user_func(['\\Exceedone\\Exment\\Services\\Sync\\SyncConnectors', 'register'], $connector);
        }
    }
}

<?php

namespace App\Plugins\ExmentKintone;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\SyncConnection;
use Exceedone\Exment\Services\Plugin\PluginPageBase;

/**
 * Exment Plugin kintone 連携 (ISS00113): the kintone connector of the data sync of Exment
 * (Exment 7.3.0 or later), and a page that makes an Exment table from the definition of a kintone
 * app. The connections, the mappings and the runs are on the admin page "Data sync" of Exment.
 *
 * Exment builds the class of a page plugin on every request (and the data sync asks for it in a
 * worker and in a command): this is where the connector is registered.
 */
class PluginPage extends PluginPageBase
{
    /**
     * @param \Exceedone\Exment\Model\Plugin $plugin
     */
    public function __construct($plugin)
    {
        parent::__construct($plugin);
        static::registerConnector();
    }

    public static function hasSync(): bool
    {
        return interface_exists('\Exceedone\Exment\Services\Sync\SyncConnector') && class_exists('\Exceedone\Exment\Services\Sync\SyncConnectors');
    }

    public static function registerConnector(): void
    {
        if (!static::hasSync()) {
            return;
        }
        // the class of the connector implements the interface of Exment 7.3: loaded only here
        require_once __DIR__ . '/KintoneConnector.php';
        \Exceedone\Exment\Services\Sync\SyncConnectors::register(new KintoneConnector());
    }

    public function index()
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $connections = [];
        $ready = static::hasSync() && \Exceedone\Exment\Services\Sync\SyncEngine::enabled();
        if ($ready) {
            $connections = SyncConnection::query()->where('connector', 'kintone')->orderBy('id')->get();
        }
        return $this->pluginView('index', [
            'hasSync' => static::hasSync(),
            'ready' => $ready,
            'connections' => $connections,
            'syncUrl' => admin_url('sync'),
            'makeUrl' => admin_url($this->plugin->getRouteUri() . '/table'),
            'ok' => session('kintone_ok'),
            'error' => session('kintone_error'),
        ]);
    }

    public function makeTable()
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $back = redirect(admin_url($this->plugin->getRouteUri()));
        if (!static::hasSync() || !\Exceedone\Exment\Services\Sync\SyncEngine::enabled()) {
            return $back->with('kintone_error', Lang::t('need_feature'));
        }
        /** @var SyncConnection|null $connection */
        $connection = SyncConnection::query()->where('connector', 'kintone')->find(intval(request()->get('connection')));
        $app = trim(strval(request()->get('app')));
        $tableName = trim(strval(request()->get('table_name')));
        $tableViewName = trim(strval(request()->get('table_view_name')));
        if (!isset($connection) || preg_match('/^\d+$/', $app) !== 1 || $tableViewName === '') {
            return $back->withInput()->with('kintone_error', Lang::t('make_help'));
        }
        if (!TableBuilder::validTableName($tableName)) {
            return $back->withInput()->with('kintone_error', Lang::t('bad_name'));
        }
        if (CustomTable::getEloquent($tableName) !== null) {
            return $back->withInput()->with('kintone_error', Lang::t('exists'));
        }
        try {
            $made = TableBuilder::build($connection, $app, $tableName, mb_substr($tableViewName, 0, 40));
        } catch (\Throwable $e) {
            $message = $e instanceof \RuntimeException ? $e->getMessage() : get_class($e);
            return $back->withInput()->with('kintone_error', $message);
        }
        $skipped = count($made['skipped']) > 0 ? Lang::t('skipped', ['fields' => implode('、', $made['skipped'])]) : '';
        admin_toastr(Lang::t('made', ['table' => $made['table']->table_view_name, 'skipped' => $skipped]));
        return redirect(admin_url('sync'));
    }

    /**
     * The system administrators only.
     *
     * @return mixed
     */
    protected function deny()
    {
        $user = \Exment::user();
        if (!isset($user) || !$user->isAdministrator()) {
            return response(Lang::t('admin_only'), 403);
        }
        return null;
    }
}

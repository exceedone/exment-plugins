<?php

namespace App\Plugins\ExmentPMBacklog;

use Exceedone\Exment\Services\Plugin\PluginPageBase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The settings screens: the list of connections, one connection's settings
 * and mapping, "sync now", and the recent runs. System administrators only:
 * a connection holds an API key and writes into a project.
 */
class PluginPage extends PluginPageBase
{
    /** A manual run stays short enough for a web request. */
    const MANUAL_LIMIT = 100;

    public function index()
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $rows = [];
        foreach (Connections::all() as $c) {
            $rows[] = [
                'conn' => $c,
                'project' => $this->projectLabel(intval($c->exment_project_id)),
                'issues' => Links::count(intval($c->id)),
            ];
        }

        return $this->pluginView('index', $this->base(['rows' => $rows]));
    }

    public function create()
    {
        if ($deny = $this->deny()) {
            return $deny;
        }

        return $this->pluginView('edit', $this->base([
            'conn' => null, 'mapping' => [], 'missing' => [], 'runs' => [], 'exUsers' => [],
            'projects' => $this->projects(), 'hasKey' => false,
        ]));
    }

    public function store()
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $in = request()->all();
        if ($err = $this->invalid($in, true)) {
            return redirect($this->url('new'))->withInput(request()->except('api_key'))->with('pmbl_error', $err);
        }
        $id = Connections::save(null, $in);

        return redirect($this->url($id))->with('pmbl_ok', Lang::t('saved'));
    }

    public function edit($id)
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $conn = Connections::find($id);
        if (!$conn) {
            return redirect($this->url());
        }
        $mapping = Connections::mapping($conn);
        $runs = DB::table('pmbl_runs')->where('connection_id', $conn->id)->orderByDesc('id')->limit(20)->get()->all();

        return $this->pluginView('edit', $this->base([
            'conn' => $conn,
            'mapping' => $mapping,
            'missing' => Mapper::missing($mapping),
            'runs' => $runs,
            'exUsers' => $this->exmentUsers(),
            'projects' => $this->projects(),
            'hasKey' => Connections::hasKey($conn),
            'issues' => Links::count(intval($conn->id)),
        ]));
    }

    public function update($id)
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $conn = Connections::find($id);
        if (!$conn) {
            return redirect($this->url());
        }
        $in = request()->all();
        if ($err = $this->invalid($in, !Connections::hasKey($conn))) {
            return redirect($this->url($id))->with('pmbl_error', $err);
        }
        Connections::save(intval($id), $in);

        return redirect($this->url($id))->with('pmbl_ok', Lang::t('saved'));
    }

    /** Read Backlog again; with create=1 also make what Exment lacks; with users[] save the user choices. */
    public function postMapping($id)
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $conn = Connections::find($id);
        if (!$conn) {
            return redirect($this->url());
        }
        $manual = [];
        foreach ((array) request()->get('users', []) as $bid => $uid) {
            $manual[strval($bid)] = is_scalar($uid) ? trim(strval($uid)) : '';
        }
        try {
            $m = Mapper::refresh($conn, request()->get('create') === '1', $manual);
            $msg = Lang::t('mapping_done') . (empty($m['created']) ? '' : ' ' . implode(' / ', $m['created']));

            return redirect($this->url($id))->with('pmbl_ok', $msg);
        } catch (\Throwable $e) {
            $error = Secrets::scrub($e->getMessage(), [Connections::apiKey($conn)]);
            \Log::warning('pm_backlog mapping ' . $id . ': ' . $error);

            return redirect($this->url($id))->with('pmbl_error', mb_substr($error, 0, 500));
        }
    }

    public function postRun($id)
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $conn = Connections::find($id);
        if (!$conn) {
            return redirect($this->url());
        }
        @set_time_limit(600);
        $r = Syncer::runConnection($conn, 'manual', static::MANUAL_LIMIT);
        if ($r['status'] === 'failed') {
            return redirect($this->url($id))->with('pmbl_error', Lang::t('run_failed', ['message' => $r['message']]));
        }
        if ($r['status'] === 'skipped') {
            return redirect($this->url($id))->with('pmbl_error', $r['message']);
        }
        $c = $r['counts'] + ['add' => 0, 'update' => 0, 'comment' => 0, 'error' => 0];

        return redirect($this->url($id))->with('pmbl_ok', Lang::t('run_done', $c));
    }

    /** Take over what backlog_sync imported (state.json, mapping.json). */
    public function postImport($id)
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $conn = Connections::find($id);
        if (!$conn) {
            return redirect($this->url());
        }
        $read = function (string $field) {
            $f = request()->file($field);
            if (!$f || !$f->isValid() || $f->getSize() > 50 * 1024 * 1024) {
                return null;
            }
            $data = json_decode(strval(file_get_contents($f->getRealPath())), true);

            return is_array($data) ? $data : null;
        };
        $state = $read('state');
        if (!$state || !isset($state['issues']) || !is_array($state['issues'])) {
            return redirect($this->url($id))->with('pmbl_error', Lang::t('import_bad'));
        }
        try {
            $n = Importer::run($conn, $state, $read('mapping'));

            return redirect($this->url($id))->with('pmbl_ok', Lang::t('import_done', $n));
        } catch (\Throwable $e) {
            $error = Secrets::scrub($e->getMessage(), [Connections::apiKey($conn)]);
            \Log::warning('pm_backlog import ' . $id . ': ' . $error);

            return redirect($this->url($id))->with('pmbl_error', mb_substr($error, 0, 500));
        }
    }

    public function postDelete($id)
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        Connections::delete(intval($id));

        return redirect($this->url())->with('pmbl_ok', Lang::t('deleted'));
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function base(array $data): array
    {
        return array_merge([
            'L' => Lang::JA,
            'url' => function (...$p) {
                return $this->url(...$p);
            },
            'ok' => session('pmbl_ok'),
            'error' => session('pmbl_error'),
            'manualLimit' => static::MANUAL_LIMIT,
        ], $data);
    }

    protected function url(...$path): string
    {
        return $this->plugin->getFullUrl(...array_map('strval', $path));
    }

    /** @return mixed|null a response when the user may not be here */
    protected function deny()
    {
        $user = \Exment::user();
        if (!$user || !$user->isAdministrator()) {
            return response(Lang::t('denied'), 403);
        }
        static::ensureTables();

        return null;
    }

    /**
     * The plugin's tables, if the install did not run its migration (an
     * Exment whose installer does not run plugin migrations, or a copy of
     * the files). The migration checks each table first.
     */
    public static function ensureTables(): void
    {
        if (Schema::hasTable('pmbl_runs') && Schema::hasColumn('pmbl_links', 'parent_backlog_id')) {
            return;
        }
        // Each migration looks before it creates, so running them all again is safe.
        $files = glob(__DIR__ . '/database/migrations/*.php') ?: [];
        sort($files);
        foreach ($files as $file) {
            $migration = require $file;
            $migration->up();
        }
    }

    /**
     * @param array<string, mixed> $in
     */
    protected function invalid(array $in, bool $needKey): ?string
    {
        foreach (['name', 'space_url', 'backlog_project_key', 'exment_project_id'] as $k) {
            if (trim(strval($in[$k] ?? '')) === '') {
                return Lang::t('need_fields');
            }
        }
        if (!preg_match('#^https://[^/\s]+#i', trim(strval($in['space_url'])))) {
            return Lang::t('bad_url');
        }
        if ($needKey && trim(strval($in['api_key'] ?? '')) === '') {
            return Lang::t('need_key');
        }

        return null;
    }

    /** @return array<int, string> id => label */
    protected function projects(): array
    {
        $out = [];
        $t = Pm::table('project_table');
        if (!$t) {
            return $out;
        }
        foreach ($t->getValueModel()->query()->orderBy('id')->get() as $p) {
            $out[intval($p->id)] = $this->label($p);
        }

        return $out;
    }

    protected function projectLabel(int $id): string
    {
        $p = Mapper::project($id);

        return $p ? $this->label($p) : ('#' . $id);
    }

    protected function label($p): string
    {
        $code = strval(array_get($p->value, 'project_code', ''));
        $name = strval(array_get($p->value, 'name', ''));

        return trim(($code !== '' ? '[' . $code . '] ' : '') . $name) ?: ('#' . $p->id);
    }

    /** @return array<int, string> id => name */
    protected function exmentUsers(): array
    {
        $out = [];
        $t = \Exceedone\Exment\Model\CustomTable::getEloquent(\Exceedone\Exment\Enums\SystemTableName::USER);
        foreach ($t->getValueModel()->query()->orderBy('id')->get() as $u) {
            $out[intval($u->id)] = strval(array_get($u->value, 'user_name')) . ' <' . strval(array_get($u->value, 'email')) . '>';
        }

        return $out;
    }
}

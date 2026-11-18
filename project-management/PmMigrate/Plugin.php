<?php

namespace App\Plugins\PmMigrate;

use Exceedone\Exment\Services\Plugin\PluginPageBase;
use Illuminate\Support\Facades\Log;

/**
 * Moves the portal's projects from one Exment to another.
 *
 * One screen, install it on both ends: on the source it exports the projects
 * that were ticked into a ZIP, on the target it takes that ZIP, shows what an
 * import would do (a dry run), and writes it when asked. The work is in
 * Exporter and Importer; this class only checks who is asking and shapes the
 * answers.
 */
class Plugin extends PluginPageBase
{
    public function index()
    {
        $this->assertAdmin();

        return $this->screen();
    }

    /**
     * Build the bundle of the ticked projects and send it as a download.
     *
     * @return mixed
     */
    public function postExport()
    {
        $this->assertAdmin();

        $ids = array_filter(array_map('intval', (array)request()->input('projects', [])));
        if (empty($ids)) {
            return $this->screen(['error' => '移行するプロジェクトを選択してください。']);
        }

        $this->lift();
        try {
            $path = Exporter::run(array_values($ids));
        } catch (\Throwable $e) {
            return $this->screen(['error' => $this->message($e, 'export')]);
        }

        return response()->download($path, 'pm-migrate-' . date('Ymd-His') . '.zip')->deleteFileAfterSend(true);
    }

    /**
     * Check a bundle against this Exment, and write it if asked to.
     *
     * @return mixed
     */
    public function postImport()
    {
        $this->assertAdmin();

        $file = request()->file('bundle');
        if ($file === null || !$file->isValid()) {
            return $this->screen(['error' => '移行ファイル(ZIP)を選択してください。']);
        }

        $execute = request()->input('action') === 'execute';
        if ($execute && !request()->boolean('confirm')) {
            return $this->screen(['error' => '実行するには、確認のチェックを入れてください。']);
        }

        $this->lift();
        try {
            $spec = UserMap::parseSpec(strval(request()->input('user_map', '')));
            $mode = request()->input('master_mode') === Importer::MASTER_REPLACE ? Importer::MASTER_REPLACE : Importer::MASTER_ABORT;
            $report = Importer::run(strval($file->getRealPath()), $spec, $mode, $execute);
        } catch (\Throwable $e) {
            return $this->screen(['error' => $this->message($e, 'import')]);
        }

        return $this->screen(['report' => $report, 'ran' => $execute ? 'execute' : 'dry']);
    }

    // ---- helpers --------------------------------------------------------

    /**
     * @param array<string, mixed> $extra
     * @return mixed
     */
    protected function screen(array $extra = [])
    {
        $projects = [];
        $error = $extra['error'] ?? null;
        try {
            $projects = Exporter::projectChoices();
        } catch (\Throwable $e) {
            $error = $error ?? $this->message($e, 'projects');
        }

        return $this->pluginView('index', array_merge([
            'plugin' => $this->_plugin(),
            'projects' => $projects,
            'error' => null,
            'report' => null,
            'ran' => null,
            'urls' => [
                'export' => admin_urls($this->getRouteUri(), 'export'),
                'import' => admin_urls($this->getRouteUri(), 'import'),
            ],
        ], $extra, ['error' => $error]));
    }

    /**
     * System administrators only: the screen reads and writes every project.
     *
     * @return void
     */
    protected function assertAdmin(): void
    {
        $user = \Exment::user();
        if ($user === null || !$user->isAdministrator()) {
            abort(403, 'システム管理者のみ利用できます。');
        }
    }

    /**
     * A bundle can take longer than a web request is normally allowed.
     *
     * @return void
     */
    protected function lift(): void
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
    }

    /**
     * The sentence shown for a failure. Only MigrateException carries one that
     * is safe to show: anything else may hold SQL, a host name or a path.
     *
     * @param \Throwable $e
     * @param string $what
     * @return string
     */
    protected function message(\Throwable $e, string $what): string
    {
        if ($e instanceof MigrateException) {
            return $e->getMessage();
        }

        Log::error('pm_migrate ' . $what . ' failed: ' . $e->getMessage(), ['exception' => $e]);

        return '処理中にエラーが発生しました。サーバーのログを確認してください。';
    }
}

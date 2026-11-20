<?php

namespace App\Plugins\ExmentGroupware;

use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Services\Plugin\PluginPageBase;

/**
 * Exment Plugin Groupware (ISS00233).
 *
 * This first version opens the Word, Excel and PowerPoint files of the file
 * server of Exment (ISS00293) in a browser Office (ibisOffice): the file page
 * gets an "Office で開く" button, the file opens in the editor, and a save
 * becomes a new version of the file. While it is open for editing, the file
 * is checked out, so nobody else changes it at the same time (ibisOffice
 * has no editing together yet).
 */
class Plugin extends PluginPageBase
{
    protected $useCustomOption = true;

    /**
     * Exment builds the page plugin's class on every request (it asks each one
     * for its views), so this is where the button is added to the file page.
     *
     * @param \Exceedone\Exment\Model\Plugin $plugin
     */
    public function __construct($plugin)
    {
        parent::__construct($plugin);
        OfficeOpener::register($plugin);
    }

    /**
     * @param \ExmentAdminCore\Admin\Form $form
     * @return void
     */
    public function setCustomOptionForm(&$form)
    {
        $form->url('ibisoffice_url', 'ibisOffice の場所（URL）')
            ->help('空にすると、このサイトにインストールした ibisOffice ' . IbisOffice::VERSION . ' を使います。ほかの場所の ibisOffice を使うときだけ、index.html のあるフォルダーの URL を入れます（例: https://office.example.com/ibisoffice/0.8.9/）。');
    }

    /**
     * The state of the editor and its installation.
     *
     * @return mixed
     */
    public function index()
    {
        $office = new IbisOffice($this->plugin);
        $user = \Exment::user();
        return $this->pluginView('index', [
            'version' => IbisOffice::VERSION,
            'installed' => $office->isInstalled(),
            'custom_url' => $office->customUrl(),
            'available' => $office->isAvailable(),
            'can_install' => isset($user) && $user->hasPermission(Permission::SYSTEM),
            'install_url' => $this->plugin->getFullUrl('install'),
            'file_server_url' => OfficeOpener::fileServerAvailable() ? admin_url('file_server') : null,
            'project_url' => IbisOffice::PROJECT_URL,
            'extensions' => IbisOffice::EXTENSIONS,
        ]);
    }

    /**
     * Install ibisOffice into the public folder of the site (system administrators).
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function install()
    {
        $user = \Exment::user();
        if (!isset($user) || !$user->hasPermission(Permission::SYSTEM)) {
            abort(403);
        }
        try {
            (new IbisOffice($this->plugin))->install();
            admin_toastr('ibisOffice ' . IbisOffice::VERSION . ' をインストールしました。');
        } catch (\Throwable $e) {
            \Log::error('[ExmentGroupware] ibisOffice install: ' . $e->getMessage());
            admin_toastr('インストールできませんでした: ' . $e->getMessage(), 'error');
        }
        return redirect($this->plugin->getFullUrl());
    }

    /**
     * A file of the file server in the editor: a page of its own (the editor
     * takes the whole window), opened in a new tab from the file page.
     *
     * @param string|int $workspace
     * @param string|int $node
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function office($workspace, $node)
    {
        if (!OfficeOpener::fileServerAvailable()) {
            abort(404);
        }
        $office = new IbisOffice($this->plugin);
        $ws = intval($workspace);
        $id = intval($node);
        $user = \Exment::user();
        $userName = '';
        if (isset($user) && isset($user->base_user)) {
            $userName = strval($user->base_user->getLabel());
        }

        return response(view('exment_exment_groupware::office', [
            'available' => $office->isAvailable(),
            'editor_base' => $office->baseUrl(),
            'office_origin' => $office->officeOrigin(),
            'info_url' => admin_urls('file_server', $ws, 'nodes', $id, 'info'),
            'back_url' => admin_urls('file_server', $ws, 'nodes', $id),
            'settings_url' => $this->plugin->getFullUrl(),
            'user_name' => $userName,
            'locale' => str_starts_with(strval(app()->getLocale()), 'ja') ? 'ja' : 'en',
            'project_url' => IbisOffice::PROJECT_URL,
            'version' => IbisOffice::VERSION,
        ])->render())->header('X-Frame-Options', 'SAMEORIGIN');
    }
}

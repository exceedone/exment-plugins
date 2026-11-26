<?php

namespace App\Plugins\ExmentITSupport;

use Exceedone\Exment\Services\Plugin\PluginPageBase;

/**
 * The agents' screens, packaged as a page plugin.
 *
 * Called PluginPage, not Plugin: with a list in plugin_type, Exment looks for
 * Plugin{Type}. PluginPageController hands over route parameters only, so the
 * request is taken from the container and the work is passed to Desk.
 */
class PluginPage extends PluginPageBase
{
    /**
     * Exment builds this class on every boot to read its views
     * (ExmentServiceProvider::load), which makes it the place to add the
     * routes customers open without logging in. See PublicRoutes.
     *
     * @param \Exceedone\Exment\Model\Plugin $plugin
     */
    public function __construct($plugin)
    {
        parent::__construct($plugin);

        // Exment's "?" of the header opens the manual of the plugin (ISS02882)
        ManualLink::register($plugin);
        try {
            Feature::register($plugin);
        } catch (\Throwable $e) {
            \Log::error('ExmentITSupport could not register its features: ' . $e->getMessage());
        }
        try {
            PublicRoutes::register();
        } catch (\Throwable $e) {
            \Log::error('ExmentITSupport could not add its public routes: ' . $e->getMessage());
        }
        // before the setup: the entry in the left menu that leads to it (7.2.1)
        try {
            Schema::ensurePreSetupMenu();
        } catch (\Throwable $e) {
            \Log::error('ExmentITSupport could not add its menu before the setup: ' . $e->getMessage());
        }
    }

    /** The desk draws its own header. */
    public function _showHeader()
    {
        return false;
    }

    protected function desk(): Desk
    {
        return app(Desk::class);
    }

    /**
     * A view goes back as a view: PluginPageController puts it inside
     * Exment's page, menu and all. Redirects and JSON pass through.
     *
     * @param mixed $result
     * @return mixed
     */
    protected function respond($result)
    {
        return $result;
    }

    public function index()
    {
        return $this->respond($this->desk()->index(request()));
    }

    public function setup()
    {
        return $this->respond($this->desk()->setup());
    }

    public function postSetup()
    {
        return $this->desk()->postSetup(request());
    }

    public function settings()
    {
        return $this->respond($this->desk()->settings());
    }

    public function postSettings()
    {
        return $this->desk()->postSettings(request());
    }

    public function postMailTest()
    {
        return $this->desk()->postMailTest(request());
    }

    public function postFetchNow()
    {
        return $this->desk()->postFetchNow(request());
    }

    public function postIntakeKey()
    {
        return $this->desk()->postIntakeKey(request());
    }

    public function postIntakeSource()
    {
        return $this->desk()->postIntakeSource(request());
    }

    public function postIntakeSourceUpdate($id)
    {
        return $this->desk()->postIntakeSourceUpdate(request(), $id);
    }

    public function postAlertSharedKey()
    {
        return $this->desk()->postAlertSharedKey(request());
    }

    public function dashboard()
    {
        return $this->respond($this->desk()->dashboard(request()));
    }

    public function reportCsv()
    {
        return $this->desk()->reportCsv(request());
    }

    public function newTicket()
    {
        return $this->respond($this->desk()->newTicket(request()));
    }

    public function postNewTicket()
    {
        return $this->desk()->postNewTicket(request());
    }

    public function apiList()
    {
        return $this->desk()->apiList(request());
    }

    public function apiTicket($id)
    {
        return $this->desk()->apiTicket(request(), $id);
    }

    public function knowledgeSearch()
    {
        return $this->desk()->knowledgeSearch(request());
    }

    public function myTickets()
    {
        return $this->respond($this->desk()->myTickets(request()));
    }

    public function postBulk()
    {
        return $this->desk()->postBulk(request());
    }

    public function ticket($id)
    {
        return $this->respond($this->desk()->ticket(request(), $id));
    }

    public function postReply($id)
    {
        return $this->desk()->postReply(request(), $id);
    }

    public function postUpdate($id)
    {
        return $this->desk()->postUpdate(request(), $id);
    }

    public function postShare($id)
    {
        return $this->desk()->postShare(request(), $id);
    }

    public function postToKnowledge($id)
    {
        return $this->desk()->postToKnowledge(request(), $id);
    }

    public function postMerge($id)
    {
        return $this->desk()->postMerge(request(), $id);
    }

    public function postLock($id)
    {
        return $this->desk()->postLock(request(), $id);
    }
}

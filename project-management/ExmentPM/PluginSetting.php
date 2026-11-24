<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Services\Plugin\PluginBase;

/**
 * The plugin's own settings block on Exment's plugin management screen.
 *
 * For a plugin whose plugin_type is a list, Exment resolves the settings
 * class by the fixed name "PluginSetting" (PluginType::getPluginClassShortName
 * with as_setting), which is why this file exists next to PluginPage.
 *
 * What is configured here is the Git server connection a SaaS operator or a
 * self-hosted admin grants the portal - deliberately here and not on any
 * portal screen, because it is space-level infrastructure, guarded by the
 * plugin permission, and none of a project member's business. Values are
 * read back through GitProvisioner::settings(), which also lets each of
 * them be overridden by deployment config (environment variables), so the
 * token never has to touch the database at all.
 */
class PluginSetting
{
    use PluginBase;

    /**
     * @param \Exceedone\Exment\Model\Plugin $plugin
     */
    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Overridden as a method rather than by redeclaring PluginBase's
     * $useCustomOption property: a class property whose default differs from
     * the trait's is a fatal composition error on PHP 8.3, and it takes the
     * whole plugin management screen down with it.
     *
     * @return bool
     */
    public function useCustomOption()
    {
        return true;
    }

    /**
     * @param \ExmentAdminCore\Admin\Form\EmbeddedForm $form
     * @return void
     */
    public function setCustomOptionForm(&$form)
    {
        $t = function ($key) {
            return PortalDefinition::trans('saas.' . $key);
        };

        $form->exmheader($t('header'))->hr();
        $form->description($t('description'));
        $form->description($t('locked'));

        // No editable field, on purpose. Every value here belongs to whoever
        // runs the deployment: the organisation repositories land in, the
        // address of the API, the token that opens it. A customer is given
        // the portal, not the Git server, so what is left on this screen is
        // a read-only summary - and the API URL is not even in that, because
        // nothing on any screen needs to know it.
        //
        // Where the values come from, and how to change them, is in
        // GitProvisioner's class comment: a published config file, then
        // .env, then the plugin options that git-apply-env.php (in
        // storage/app/pmportal-dev) writes.
        $form->html($this->gitStatus(), $t('status'));

        // ---- AI auto-fill (TypeSafe Jev) ---------------------------------
        // Space-level too: the key and the thresholds are the operator's,
        // the per-project switches live on the project settings tab. Every
        // value is read through IssueAutofill::option() with the same
        // default the form shows, so an untouched screen behaves as shown.
        $a = function ($key) {
            return PortalDefinition::trans('ai.opt_' . $key);
        };

        // ---- Teams (ISS02863) ----------------------------------------------
        // The issue notices in the Teams chat of each person the bells go to. Needs Exment's
        // "Teams notifications" (Feature management) and the address of its Teams workflow.
        $form->exmheader(PortalDefinition::trans('teams.setting_header'))->hr();
        $form->switchbool('notify_teams', PortalDefinition::trans('teams.setting'))
            ->help(PortalDefinition::trans(method_exists('Exceedone\Exment\Services\NotifyService', 'notifyTeamsPersonal') ? 'teams.setting_help' : 'teams.setting_unavailable'))
            ->default("0");

        $form->exmheader($a('header'))->hr();
        $form->description($a('description'));

        // Write-only. encpassword would decrypt the stored key back into the
        // input's value attribute, readable by anybody who can open this
        // screen and view its source; the value attribute is fixed empty
        // instead, and the help says whether a key is stored. An empty
        // input keeps the stored key (EncPassword::prepare), so saving the
        // other options never wipes it. new-password stops a browser from
        // filling the administrator's own login password in here.
        $form->encpassword('ai_api_key', $a('api_key'))
            ->attribute('value', '')
            ->attribute('autocomplete', 'new-password')
            ->placeholder($this->aiKeyStored() ? $a('api_key_placeholder') : '')
            ->help($a('api_key_help') . ' ' . $a($this->aiKeyStored() ? 'api_key_state_set' : 'api_key_state_unset'));

        $form->text('ai_model', $a('model'))
            ->help($a('model_help'))
            ->default(JevClient::DEFAULT_MODEL);

        $form->text('ai_threshold_suggest', $a('threshold_suggest'))
            ->help($a('threshold_suggest_help'))
            ->default('0.6');

        $form->text('ai_threshold_auto', $a('threshold_auto'))
            ->help($a('threshold_auto_help'))
            ->default('0.8');

        $form->text('ai_min_description', $a('min_description'))
            ->help($a('min_description_help'))
            ->default('30');

        $form->text('ai_daily_cap', $a('daily_cap'))
            ->help($a('daily_cap_help_min'))
            ->default('300');

        $form->text('ai_assign_cap', $a('assign_cap'))
            ->help($a('assign_cap_help'))
            ->default('15');

        $form->switchbool('ai_mask_pii', $a('mask_pii'))
            ->help($a('mask_pii_help'))
            ->default("1");

        $form->switchbool('ai_priority_top_is_high', $a('top_is_high'))
            ->help($a('top_is_high_help'))
            ->default("1");

        $form->switchbool('ai_severe_bump', $a('severe_bump'))
            ->help($a('severe_bump_help'))
            ->default("1");
    }

    /**
     * Whether a usable TypeSafe key is stored in the plugin options - one
     * that decrypts, which is the one JevClient would use.
     *
     * @return bool
     */
    protected function aiKeyStored(): bool
    {
        $stored = isset($this->plugin) ? $this->plugin->getCustomOption('ai_api_key') : null;

        return !is_nullorempty($stored) && !is_nullorempty(trydecrypt(strval($stored)));
    }

    /**
     * The read-only Git summary: what is configured, and which layer it came
     * from.
     *
     * Two things are deliberately absent. The API URL, because a screen that
     * displays it is one screenshot away from handing out the address of an
     * internal service - and nobody reading this screen has to type it. The
     * token, because it is a password; only whether one is set.
     *
     * The source column is the part an operator cannot work out by looking.
     * All three layers usually hold the same value, and which one answers
     * decides what could change it: "config" survives artisan config:cache,
     * "env" does not, and "db" is a row in the plugins table.
     *
     * @return string
     */
    protected function gitStatus(): string
    {
        $t = function ($key, $params = []) {
            return PortalDefinition::trans('saas.' . $key, $params);
        };

        $settings = GitProvisioner::settings();
        $sources = GitProvisioner::sources();
        $source = function ($key) use ($t, $sources) {
            return $t('source_' . strval(array_get($sources, $key, 'unset')));
        };
        $set = function ($yes) use ($t) {
            return $t($yes ? 'secret_set' : 'secret_unset');
        };

        $rows = [
            [$t('provider'), strval(array_get($settings, 'provider')), $source('provider')],
            [$t('namespace'), strval(array_get($settings, 'namespace')), $source('namespace')],
            [$t('clone_base'), strval(array_get($settings, 'clone_base')), $source('clone_base')],
            [$t('api_url'), $set(!is_nullorempty(array_get($settings, 'api_url'))), $source('api_url')],
            [$t('token'), $set(boolval(array_get($settings, 'has_token'))), ''],
        ];

        $html = '<p><strong>' . e($t(GitProvisioner::configured() ? 'state_on' : 'state_off')) . '</strong></p>';
        $html .= '<table class="table table-sm" style="max-width:40rem">';
        foreach ($rows as $row) {
            $html .= '<tr><th style="width:12rem;font-weight:normal">' . e($row[0]) . '</th>'
                . '<td>' . e($row[1]) . '</td>'
                . '<td style="color:#888">' . e($row[2]) . '</td></tr>';
        }
        $html .= '</table>';

        return $html;
    }
}

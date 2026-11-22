<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\ErrorCode;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\Plugin;
use Illuminate\Http\JsonResponse;

/**
 * What the project portal is built from.
 *
 * The portal itself is generic: it reads everything through this map, so an
 * instance whose tables are named differently overrides config('exment.pmportal')
 * and keeps the same screens. The defaults follow the pm_portal template
 * (project / pm_issue / task_milestone / task_category / pm_wiki).
 */
class PortalDefinition
{
    /**
     * The plugin name, once read off the row. A row cannot be renamed in
     * place - Exment's installer refuses an upload whose name and uuid do not
     * both match - so this holds for as long as this code is loaded.
     *
     * @var string|null
     */
    private static $pluginName;

    /**
     * projectAllows() answers: "user:project:mode:formAction" => [time, bool].
     *
     * @var array<string, array{0: float, 1: bool}>
     */
    private static $projectAnswers = [];

    /**
     * The plugin row this code runs as.
     *
     * Looked up by PHP namespace on purpose. Exment loads a plugin's classes
     * from App\Plugins\{pascalized plugin_name} and refuses an upload whose
     * files declare any other namespace (PluginNamespaceRule), so this is the
     * one identity that cannot drift from the row. The name and the uuid would
     * both have to be copied here by hand, and the page URI is not fixed at
     * all: an admin edits it on Exment's plugin screen.
     *
     * @return Plugin|null null when there is no row to read - a unit test, or a
     *         console boot before the database is reachable
     */
    public static function plugin(): ?Plugin
    {
        try {
            return Plugin::firstRecordCache(function ($plugin) {
                return strcasecmp(strval($plugin->getNameSpace()), __NAMESPACE__) === 0;
            });
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The name the plugin is registered under.
     *
     * Without a row, the namespace stands in: Exment builds it by pascalizing
     * the name, and the snake case every derived name uses comes out the same
     * from either.
     *
     * @return string
     */
    public static function pluginName(): string
    {
        if (static::$pluginName === null) {
            $plugin = static::plugin();
            if (!isset($plugin)) {
                return class_basename(__NAMESPACE__);
            }
            static::$pluginName = strval($plugin->plugin_name);
        }

        return static::$pluginName;
    }

    /**
     * The portal's route prefix under the admin, such as "plugins/exment_pm".
     *
     * Read from the row on every call rather than kept: it is the URI field on
     * Exment's plugin screen, which an admin can change while the portal runs.
     *
     * @return string
     */
    public static function routeUri(): string
    {
        $plugin = static::plugin();
        if (isset($plugin)) {
            return strval($plugin->getRouteUri());
        }

        // Exment's own rule for a row with no uri option.
        return 'plugins/' . snake_case(static::pluginName());
    }

    /**
     * Absolute URL of one of the portal's own routes.
     *
     * @param mixed ...$segments the path below the plugin, one segment per
     *        argument: url($projectId, 'issue', $issueId)
     * @return string
     */
    public static function url(...$segments): string
    {
        return admin_url(static::routeUri(), $segments);
    }

    /**
     * First segments the portal's own routes use (config.json). A project
     * whose code reads the same is addressed by its id instead.
     */
    const RESERVED_SEGMENTS = ['setup', 'settings', 'form-style', 'asset', 'lightapi', 'search', 'projects', 'ranking', 'view'];

    /**
     * How a project is written in an address (ISS00440): its code, such as
     * EXMENT_CUSTOMER, upper-cased the way the portal shows it. The id when
     * the project has no code, or one that would not read as a code - an old
     * code written before the format rule, or one that names a route.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     */
    public static function projectSegment($project): string
    {
        $code = array_get((array) $project->value, strval(array_get(static::config(), 'project.key', 'project_code')));

        return static::codeSegment($code) ?? strval($project->id);
    }

    /**
     * The code as an address segment, or null when it cannot be one.
     *
     * @param mixed $code
     */
    public static function codeSegment($code): ?string
    {
        $code = is_scalar($code) ? trim(strval($code)) : '';
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $code) || in_array(strtolower($code), static::RESERVED_SEGMENTS, true)) {
            return null;
        }

        return strtoupper($code);
    }

    /**
     * A project's page: /plugins/exment_pm/EXMENT_CUSTOMER.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     */
    public static function projectUrl($project): string
    {
        return static::url(static::projectSegment($project));
    }

    /**
     * A project's page by its id, for a caller that holds only the id (a
     * mail, a bell). Read without the permission scope: the address is for
     * someone who may open the issue it leads to, and the project's own
     * page still checks who opens it.
     *
     * @param mixed $projectId
     */
    public static function projectUrlById($projectId): string
    {
        try {
            $table = CustomTable::getEloquent(strval(array_get(static::config(), 'project_table')));
            $project = isset($table) ? $table->getValueModel()->newQueryWithoutScopes()->find($projectId) : null;
            if (isset($project)) {
                return static::projectUrl($project);
            }
        } catch (\Throwable $e) {
            // The id is still a working address.
        }

        return static::url(strval($projectId));
    }

    /**
     * How an issue is written after #issue- (ISS00440): its issue key, such as
     * ISS00239. The id when the issue has no key that can stand in an address.
     *
     * @param \Exceedone\Exment\Model\CustomValue $issue
     */
    public static function issueAnchor($issue): string
    {
        $key = static::issueKeyOf($issue);

        return $key !== '' ? $key : strval($issue->id);
    }

    /**
     * The issue key (ISS00239), or '' when it has none that can stand in an
     * address.
     *
     * @param \Exceedone\Exment\Model\CustomValue $issue
     */
    public static function issueKeyOf($issue): string
    {
        $key = array_get((array) $issue->value, strval(array_get(static::config(), 'issue.key', 'issue_key')));
        $key = is_scalar($key) ? trim(strval($key)) : '';

        return preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $key) ? $key : '';
    }

    /**
     * Where one issue opens: its project's page with #issue-ISS00239.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Exceedone\Exment\Model\CustomValue $issue
     */
    public static function issuePageUrl($project, $issue): string
    {
        return static::projectUrl($project) . '#issue-' . rawurlencode(static::issueAnchor($issue));
    }

    /**
     * The portal's script and stylesheet, served as files of their own so the
     * browser keeps them between pages instead of reading half a megabyte of
     * inline code on every one. Both are written as blade views (script.blade
     * .php, style.blade.php) but hold nothing per request: the text the script
     * needs comes from a JSON block on the page.
     */
    const ASSETS = [
        'script' => ['file' => 'script.blade.php', 'tag' => 'script', 'type' => 'application/javascript; charset=utf-8'],
        'style' => ['file' => 'style.blade.php', 'tag' => 'style', 'type' => 'text/css; charset=utf-8'],
    ];

    /**
     * The asset's code, without the <script> / <style> tags around it.
     */
    public static function assetBody(string $name): ?string
    {
        $def = static::ASSETS[$name] ?? null;
        if ($def === null) {
            return null;
        }
        $text = @file_get_contents(__DIR__ . '/resources/views/' . $def['file']);
        if ($text === false) {
            return null;
        }
        $text = trim($text);
        $open = '<' . $def['tag'] . '>';
        $close = '</' . $def['tag'] . '>';
        if (strpos($text, $open) === 0) {
            $text = substr($text, strlen($open));
        }
        if (substr($text, -strlen($close)) === $close) {
            $text = substr($text, 0, -strlen($close));
        }

        return $text;
    }

    /**
     * Address of an asset, carrying a hash of its content: a new version of
     * the file is a new address, so a year-long browser cache is safe.
     */
    public static function assetUrl(string $name): string
    {
        $file = __DIR__ . '/resources/views/' . (static::ASSETS[$name]['file'] ?? '');
        $version = is_file($file) ? substr(md5((string) filemtime($file) . ':' . filesize($file) . ':' . md5_file($file)), 0, 12) : '0';

        return static::url('asset', $name) . '?v=' . $version;
    }

    /**
     * The namespace Exment registers the plugin's views and lang files under,
     * "exment_" plus the snake case of its name (PluginPageTrait).
     *
     * @return string
     */
    public static function viewNamespace(): string
    {
        return 'exment_' . snake_case(static::pluginName());
    }

    /**
     * A view of the plugin's own, by name: view('list') for list.blade.php.
     *
     * @param string $name
     * @return string
     */
    public static function view(string $name): string
    {
        return static::viewNamespace() . '::' . $name;
    }

    /**
     * The tables the portal needs, keyed by the role each one plays, mapped to
     * the name the bundled template ships with. PortalSetup prefixes these
     * values when the install already owns a table of the same name, so this
     * is the shape of the map it stores, not necessarily the names in use.
     */
    const TABLE_ROLES = [
        'project_table' => 'project',
        'issue_table' => 'pm_issue',
        'wiki_table' => 'pm_wiki',
        'milestone_table' => 'task_milestone',
        'category_table' => 'task_category',
    ];

    /**
     * Every table role in play: the five the template ships plus whatever
     * PortalSchema has added since. Kept as a method rather than folded into
     * the constant so the two lists stay authored in one place each.
     *
     * @return array<string, string>
     */
    public static function tableRoles(): array
    {
        return static::TABLE_ROLES + PortalSchema::TABLE_ROLES;
    }

    /**
     * The lang file every portal string lives in, resources/lang/ja/pmportal.php.
     */
    const LANG_FILE = 'pmportal';

    /**
     * The only locale the portal ships.
     *
     * The plugin is Japanese-only by decision, so every string is resolved
     * against ja regardless of the locale the admin is running in. Asking for
     * the current locale instead would mean an admin set to English gets the
     * raw key back, which is the defect that put keys like
     * "exment_pm_portal::pmportal.schema.values.status.todo" into pm_status.
     */
    const LANG_LOCALE = 'ja';

    /**
     * Whether ensureNamespace() has already done its work this request.
     *
     * @var bool
     */
    private static $namespaceReady = false;

    /**
     * Make sure the plugin's lang directory is registered.
     *
     * ExmentServiceProvider::load() registers it while booting, but only for
     * plugins it finds through PluginType::PLUGIN_TYPE_PLUGIN_USE_VIEW, and
     * only once the database is reachable - the loop sits behind a
     * canConnection() early return. A console run, or a boot that happens
     * while the plugin row's plugin_types is out of sync, therefore leaves the
     * namespace missing, and trans() then hands back the key itself.
     *
     * That is not only ugly on screen: the schema seeder used to persist those
     * keys as master row names, so rows called
     * "exment_pm_portal::pmportal.schema.values.status.todo" ended up in
     * pm_status. Registering here means every entry point gets the same
     * answer, whoever booted the app.
     */
    protected static function ensureNamespace(): void
    {
        if (static::$namespaceReady) {
            return;
        }

        try {
            $namespace = static::viewNamespace();
            $translator = app('translator');
            $loader = $translator->getLoader();
            $registered = method_exists($loader, 'namespaces') ? $loader->namespaces() : [];

            if (!array_key_exists($namespace, $registered)) {
                $translator->addNamespace($namespace, __DIR__ . '/resources/lang');

                // Translator caches the empty result of a lookup made before
                // the namespace existed, so registering it is not enough on its
                // own - the stale miss would answer every later call in this
                // request. There is no public API to forget one namespace, and
                // setLoaded() alone would drop lines other packages added at
                // runtime, so read the map first and put the rest back.
                try {
                    $property = new \ReflectionProperty($translator, 'loaded');
                    $property->setAccessible(true);
                    $loaded = $property->getValue($translator);

                    if (is_array($loaded) && array_key_exists($namespace, $loaded)) {
                        unset($loaded[$namespace]);
                        $translator->setLoaded($loaded);
                    }
                } catch (\Throwable $e) {
                    // A translator that keeps its cache somewhere else still
                    // answers correctly for every call made from here on.
                }
            }
        } catch (\Throwable $e) {
            // The translator is not bound yet. trans() below would fail for the
            // same reason, so leave the flag down and retry on the next call.
            return;
        }

        static::$namespaceReady = true;
    }

    /**
     * Whether a translated string came back as the key it was asked for, which
     * is how Laravel reports a line it could not resolve.
     *
     * Any namespace counts, not only today's: master rows written by an older
     * build carry the key under the name the plugin had then.
     *
     * @param mixed $text
     * @return bool
     */
    public static function isUnresolved($text): bool
    {
        return !is_string($text)
            || $text === ''
            || static::untranslatedKey($text) !== null;
    }

    /**
     * The portal key inside an unresolved string such as
     * "exment_pm_portal::pmportal.schema.values.status.todo", or null when the
     * text is not one.
     *
     * @param string $text
     * @return string|null
     */
    public static function untranslatedKey(string $text): ?string
    {
        $pattern = '/^\w+::' . preg_quote(static::LANG_FILE, '/') . '\.(.+)$/s';

        return preg_match($pattern, $text, $match) === 1 ? $match[1] : null;
    }

    /**
     * Translate a portal string from the plugin's own lang files.
     *
     * Mirrors exmtrans(): an array argument is a replacement map, any other
     * arguments are fed to vsprintf. Kept here so the blades and the
     * controller share one entry point.
     *
     * The locale is pinned to LANG_LOCALE rather than following the admin's,
     * because the portal only ships Japanese.
     *
     * @param string $key
     * @param mixed ...$args
     * @return mixed
     */
    public static function trans(string $key, ...$args)
    {
        static::ensureNamespace();

        $line = static::viewNamespace() . '::' . static::LANG_FILE . '.' . $key;

        if (count($args) > 0 && is_array($args[0])) {
            return trans($line, $args[0], static::LANG_LOCALE);
        }

        $text = trans($line, [], static::LANG_LOCALE);
        if (count($args) > 0) {
            $text = vsprintf($text, $args);
        }

        return $text;
    }

    /**
     * The Japanese sentence for an error code a JSON endpoint answers with.
     *
     * Endpoints keep a machine code in `error` - the client branches on it
     * ('stale', 'in_use') - and put what a person reads in `message`. The
     * codes are English, and they used to reach the screen as they stood:
     * the client printed `detail || error`, so a failed save read
     * "save_failed" or the raw exception text. A code with no entry under
     * api_error still reads as Japanese through the fallback.
     *
     * @param string $code a key under api_error
     * @param array<string, mixed> $replace
     * @return string
     */
    public static function errorMessage(string $code, array $replace = []): string
    {
        foreach (['api_error.' . $code, 'api_error.fallback'] as $key) {
            $text = static::trans($key, $replace);
            if (!static::isUnresolved($text)) {
                return strval($text);
            }
        }

        // The plugin's lang directory itself is unreadable. Exment's own
        // line is still Japanese, which beats showing the key.
        return strval(exmtrans('common.message.error_execute'));
    }

    /**
     * A JSON refusal in the shape every portal screen reads: ok false, the
     * code in `error`, the sentence to show in `message`.
     *
     * $extra is merged last, so a caller holding a more precise Japanese
     * sentence - a core ErrorCode's, say - may replace `message` with it.
     *
     * @param int $status HTTP status
     * @param string $code a key under api_error
     * @param array<string, mixed> $extra more fields for the client
     * @param array<string, mixed> $replace placeholders for the sentence
     * @return JsonResponse
     */
    public static function jsonError(int $status, string $code, array $extra = [], array $replace = []): JsonResponse
    {
        return response()->json(array_merge([
            'ok' => false,
            'error' => $code,
            'message' => static::errorMessage($code, $replace),
        ], $extra), $status);
    }

    /**
     * What the screens need to word a failure that is not one of the portal's
     * own JSON errors: an expired session, a proxy's error page, a dropped
     * connection. Each screen embeds this once and picks the sentence by the
     * HTTP status (0 = no answer at all).
     *
     * @return array{messages: array<string, string>, status: array<int, string>}
     */
    public static function httpErrors(): array
    {
        $messages = static::trans('http_error');

        return [
            'messages' => is_array($messages) ? $messages : [],
            'status' => [
                0 => 'network',
                401 => 'unauthorized',
                403 => 'forbidden',
                404 => 'not_found',
                409 => 'conflict',
                413 => 'too_large',
                419 => 'expired',
                422 => 'invalid',
                429 => 'busy',
                500 => 'server',
                502 => 'unavailable',
                503 => 'unavailable',
                504 => 'unavailable',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $default = [
            'project' => [
                'key' => 'project_code',
                'name' => 'name',
                'status' => 'status',
                'manager' => 'manager',
                'members' => 'members',
                'viewers' => 'viewers',
                // Schema 23 (ISS00333): organizations on the project, as
                // members (edit) and as viewers (read).
                'member_orgs' => 'member_orgs',
                'viewer_orgs' => 'viewer_orgs',
                'start' => 'start_date',
                'end' => 'end_date',
                'note' => 'note',
            ],
            'issue' => [
                'subject' => 'subject',
                'type' => 'issue_type',
                'status' => 'status',
                'priority' => 'priority',
                'assignees' => 'assignees',
                'parent' => 'parent_issue',
                'milestone' => 'milestone',
                'category' => 'category',
                'start' => 'start_date',
                'due' => 'due_date',
                'progress' => 'progress',
                'estimate' => 'estimate_h',
                'actual' => 'actual_h',
                'description' => 'description',
            ],
            'milestone' => [
                'name' => 'name',
                'start' => 'start_date',
                'due' => 'due_date',
                'project' => 'project',
            ],
            // Named here for the same reason the milestone is: the quick add
            // box has to know which column holds the label and which one ties
            // the row to a project, and a deployment that renamed either one
            // overrides the map instead of patching code.
            'category' => [
                'name' => 'name',
                'project' => 'project',
            ],
            'wiki' => [
                'title' => 'title',
                'content' => 'content',
                'parent' => 'parent_page',
            ],

            // The rows behind the converted select columns. Their names live
            // in PortalSchema so the upgrade and the portal agree on them.
            'status_row' => PortalSchema::COLUMNS['status'],
            'issue_type_row' => PortalSchema::COLUMNS['issue_type'],
            'priority_row' => PortalSchema::COLUMNS['priority'],
            'resolution_row' => PortalSchema::COLUMNS['resolution'],
            'related_row' => PortalSchema::COLUMNS['related'],

            // Fields this install may or may not have yet - the portal checks
            // for the column before using one, so an install that has not run
            // the schema upgrade keeps working.
            'issue_extra' => PortalSchema::ISSUE_COLUMNS,
            'project_extra' => PortalSchema::PROJECT_COLUMNS,

            // Status values treated as done. Only used while status is still a
            // word list; once converted, the row's own is_done flag wins.
            'done_statuses' => ['resolved', 'closed'],
        ];

        // The names chosen during setup sit between the shipped defaults and
        // an explicit deployment override, so config() still wins.
        return array_replace_recursive(
            $default + static::tableRoles(),
            PortalSetup::tableMap(),
            (array) config('exment.pmportal', [])
        );
    }

    /**
     * The portal URL for a record of one of the portal tables, or null when the
     * record does not belong to the portal. Used by the notify navbar so a bell
     * click lands back inside the portal instead of on the raw data screen.
     *
     * @param \Exceedone\Exment\Model\CustomValue|null $custom_value
     * @return string|null
     */
    public static function portalRecordUrl($custom_value): ?string
    {
        if (!isset($custom_value)) {
            return null;
        }
        $def = static::config();
        $table_name = optional($custom_value->custom_table)->table_name;
        if ($table_name === $def['project_table']) {
            return static::projectUrl($custom_value);
        }
        if ($table_name !== $def['issue_table'] && $table_name !== $def['wiki_table']) {
            return null;
        }
        if ($custom_value->parent_type !== $def['project_table'] || is_nullorempty($custom_value->parent_id)) {
            return null;
        }
        $anchor = $table_name === $def['issue_table']
            ? '#issue-' . rawurlencode(static::issueAnchor($custom_value))
            : '#wiki-' . $custom_value->id;
        return static::projectUrlById($custom_value->parent_id) . $anchor;
    }

    /**
     * A record's title as it may go into a notification subject.
     *
     * Exment's notification dashboard box prints the subject as HTML, so the
     * two characters that open and close markup are swapped for their
     * full-width forms - a title still reads the same, and cannot become a
     * tag. The column holds 200 characters, and an insert past that fails in
     * strict mode after everything before it was saved, so a longer subject
     * is cut instead.
     */
    public static function notifySubject(string $text): string
    {
        return static::cut(str_replace(['<', '>'], ['＜', '＞'], $text), 200);
    }

    /**
     * A notification body with a link to where it happened at the end, cut
     * to fit as notifyBody() does. Exment shows a notification's body as
     * cleaned HTML, so the link is a link there; the text before it is the
     * same as it was.
     */
    public static function notifyBodyWithLink(string $text, string $url): string
    {
        $link = "\n\n" . '<a href="' . e($url) . '">' . e($url) . '</a>';

        return static::cut($text, max(1, 2000 - mb_strlen($link))) . $link;
    }

    /**
     * A notification body, cut to the 2000 characters its column holds.
     */
    public static function notifyBody(string $text): string
    {
        return static::cut($text, 2000);
    }

    /**
     * $text in at most $max characters, the last one an ellipsis when cut.
     */
    protected static function cut(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 1) . '…';
    }

    /**
     * Whether the signed-in user may edit a row: Exment's own answer, except
     * that a wiki page follows its project.
     *
     * inherit_parent_permission lets everyone on a project read its pages, but
     * Exment has no counterpart for writing - editable_with_parent only adds
     * the project as a second hurdle. A page therefore stayed writable by its
     * author alone, and every other member opened it read-only. Whoever may
     * edit the project, and holds a role that edits wiki pages at all, may
     * edit its pages. A disabled form action, a workflow lock or a deleted
     * page still refuses, in the order Exment itself checks them.
     *
     * Every row that lives in a project also needs the project's consent,
     * found whoever is asking and refused while the project is archived -
     * see projectAllows().
     *
     * @return true|ErrorCode
     */
    public static function enableEdit(CustomValue $row, bool $checkFormAction = false)
    {
        $def = static::config();
        $result = $row->enableEdit($checkFormAction);
        if ($result === true) {
            // Exment asked the project too - but only when the caller's own row
            // scope could still find it. See projectAllows().
            return static::projectAllows($row, $def, $checkFormAction) ? true : ErrorCode::PERMISSION_DENY();
        }
        if (strval($result) !== ErrorCode::PERMISSION_DENY) {
            return $result;
        }

        $table = $row->custom_table;
        if (!$table || $table->table_name !== array_get($def, 'wiki_table')
            || $row->parent_type !== array_get($def, 'project_table')
            || !$table->hasPermission(Permission::AVAILABLE_EDIT_CUSTOM_VALUE)) {
            return $result;
        }
        if ($row->lockedWorkflow()) {
            return ErrorCode::WORKFLOW_LOCK();
        }
        if (!static::projectAllows($row, $def, $checkFormAction)) {
            return $result;
        }
        if ($row->trashed()) {
            return ErrorCode::ALREADY_DELETED();
        }

        return true;
    }

    /**
     * Whether the signed-in user may delete a row: Exment's own answer, with
     * the project's consent on top for a row that lives in one - the same
     * gap enableEdit() closes.
     *
     * @return true|ErrorCode
     */
    public static function enableDelete(CustomValue $row, bool $checkFormAction = false)
    {
        $result = $row->enableDelete($checkFormAction);
        if ($result !== true) {
            return $result;
        }

        return static::projectAllows($row, static::config(), $checkFormAction, 'delete') ? true : ErrorCode::PERMISSION_DENY();
    }

    /**
     * Whether the project a row lives in lets it be written: the project is
     * there, the caller may edit it - or delete it, for a delete, exactly as
     * Exment asks - or only reach it, where the row's table turns
     * editable_with_parent off, and it is not archived. True for a row that
     * does not live in a project.
     *
     * Exment runs the same parent check, but finds the parent through the
     * caller's row scope. For someone taken off a project the project is
     * simply not there any more, so Exment skips the check and answers yes
     * for whatever share they still hold on the row itself - the issue they
     * opened, the one they were assigned. The project is looked up here
     * whoever is asking, and only then judged for the caller.
     *
     * Archived is Backlog's read-only: nothing in the project changes until
     * it is unarchived. PortalRules refuses the save; this keeps every
     * screen from offering it in the first place.
     *
     * Remembered for a few seconds per user and project: a screen asks for
     * every issue of the same project in a row.
     */
    protected static function projectAllows(CustomValue $row, array $def, bool $checkFormAction, string $action = 'edit'): bool
    {
        $projectTable = strval(array_get($def, 'project_table'));
        if ($projectTable === '' || $row->parent_type !== $projectTable || is_nullorempty($row->parent_id)) {
            return true;
        }

        $table = $row->custom_table;
        $mode = (!$table || boolval($table->getOption('editable_with_parent') ?? 1)) ? $action : 'access';
        $key = implode(':', [intval(\Exment::getUserId()), intval($row->parent_id), $mode, $checkFormAction ? 1 : 0]);
        $now = microtime(true);
        if (isset(static::$projectAnswers[$key]) && $now - static::$projectAnswers[$key][0] < 10) {
            return static::$projectAnswers[$key][1];
        }

        $allowed = false;
        $projects = CustomTable::getEloquent($projectTable);
        $project = $projects ? $projects->getValueModel()->newQueryWithoutScopes()->whereNull('deleted_at')->find($row->parent_id) : null;
        if ($project instanceof CustomValue) {
            if ($mode === 'delete') {
                $code = $project->enableDelete($checkFormAction);
            } elseif ($mode === 'edit') {
                $code = $project->enableEdit($checkFormAction);
            } else {
                $code = $project->enableAccess();
            }
            $archived = boolval(array_get((array) $project->value, array_get($def, 'project_extra.archived', 'archived')));
            $allowed = $code === true && !$archived;
        }

        static::$projectAnswers[$key] = [$now, $allowed];

        return $allowed;
    }

    /**
     * The layouts that can be applied to the admin edit / create form when
     * it is opened inside the portal modal. A style is a named set of
     * variables plus a raw CSS rule pack that reshapes the .box / .form-group
     * markup the admin ships with; picking one restyles create, edit and
     * revision-compare in place, without touching the form itself.
     *
     * A deployment can add or replace styles through
     * config('exment.pmportal.form_styles'); setting an id to null removes it.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function formStyles(): array
    {
        $default = static::defaultFormStyles();
        $styles = array_replace_recursive($default, (array) config('exment.pmportal.form_styles', []));

        return array_filter($styles, function ($style) {
            return is_array($style);
        });
    }

    /**
     * The four bundled form layouts. Each one is a small set of variables
     * that the shared body-CSS reads, so a deployment can retint a bundled
     * style just by overriding a few numbers rather than rewriting the CSS.
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function defaultFormStyles(): array
    {
        return [
            'card' => [
                'label' => static::trans('form_styles.card'),
                'description' => static::trans('form_styles.card_desc'),
                'vars' => [
                    '--pp-form-radius' => '10px',
                    '--pp-form-shadow' => '0 8px 24px rgba(15, 42, 60, 0.08)',
                    '--pp-form-gap' => '18px',
                    '--pp-form-label-transform' => 'none',
                    '--pp-form-label-letter' => '0',
                    '--pp-form-input-radius' => '6px',
                    '--pp-form-header-bg' => 'linear-gradient(180deg, var(--bl-box) 0%, var(--bl-box2) 100%)',
                    '--pp-form-header-fs' => '16px',
                    '--pp-form-body-pad' => '18px 22px 22px',
                    '--pp-form-footer-bg' => 'var(--bl-box)',
                    '--pp-form-footer-shadow' => '0 -1px 0 var(--bl-line)',
                    '--pp-form-max' => '960px',
                ],
            ],
            'sheet' => [
                'label' => static::trans('form_styles.sheet'),
                'description' => static::trans('form_styles.sheet_desc'),
                'vars' => [
                    '--pp-form-radius' => '0px',
                    '--pp-form-shadow' => 'none',
                    '--pp-form-gap' => '22px',
                    '--pp-form-label-transform' => 'uppercase',
                    '--pp-form-label-letter' => '0.5px',
                    '--pp-form-input-radius' => '0',
                    '--pp-form-header-bg' => 'transparent',
                    '--pp-form-header-fs' => '20px',
                    '--pp-form-body-pad' => '4px 12px 24px',
                    '--pp-form-footer-bg' => 'transparent',
                    '--pp-form-footer-shadow' => 'none',
                    '--pp-form-max' => '880px',
                ],
            ],
            'compact' => [
                'label' => static::trans('form_styles.compact'),
                'description' => static::trans('form_styles.compact_desc'),
                'vars' => [
                    '--pp-form-radius' => '4px',
                    '--pp-form-shadow' => '0 1px 2px rgba(0,0,0,.05)',
                    '--pp-form-gap' => '6px',
                    '--pp-form-label-transform' => 'none',
                    '--pp-form-label-letter' => '0',
                    '--pp-form-input-radius' => '4px',
                    '--pp-form-header-bg' => 'var(--bl-box2)',
                    '--pp-form-header-fs' => '13px',
                    '--pp-form-body-pad' => '10px 14px 14px',
                    '--pp-form-footer-bg' => 'var(--bl-box2)',
                    '--pp-form-footer-shadow' => '0 -1px 0 var(--bl-line)',
                    '--pp-form-max' => '1100px',
                ],
            ],
            'flow' => [
                'label' => static::trans('form_styles.flow'),
                'description' => static::trans('form_styles.flow_desc'),
                'vars' => [
                    '--pp-form-radius' => '14px',
                    '--pp-form-shadow' => '0 22px 60px rgba(15, 42, 60, 0.18)',
                    '--pp-form-gap' => '26px',
                    '--pp-form-label-transform' => 'none',
                    '--pp-form-label-letter' => '0',
                    '--pp-form-input-radius' => '10px',
                    '--pp-form-header-bg' => 'linear-gradient(135deg, var(--bl-accent) 0%, var(--bl-accent-d) 100%)',
                    '--pp-form-header-fs' => '22px',
                    '--pp-form-body-pad' => '26px 32px 34px',
                    '--pp-form-footer-bg' => 'linear-gradient(180deg, var(--bl-box) 0%, var(--bl-box2) 100%)',
                    '--pp-form-footer-shadow' => '0 -1px 0 var(--bl-line)',
                    '--pp-form-max' => '980px',
                ],
            ],
        ];
    }
}
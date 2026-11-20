<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\NotifyAction;
use Exceedone\Exment\Enums\NotifyTrigger;
use Exceedone\Exment\Enums\PluginEventTrigger;
use Exceedone\Exment\Enums\SharePermission;

/**
 * The Backlog shaped database the portal wants, expressed once.
 *
 * The plugin ships its first five tables as an Exment template. That file is
 * fine for a fresh install but useless for an existing one: it is matched by
 * table name, it cannot add a column to a table somebody already has data in,
 * and hand editing four thousand lines of JSON to add a field is how mistakes
 * happen. So everything the portal has learned from Backlog since that
 * template was written lives here instead, as data, and PortalUpgrade turns it
 * into a template fragment at run time.
 *
 * What Backlog models that the original template did not:
 *
 *  - Status and issue type are rows owned by a project, not a fixed word list.
 *    Two projects can disagree about what "in review" means, and an
 *    administrator can add one without a developer.
 *  - Priority and resolution are rows too, but shared by the whole space.
 *  - An issue records why it was closed (resolution), which releases it
 *    affects (versions) and who else should hear about it (notified users).
 *  - Issues link to each other sideways - relates / blocks / duplicates -
 *    which the parent-child column cannot express.
 *  - A project switches its own features on and off, and retires by being
 *    archived rather than deleted.
 *
 * Every view name is translated rather than written here, because these
 * strings end up on screen the moment the tables are created.
 */
class PortalSchema
{
    /**
     * Bumped whenever this file gains something the upgrade has to apply.
     * PortalUpgrade compares it against the number stored on the plugin row.
     *
     * 15: withdrawn before it reached an install. It turned on Exment's
     * share_setting_sync for the project table, which on every save deletes
     * each share the people columns do not name - the creator's own, the
     * share dialog's, everyone's - and never reaches the issues under the
     * project. PortalMembership revokes exactly what a save took away instead.
     * The number stays used so the history reads in order.
     *
     * 16: the portal's role groups get plugin_access on the plugin itself.
     * Without it Exment turns every non-administrator away from every portal
     * route before the table permissions above are even consulted, so a fresh
     * install only ever worked for system administrators.
     *
     * 17: the member and viewer groups lose their table-wide grant on the
     * issue-link table (see retiredGrants()). It let Exment's own data screen
     * list, change and delete every link in the space, past the checks the
     * portal's API makes; links are now read and written through that API
     * only. The plugin row also starts guarding the wiki and link tables
     * (EVENT_TABLE_ROLES), the priority and resolution tables follow the
     * install prefix like every other table, and the AI back-fill batch is
     * taken off Exment's hourly scheduler (BATCH_HOUR_OFF).
     *
     * 18: projects get display_order, so an administrator can drag them into
     * order in the project menu (ISS00263). Empty until someone drags; the
     * menu falls back to the name.
     *
     * 19: issues get subject_sub, a second subject line (ex1task keeps the
     * Vietnamese subject there), shown under the subject in the list and
     * the detail. The sidebar menu is a single entry (menus()).
     *
     * 20: issues get recurrence (daily, weekdays, weekly, monthly, yearly).
     * When the next turn comes, PortalRecurrence makes the next issue and
     * moves the setting to it.
     *
     * 21: not used. It was taken by ISS00333 while it was on its own branch,
     * which landed after 22 and so became 23. The number stays used so the
     * history reads in order.
     *
     * 22: issues get due_time, an optional time on the due date (ISS00299).
     * Empty means the whole day, as before.
     *
     * 23: projects get member_orgs and viewer_orgs (ISS00333), organization
     * columns that work like members and viewers: each is a share column, so
     * an organization named in one gets a share on the project and everyone
     * in it can write (or read) the project's issues and wiki. The share
     * settings are added by PortalUpgrade::syncShareSettings(), not through
     * the template, whose import deletes every share setting a table has
     * before it writes its own.
     *
     * 24: the "issue saved" notification (notifies()) is written by
     * PortalUpgrade::createMissingNotifies(). It used to ride in the
     * template, whose importer silently skips notifications, so no install
     * ever got it and assignees heard nothing when their issue changed
     * (ISS00343).
     *
     * 25: the sidebar is tidied to the single "プロジェクト管理" entry
     * (ISS00256). PortalUpgrade::tidyLegacyMenus() takes away the menus
     * older versions created - the portal / settings / master branch and the
     * bundled template's table branch - and lifts the portal entry to the
     * top level. Only the plugin's own entries, found by name; the screens
     * stay, opened from the portal's ⚙ 設定.
     *
     * 26: what a Backlog import has nowhere else to put (ISS00070, ISS00113).
     * Issues get where they came from - the source key and URL, who raised
     * and last changed them there and when - because Exment's own created_at
     * and created_user are the importer's, not the original author's; and
     * the source's custom attributes, as "name: value" lines until the
     * portal has per-project custom fields of its own. Milestones get the
     * archived flag Backlog has, and wiki pages get tags and their source.
     * All empty for anything not imported.
     *
     * 26 was 25 on its own branch (PR #33), and ex1task ran it as 25
     * before 1.4.1's menu tidy took that number. Both are safe to run
     * again: the template importer only adds a column a table has not
     * got, and the menu tidy removes entries by name.
     *
     * 27: who else an issue belongs to (ISS00252). Beside the assignees, the
     * sub-assignees who help, the managers who answer for it and the
     * reviewers who check it, and how hard it is. The three people columns
     * join the issue notification's targets the way the notify column did,
     * so naming someone tells them. The author is Exment's own created_user
     * (or, for an import, the source's author) and the watchers are the
     * notify column, so neither needs a column of its own.
     *
     * 28: the member group's grant on projects, issues and wiki pages is
     * edit only. Who is on a project is set through its members columns,
     * which PortalMembership keeps in step. Narrowed in place on the way
     * past 28 (retiredGrants()), so members keep editing what they edit.
     */
    const VERSION = 28;

    /** Key holding the applied version inside plugins.options. */
    const STATE_KEY = 'portal_schema_version';

    /**
     * Tables this schema adds, keyed by the role each one plays. Merged into
     * PortalDefinition::TABLE_ROLES so the rest of the portal reaches them the
     * same way it reaches the original five, prefix and all.
     */
    const TABLE_ROLES = [
        'status_table' => 'pm_status',
        'issue_type_table' => 'pm_issue_type',
        'priority_table' => 'pm_priority',
        'resolution_table' => 'pm_resolution',
        'related_table' => 'pm_related_issue',
    ];

    /**
     * Column names inside the new tables, so a deployment that renames one
     * can override the map instead of patching code.
     */
    const COLUMNS = [
        'status' => ['name' => 'name', 'color' => 'color', 'order' => 'display_order', 'done' => 'is_done'],
        'issue_type' => ['name' => 'name', 'color' => 'color', 'order' => 'display_order', 'template_subject' => 'template_subject', 'template_description' => 'template_description', 'ai_hint' => 'ai_hint'],
        'priority' => ['name' => 'name', 'color' => 'color', 'order' => 'display_order'],
        'resolution' => ['name' => 'name', 'order' => 'display_order'],
        'related' => ['from' => 'from_issue', 'to' => 'to_issue', 'type' => 'relation_type'],
    ];

    /** Columns added to the issue table by this schema. */
    const ISSUE_COLUMNS = [
        'resolution' => 'resolution',
        'versions' => 'versions',
        'notified_users' => 'notified_users',
        // Schema 19: second subject line (e.g. the subject in Vietnamese).
        'subject_sub' => 'subject_sub',
        // Schema 20: repeat the issue (PortalRecurrence).
        'recurrence' => 'recurrence',
        // Schema 22: the time on the due date, or empty for the whole day.
        'due_time' => 'due_time',
        // Schema 26: where an imported issue came from (Backlog and the like).
        'source_key' => 'source_key',
        'source_url' => 'source_url',
        'source_created_by' => 'source_created_by',
        'source_created_at' => 'source_created_at',
        'source_updated_by' => 'source_updated_by',
        'source_updated_at' => 'source_updated_at',
        'custom_fields' => 'custom_fields',
        // Schema 27: the other roles on an issue, and its difficulty.
        'sub_assignees' => 'sub_assignees',
        'managers' => 'managers',
        'reviewers' => 'reviewers',
        'difficulty' => 'difficulty',
    ];

    /**
     * Schema 27: the people columns besides the assignees, in the order the
     * screens show them. Each is told about the issue like the notify
     * column, and has to be on the project.
     */
    const ROLE_COLUMNS = ['sub_assignees', 'managers', 'reviewers'];

    /** Schema 27: the difficulty choices, easiest first. */
    const DIFFICULTIES = ['easy', 'normal', 'hard'];

    /** Columns added to the milestone table by this schema (26). */
    const MILESTONE_COLUMNS = [
        'archived' => 'archived',
    ];

    /** Columns added to the wiki table by this schema (26). */
    const WIKI_COLUMNS = [
        'tags' => 'tags',
        'source_url' => 'source_url',
        'source_created_by' => 'source_created_by',
        'source_created_at' => 'source_created_at',
    ];

    /**
     * Columns added to the category table by this schema (14): the one-line
     * description the AI classifies against, and the person a category's
     * issues go to by default, the way Redmine assigns by category.
     */
    const CATEGORY_COLUMNS = [
        'ai_hint' => 'ai_hint',
        'default_assignee' => 'default_assignee',
    ];

    /** Columns added to the project table by this schema. */
    const PROJECT_COLUMNS = [
        'archived' => 'archived',
        'use_wiki' => 'use_wiki',
        'use_file' => 'use_file',
        'use_gantt' => 'use_gantt',
        'use_git' => 'use_git',
        'subtasking' => 'subtasking',
        'formatting_rule' => 'formatting_rule',
        // Schema 14: the AI auto-fill switches, one for the AI and one for
        // whether it fills in the assignee too.
        'ai_enabled' => 'ai_enabled',
        'ai_assign' => 'ai_assign',
        // Schema 18: the order an administrator dragged the projects into.
        'display_order' => 'display_order',
        // Schema 23: organizations on the project (ISS00333).
        'member_orgs' => 'member_orgs',
        'viewer_orgs' => 'viewer_orgs',
    ];

    /** Menu node names, prefixed per install the same way tables are. */
    const MENU_ROOT = 'pm_portal_root';
    const MENU_PAGE = 'pm_portal_page';
    const MENU_SETTINGS = 'pm_portal_settings';
    const MENU_MASTER = 'pm_portal_master';

    /**
     * Menu node the bundled template created before 1.4.1, with the plugin's
     * data tables under it. The template no longer ships a menu (ISS00256);
     * the name stays so PortalUpgrade::tidyLegacyMenus() can find the node.
     */
    const MENU_TEMPLATE = 'pm_internal';

    /**
     * Role groups the portal sets up, in the order they are offered.
     *
     * Nothing in the running portal reads these names: a screen asks Exment
     * whether this user may edit this table or this record, and an
     * administrator is free to answer that through groups of their own. They
     * exist so that a fresh install has a working answer on day one.
     */
    const ROLE_GROUP_ADMIN = 'pm_admin_group';
    const ROLE_GROUP_MEMBER = 'pm_member_group';
    const ROLE_GROUP_VIEWER = 'pm_viewer_group';

    /** Notification names, stable across languages. See notifies(). */
    const NOTIFY_ISSUE_SAVED = 'pm_issue_assigned';

    // ---- the plugin row ------------------------------------------------

    /**
     * Tables whose saves the plugin's validator and event halves see, by role.
     *
     * Exment calls a validator or event plugin only for the tables listed in
     * its options.target_tables. The wiki and the link table are guarded too:
     * both can be written through Exment's own data screens and web API, not
     * just through the portal.
     */
    const EVENT_TABLE_ROLES = ['project_table', 'issue_table', 'wiki_table', 'related_table'];

    /** Event triggers the plugin row carries (options.event_triggers). */
    const EVENT_TRIGGERS = [PluginEventTrigger::SAVING, PluginEventTrigger::SAVED];

    /**
     * Triggers the portal does not handle and takes off its row if found.
     * Notifications are filtered elsewhere, and a trigger left on the row
     * would make Exment load the event class for every notification it sends
     * on these tables, for nothing.
     */
    const RETIRED_EVENT_TRIGGERS = [PluginEventTrigger::NOTIFY_EXECUTING, PluginEventTrigger::NOTIFY_EXECUTED];

    /**
     * options.batch_hour value that keeps the AI back-fill batch off Exment's
     * hourly scheduler.
     *
     * Exment runs a batch plugin whenever options.batch_hour == the current
     * hour, and an option that was never set compares equal to 0, so the
     * back-fill ran every night at 00:00 with its defaults - every AI-enabled
     * project, 100 paid calls, nothing dry - and spent the day's AI cap before
     * anyone started work. It is meant to be run by hand:
     *
     *   php artisan exment:batch --name=<plugin name> --param1=all --param2=50
     *
     * No hour equals -1. Exment's own plugin screen offers no batch field for a
     * page plugin, so an operator who wants a nightly run sets options.batch_cron
     * on the plugin row instead (a cron entry wins over batch_hour) or calls the
     * command from the system's own scheduler. Either survives a re-upload.
     */
    const BATCH_HOUR_OFF = -1;

    // ---- fallback option lists -----------------------------------------

    /**
     * What to seed when the column being converted has no options to copy.
     *
     * A normal upgrade never reaches these: the seed is built from whatever
     * the install's own select column already offers, so nothing on screen
     * changes. They matter for a table created fresh by this schema.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function fallbackOptions(): array
    {
        return [
            // Labels and colours both follow the word list these rows
            // replaced, which paired every key with its label directly
            // ("todo,...", "doing,...", "review,...", "resolved,...",
            // "closed,..."). They were once shifted a position against that
            // list, so the fourth label was seeded as a not-done state on new
            // projects while the converted project kept it as a done one -
            // the same label meaning two different things by project.
            'status' => [
                ['key' => 'todo', 'color' => '#ed8077', 'done' => false],
                ['key' => 'doing', 'color' => '#4488c5', 'done' => false],
                ['key' => 'review', 'color' => '#868cb7', 'done' => false],
                ['key' => 'resolved', 'color' => '#5eb5a6', 'done' => true],
                ['key' => 'closed', 'color' => '#b0be3c', 'done' => true],
            ],
            // Backlog ships Bug / Task / Request / Other on a new project and
            // "improve" is this portal's own addition, kept because installs
            // already use it.
            'issue_type' => [
                ['key' => 'task', 'color' => '#7ea800'],
                ['key' => 'bug', 'color' => '#990000'],
                ['key' => 'request', 'color' => '#ff9200'],
                ['key' => 'improve', 'color' => '#2779ca'],
                ['key' => 'other', 'color' => '#8e8e8e'],
            ],
            'priority' => [
                ['key' => 'urgent', 'color' => '#c0392b'],
                ['key' => 'high', 'color' => '#e67e22'],
                ['key' => 'normal', 'color' => '#3c8dbc'],
                ['key' => 'low', 'color' => '#95a5a6'],
            ],
        ];
    }

    /**
     * Backlog's close reasons. Unlike the three lists above these have no
     * select column to grow out of, so they are always seeded from here.
     *
     * @return array<int, string>
     */
    public static function resolutionKeys(): array
    {
        // The first five are Backlog's own fixed set - Fixed, Won't Fix,
        // Invalid, Duplication, Cannot Reproduce. "pending" is this portal's
        // extra and stays last so the shipped order still reads like Backlog.
        return ['fixed', 'wontfix', 'invalid', 'duplicate', 'cannot_reproduce', 'pending'];
    }

    /** @return array<int, string> */
    public static function relationTypeKeys(): array
    {
        return ['relates', 'blocks', 'blocked_by', 'duplicates'];
    }

    /**
     * Each relation read from the other end: A blocks B means B is blocked
     * by A. The symmetric kinds answer with themselves, so a detail screen
     * can label a link correctly from whichever side it is looking.
     *
     * @return array<string, string>
     */
    public static function relationInverse(): array
    {
        return [
            'relates' => 'relates',
            'blocks' => 'blocked_by',
            'blocked_by' => 'blocks',
            'duplicates' => 'duplicates',
        ];
    }

    // ---- template fragment ---------------------------------------------

    /**
     * The whole schema as something TemplateImporter::import() understands.
     *
     * Names arrive already resolved - PortalUpgrade passes the map the
     * install actually uses - so nothing here has to know about prefixes.
     *
     * @param array<string, string> $n role => real table name
     * @param string $prefix install prefix, for names that are not tables
     * @return array<string, mixed>
     */
    public static function template(array $n, string $prefix = ''): array
    {
        return [
            'template_name' => $prefix . 'pm_portal_schema',
            'template_view_name' => PortalDefinition::trans('schema.template_name'),
            'description' => PortalDefinition::trans('schema.template_description'),
            'custom_tables' => static::tables($n),
            'custom_relations' => static::relations($n),
            'admin_menu' => static::menus($n, $prefix),
            'notifies' => static::notifies($n, $prefix),
        ];
    }

    /**
     * The notification the portal expects on the issue table.
     *
     * Defined here rather than in the bundled template for the same reason the
     * role permissions are: an install that already owned the portal's tables
     * never ran that import, so it would never get one, and there is no way to
     * add it afterwards short of an administrator rebuilding it by hand.
     *
     * Exment identifies a notification by its display name, which is the one
     * part of it an administrator is expected to edit - rename it and the next
     * upgrade would import a second copy. PortalUpgrade therefore decides what
     * is missing by notify_name, which nothing on screen offers to change.
     *
     * @param array<string, string> $n
     * @param string $prefix
     * @return array<int, array<string, mixed>>
     */
    public static function notifies(array $n, string $prefix = ''): array
    {
        $config = PortalDefinition::config();
        $issue = $config['issue'];
        $extra = array_get($config, 'issue_extra', self::ISSUE_COLUMNS);
        $subject = array_get($issue, 'subject');

        return [
            [
                // The label carries the prefix too: on a prefixed install this
                // is a second, independent copy of the portal, and Exment would
                // otherwise see one notification shared by both.
                'notify_name' => $prefix . self::NOTIFY_ISSUE_SAVED,
                'notify_view_name' => $prefix . PortalDefinition::trans('schema.notify.issue_saved'),
                'notify_trigger' => NotifyTrigger::CREATE_UPDATE_DATA,
                'target_table_name' => $n['issue_table'],
                'trigger_settings' => [
                    'notify_saved_trigger' => ['created', 'updated', 'comment'],
                    // Nobody needs telling about their own edit.
                    'notify_myself' => false,
                    'notify_target_date' => null,
                ],
                'action_settings' => [
                    [
                        'notify_action' => NotifyAction::SHOW_PAGE,
                        // In-app, not mail: whoever has to act on the issue,
                        // whoever asked to hear about it, and whoever opened it
                        // - the circle Backlog tells on every update.
                        'notify_action_target' => array_merge([
                            $n['issue_table'] . '.' . array_get($issue, 'assignees'),
                            $n['issue_table'] . '.' . array_get($extra, 'notified_users', 'notified_users'),
                        ], array_map(function ($role) use ($n, $extra) {
                            // Schema 27: sub-assignees, managers, reviewers.
                            return $n['issue_table'] . '.' . array_get($extra, $role, $role);
                        }, self::ROLE_COLUMNS), [
                            'created_user',
                        ]),
                        // Exment expands ${value:column} when it sends. The
                        // placeholder is named :field rather than :subject so
                        // that the colon inside the value cannot collide with
                        // the one the translator is looking for.
                        'mail_subject' => PortalDefinition::trans('schema.notify.issue_saved_subject', [
                            'field' => '${value:' . $subject . '}',
                        ]),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, string> $n
     * @return array<int, array<string, mixed>>
     */
    protected static function tables(array $n): array
    {
        $tables = static::newTables($n);

        // Existing tables listed with only their new columns. The importer
        // skips a table row it already has but still walks the column list,
        // so this adds fields without touching anything already there.
        $issue = static::issueAdditions($n);
        if (!empty($issue['custom_columns'])) {
            $tables[] = $issue;
        }
        $project = static::projectAdditions($n);
        if (!empty($project['custom_columns'])) {
            $tables[] = $project;
        }

        // Schema 9: the category and milestone lists become draggable in the
        // project settings tab, and a drag needs a column to land in.
        foreach (['category_table', 'milestone_table'] as $master) {
            if (!is_nullorempty(array_get($n, $master))) {
                $tables[] = static::masterAdditions($n[$master]);
            }
        }

        // Schema 14: what the AI needs to know about a category, and who a
        // category's issues go to when nobody says otherwise.
        if (!is_nullorempty(array_get($n, 'category_table'))) {
            $tables[] = static::categoryAdditions($n['category_table']);
        }

        // Schema 26: Backlog's archived milestones, and wiki tags and source.
        if (!is_nullorempty(array_get($n, 'milestone_table'))) {
            $tables[] = static::milestoneAdditions($n['milestone_table']);
        }
        if (!is_nullorempty(array_get($n, 'wiki_table'))) {
            $tables[] = static::wikiAdditions($n['wiki_table']);
        }

        return $tables;
    }

    /**
     * Backlog archives a milestone that is over instead of deleting it, so
     * the issues that point at it keep their history. Off by default.
     *
     * @param string $tableName
     * @return array<string, mixed>
     */
    protected static function milestoneAdditions(string $tableName): array
    {
        $c = self::MILESTONE_COLUMNS;

        return [
            'table_name' => $tableName,
            'custom_columns' => [
                static::column($c['archived'], 'milestone.archived', 'yesno', [
                    'default' => '0',
                    'index_enabled' => '1',
                    'help' => PortalDefinition::trans('schema.help.milestone_archived'),
                ], 93),
            ],
        ];
    }

    /**
     * A wiki page's tags, and for an imported page where it came from.
     *
     * @param string $tableName
     * @return array<string, mixed>
     */
    protected static function wikiAdditions(string $tableName): array
    {
        $c = self::WIKI_COLUMNS;

        return [
            'table_name' => $tableName,
            'custom_columns' => array_merge([
                static::column($c['tags'], 'wiki.tags', 'text', [
                    'freeword_search' => '1',
                    'help' => PortalDefinition::trans('schema.help.wiki_tags'),
                ], 94),
            ], static::sourceColumns($c, 95, false)),
        ];
    }

    /**
     * The "where it came from" columns an import fills in. Exment stamps a
     * row with the importer as its creator and the import as its time; these
     * keep the original author and dates beside them.
     *
     * @param array<string, string> $c the table's column map
     * @param int $order where the first one goes
     * @param bool $withUpdate also the last change at the source
     * @return array<int, array<string, mixed>>
     */
    protected static function sourceColumns(array $c, int $order, bool $withUpdate): array
    {
        $help = ['help' => PortalDefinition::trans('schema.help.source')];
        $columns = [
            static::column($c['source_url'], 'source.url', 'url', $help, $order),
            static::column($c['source_created_by'], 'source.created_by', 'text', $help, $order + 1),
            static::column($c['source_created_at'], 'source.created_at', 'datetime', $help, $order + 2),
        ];
        if ($withUpdate) {
            $columns[] = static::column($c['source_updated_by'], 'source.updated_by', 'text', $help, $order + 3);
            $columns[] = static::column($c['source_updated_at'], 'source.updated_at', 'datetime', $help, $order + 4);
        }

        return $columns;
    }

    /**
     * The tables this schema creates, one per entry of TABLE_ROLES, under the
     * names the install uses.
     *
     * Public for the setup preflight, which has to clear these names as well
     * as the template's before it may promise an install will not land on a
     * table that belongs to something else.
     *
     * @param array<string, string> $n role => real table name
     * @return array<int, array<string, mixed>>
     */
    public static function newTables(array $n): array
    {
        return [
            static::statusTable($n),
            static::issueTypeTable($n),
            static::priorityTable($n),
            static::resolutionTable($n),
            static::relatedTable($n),
        ];
    }

    /**
     * The AI hint and the default assignee of a category.
     *
     * Listed as a second fragment for the same table as masterAdditions()
     * rather than folded into it: the importer walks each fragment's column
     * list on its own, so a table row that is already there still gains the
     * columns it lacks.
     *
     * @param string $tableName
     * @return array<string, mixed>
     */
    protected static function categoryAdditions(string $tableName): array
    {
        $c = self::CATEGORY_COLUMNS;

        return [
            'table_name' => $tableName,
            'custom_columns' => [
                static::column($c['ai_hint'], 'category.ai_hint', 'textarea', [
                    'help' => PortalDefinition::trans('schema.help.ai_hint'),
                ], 91),
                static::column($c['default_assignee'], 'category.default_assignee', 'user', [
                    'help' => PortalDefinition::trans('schema.help.default_assignee'),
                ], 92),
            ],
        ];
    }

    /**
     * A sort handle for a master list the base app created without one.
     *
     * The schema's own masters carry display_order from birth; the category
     * and milestone tables predate this schema and have not got one.
     *
     * @param string $tableName
     * @return array<string, mixed>
     */
    protected static function masterAdditions(string $tableName): array
    {
        return [
            'table_name' => $tableName,
            'custom_columns' => [
                static::column('display_order', 'master.order', 'integer', ['index_enabled' => '1'], 90),
            ],
        ];
    }

    /**
     * @param array<string, string> $n
     * @return array<string, mixed>
     */
    protected static function statusTable(array $n): array
    {
        $c = self::COLUMNS['status'];

        return static::table($n['status_table'], 'status', 'fa-flag-o', [
            static::column($c['name'], 'status.name', 'text', ['index_enabled' => '1', 'required' => '1', 'freeword_search' => '1']),
            static::column($c['color'], 'status.color', 'text', ['help' => PortalDefinition::trans('schema.help.color')]),
            static::column($c['order'], 'status.order', 'integer', ['number_min' => '0', 'default' => '0']),
            static::column($c['done'], 'status.done', 'yesno', ['default' => '0', 'help' => PortalDefinition::trans('schema.help.is_done')]),
        ]);
    }

    /**
     * @param array<string, string> $n
     * @return array<string, mixed>
     */
    protected static function issueTypeTable(array $n): array
    {
        $c = self::COLUMNS['issue_type'];

        return static::table($n['issue_type_table'], 'issue_type', 'fa-bookmark-o', [
            static::column($c['name'], 'issue_type.name', 'text', ['index_enabled' => '1', 'required' => '1', 'freeword_search' => '1']),
            static::column($c['color'], 'issue_type.color', 'text', ['help' => PortalDefinition::trans('schema.help.color')]),
            static::column($c['order'], 'issue_type.order', 'integer', ['number_min' => '0', 'default' => '0']),
            static::column($c['template_subject'], 'issue_type.template_subject', 'text', ['help' => PortalDefinition::trans('schema.help.template_subject')]),
            static::column($c['template_description'], 'issue_type.template_description', 'textarea', []),
            // Schema 14: the one-line description the AI classifies against.
            // Empty means the built-in hint for the standard names applies.
            static::column($c['ai_hint'], 'issue_type.ai_hint', 'textarea', ['help' => PortalDefinition::trans('schema.help.ai_hint')], 10),
        ]);
    }

    /**
     * Named through $n like every other table. Up to 16 these two took the
     * unprefixed default, so a prefixed install created or extended plain
     * "pm_priority" / "pm_resolution" - another install's tables, possibly -
     * while everything else in the portal looked for its own prefixed ones.
     *
     * @param array<string, string> $n
     * @return array<string, mixed>
     */
    protected static function priorityTable(array $n): array
    {
        $c = self::COLUMNS['priority'];

        return static::table($n['priority_table'], 'priority', 'fa-arrows-v', [
            static::column($c['name'], 'priority.name', 'text', ['index_enabled' => '1', 'required' => '1', 'freeword_search' => '1']),
            static::column($c['color'], 'priority.color', 'text', ['help' => PortalDefinition::trans('schema.help.color')]),
            static::column($c['order'], 'priority.order', 'integer', ['number_min' => '0', 'default' => '0']),
        ]);
    }

    /**
     * @param array<string, string> $n
     * @return array<string, mixed>
     */
    protected static function resolutionTable(array $n): array
    {
        $c = self::COLUMNS['resolution'];

        return static::table($n['resolution_table'], 'resolution', 'fa-check-circle-o', [
            static::column($c['name'], 'resolution.name', 'text', ['index_enabled' => '1', 'required' => '1', 'freeword_search' => '1']),
            static::column($c['order'], 'resolution.order', 'integer', ['number_min' => '0', 'default' => '0']),
        ]);
    }

    /**
     * @param array<string, string> $n
     * @return array<string, mixed>
     */
    protected static function relatedTable(array $n): array
    {
        $c = self::COLUMNS['related'];

        return static::table($n['related_table'], 'related', 'fa-link', [
            static::column($c['from'], 'related.from', 'select_table', [
                'index_enabled' => '1',
                'required' => '1',
                'select_load_ajax' => '1',
                'select_target_table_name' => $n['issue_table'],
            ]),
            static::column($c['to'], 'related.to', 'select_table', [
                'index_enabled' => '1',
                'required' => '1',
                'select_load_ajax' => '1',
                'select_target_table_name' => $n['issue_table'],
            ]),
            static::column($c['type'], 'related.type', 'select_valtext', [
                'index_enabled' => '1',
                'required' => '1',
                'default' => 'relates',
                'grid_style' => 'tag',
                'select_item_valtext' => static::valtext(static::relationTypeKeys(), 'related.types.'),
            ]),
        ]);
    }

    /**
     * Fields Backlog keeps on an issue that this install has not got yet.
     *
     * @param array<string, string> $n
     * @return array<string, mixed>
     */
    protected static function issueAdditions(array $n): array
    {
        $ic = self::ISSUE_COLUMNS;

        $columns = [
            static::column($ic['resolution'], 'issue.resolution', 'select_table', [
                'index_enabled' => '1',
                'select_target_table_name' => $n['resolution_table'],
                'help' => PortalDefinition::trans('schema.help.resolution'),
            ], 20),
            static::column($ic['versions'], 'issue.versions', 'select_table', [
                'multiple_enabled' => '1',
                'select_target_table_name' => $n['milestone_table'],
                'help' => PortalDefinition::trans('schema.help.versions'),
            ], 21),
            static::column($ic['notified_users'], 'issue.notified_users', 'user', [
                'multiple_enabled' => '1',
                'grid_style' => 'avatar',
                'help' => PortalDefinition::trans('schema.help.notified_users'),
            ], 22),
            // Schema 19: the second subject line, placed right under the
            // subject (same order; Exment then orders by creation).
            static::column($ic['subject_sub'], 'issue.subject_sub', 'text', [
                'help' => PortalDefinition::trans('schema.help.subject_sub'),
            ], 2),
            // Schema 20: indexed, so a run finds the few recurring issues
            // without reading every row.
            static::column($ic['recurrence'], 'issue.recurrence', 'select_valtext', [
                'index_enabled' => '1',
                'select_item_valtext' => static::valtext(PortalRecurrence::KINDS, 'issue.recurrences.'),
                'help' => PortalDefinition::trans('schema.help.recurrence'),
            ], 23),
            // Schema 22: a time column of its own beside the due date, so
            // the date stays a date for the gantt, the board, CSV and the
            // API, and an issue without a time is exactly what it was.
            static::column($ic['due_time'], 'issue.due_time', 'time', [
                'help' => PortalDefinition::trans('schema.help.due_time'),
            ], 24),
        ];

        // Schema 26: an imported issue's source. The key is indexed and
        // searchable, so an old "PROJ-123" written in a mail still finds it.
        $columns[] = static::column($ic['source_key'], 'source.key', 'text', [
            'index_enabled' => '1',
            'freeword_search' => '1',
            'help' => PortalDefinition::trans('schema.help.source'),
        ], 60);
        $columns = array_merge($columns, static::sourceColumns($ic, 61, true));
        $columns[] = static::column($ic['custom_fields'], 'issue.custom_fields', 'textarea', [
            'freeword_search' => '1',
            'help' => PortalDefinition::trans('schema.help.custom_fields'),
        ], 66);

        // Schema 27: the other people on the issue, right after the
        // assignees (same order; Exment then orders by creation), and the
        // difficulty with the other judgements. Indexed: the lists filter
        // on them like on the assignees.
        foreach (self::ROLE_COLUMNS as $role) {
            $columns[] = static::column($ic[$role], 'issue.' . $role, 'user', [
                'multiple_enabled' => '1',
                'index_enabled' => '1',
                'grid_style' => 'avatar',
                'help' => PortalDefinition::trans('schema.help.' . $role),
            ], 7);
        }
        $columns[] = static::column($ic['difficulty'], 'issue.difficulty', 'select_valtext', [
            'index_enabled' => '1',
            'select_item_valtext' => static::valtext(self::DIFFICULTIES, 'issue.difficulties.'),
            'help' => PortalDefinition::trans('schema.help.difficulty'),
        ], 25);

        return ['table_name' => $n['issue_table'], 'custom_columns' => $columns];
    }

    /**
     * Backlog's per project switches, plus the archive flag that retires a
     * project without deleting its history.
     *
     * @param array<string, string> $n
     * @return array<string, mixed>
     */
    protected static function projectAdditions(array $n): array
    {
        $pc = self::PROJECT_COLUMNS;
        $flags = [
            [$pc['archived'], 'project.archived', '0', 30],
            [$pc['use_wiki'], 'project.use_wiki', '1', 31],
            [$pc['use_file'], 'project.use_file', '1', 32],
            [$pc['use_gantt'], 'project.use_gantt', '1', 33],
            [$pc['use_git'], 'project.use_git', '0', 34],
            [$pc['subtasking'], 'project.subtasking', '1', 35],
            // Schema 14. Off: the issue text goes to a third party's server
            // when it is on, which is a decision for the project.
            [$pc['ai_enabled'], 'project.ai_enabled', '0', 37],
        ];

        $columns = [];
        foreach ($flags as list($name, $key, $default, $order)) {
            $columns[] = static::column($name, $key, 'yesno', ['default' => $default, 'index_enabled' => '1'], $order);
        }

        $columns[] = static::column($pc['formatting_rule'], 'project.formatting_rule', 'select_valtext', [
            'default' => 'markdown',
            'select_item_valtext' => static::valtext(['markdown', 'backlog'], 'project.formatting_rules.'),
        ], 36);

        // Whether the AI fills in the assignee too: the category's default
        // assignee, else whoever has the least open work. On by default.
        // No index - nothing filters on it.
        $columns[] = static::column($pc['ai_assign'], 'project.ai_assign', 'yesno', [
            'default' => '1',
            'help' => PortalDefinition::trans('schema.help.ai_assign'),
        ], 39);

        // Schema 18: drag order in the project menu (ISS00263). Indexed so the
        // menu can sort on it without reading the JSON.
        $columns[] = static::column($pc['display_order'], 'project.display_order', 'integer', [
            'index_enabled' => '1',
        ], 40);

        // Schema 23: organizations as members and as viewers, placed with the
        // people columns (same order as members / viewers; Exment then
        // orders by creation, so each lands right after its user column).
        $columns[] = static::column($pc['member_orgs'], 'project.member_orgs', 'organization', [
            'multiple_enabled' => '1',
            'help' => PortalDefinition::trans('schema.help.member_orgs'),
        ], 6);
        $columns[] = static::column($pc['viewer_orgs'], 'project.viewer_orgs', 'organization', [
            'multiple_enabled' => '1',
            'help' => PortalDefinition::trans('schema.help.viewer_orgs'),
        ], 7);

        return ['table_name' => $n['project_table'], 'custom_columns' => $columns];
    }

    /**
     * The share columns the schema adds to an existing table, with the
     * permission each one grants - the same shape the bundled template gives
     * members (edit) and viewers (view). Triggered on create and on update.
     *
     * @param array<string, string> $n
     * @return array<int, array{table: string, column: string, permission: string}>
     */
    public static function shareSettings(array $n): array
    {
        $pc = self::PROJECT_COLUMNS;

        return [
            ['table' => $n['project_table'], 'column' => $pc['member_orgs'], 'permission' => SharePermission::EDIT],
            ['table' => $n['project_table'], 'column' => $pc['viewer_orgs'], 'permission' => SharePermission::VIEW],
        ];
    }

    /**
     * Status and issue type belong to a project, the way Backlog has it.
     * Priority and resolution are shared by the space, so they get no parent.
     *
     * @param array<string, string> $n
     * @return array<int, array<string, mixed>>
     */
    protected static function relations(array $n): array
    {
        $rows = [];
        foreach (['status_table', 'issue_type_table'] as $role) {
            $rows[] = [
                'relation_type' => '1',
                'options' => [
                    'parent_import_table_name' => null,
                    'parent_import_column_name' => null,
                    'parent_export_table_name' => null,
                    'parent_export_column_name' => null,
                ],
                'parent_custom_table_name' => $n['project_table'],
                'child_custom_table_name' => $n[$role],
            ];
        }

        return $rows;
    }

    /**
     * Where the settings menu entry links to, relative to the admin root.
     * PortalUpgrade::syncSettingsMenu() keeps the stored link equal to this.
     *
     * @return string
     */
    public static function settingsMenuUri(): string
    {
        return PortalDefinition::routeUri() . '/settings';
    }

    /**
     * The plugin's sidebar menu: one top-level entry that opens the portal.
     *
     * @param array<string, string> $n
     * @param string $prefix
     * @return array<int, array<string, mixed>>
     */
    public static function menus(array $n, string $prefix): array
    {
        // ISS00256 (snoro 2026-11-18): the sidebar gets a single entry,
        // "プロジェクト管理", that opens the portal. Settings and the master
        // lists open from the portal's own top bar (⚙ 設定, administrators
        // only). The old root / settings / master branch is no longer created,
        // so a schema upgrade does not bring it back, and an install that
        // still has it loses it in schema 25 (PortalUpgrade::tidyLegacyMenus).
        return [
            static::menu($prefix . self::MENU_PAGE, 'menu.single', 'plugin', null, 'fa-tasks', '34', [
                // Menu::importReplaceJson resolves this through Plugin::getEloquent,
                // which looks the plugin up by name - a uuid finds nothing and
                // leaves the menu pointing at no target, so it never renders.
                'menu_target_name' => PortalSetup::pluginName(),
            ]),
        ];
    }

    /**
     * Every table the portal owns, joined to its three role groups.
     *
     * Deliberately not part of any template: a template import matches a role
     * group by name and, told to leave existing rows alone, leaves the whole
     * group alone - permissions included. An install that already had these
     * groups would therefore never learn about a table added later, and an
     * install that adopted the portal's tables without ever running the
     * bundled template would have groups with no permissions at all.
     * PortalUpgrade writes the missing rows itself instead, one at a time,
     * and never touches a row that is already there - so a permission an
     * administrator tightened by hand survives every upgrade.
     *
     * Project and issue are row-level: what a person sees is what they were
     * assigned. Milestones and categories are per-project lists everyone on
     * the project has to be able to read, so they are view-all for members.
     * Members read the master lists; only the admin group edits the lists
     * themselves.
     *
     * The link table has no grant for members and viewers (since 17). A link
     * belongs to no project, so a table-wide grant there was a grant on every
     * project's links: Exment's own data screen and web API listed, changed
     * and deleted them without asking whether the caller can see either
     * issue. Linking is still ordinary work - the portal's own API does it,
     * after checking both ends against the caller.
     *
     * The wiki table leans on inherit_parent_permission (see tableOptions())
     * to follow the project a page belongs to, rather than needing a per-page
     * assignment - nobody is going to assign each member to each page. That
     * option covers reading only; PortalDefinition::enableEdit() lets whoever
     * may edit the project edit its pages as well.
     *
     * @param array<string, string> $n
     * @return array<string, array<string, array<int, string>>> group => table => permissions
     */
    public static function rolePermissions(array $n): array
    {
        $masters = ['status_table', 'issue_type_table', 'priority_table', 'resolution_table'];

        $admin = [];
        foreach (array_keys(self::TABLE_ROLES) as $role) {
            $admin[$n[$role]] = ['custom_value_edit_all', 'custom_value_share'];
        }

        $member = [];
        $viewer = [];
        foreach ($masters as $role) {
            $member[$n[$role]] = ['custom_value_view_all'];
            $viewer[$n[$role]] = ['custom_value_view_all'];
        }

        // A project and its issues: assigned rows only. Import and export stay
        // with the administrator, because a CSV is how a whole table gets
        // overwritten by accident. Members edit only (schema 28): who is
        // on a project is set through its members columns.
        foreach (['project_table', 'issue_table'] as $role) {
            $admin[$n[$role]] = ['custom_value_edit_all', 'custom_value_import', 'custom_value_export', 'custom_view'];
            $member[$n[$role]] = ['custom_value_edit'];
            $viewer[$n[$role]] = ['custom_value_view'];
        }

        $admin[$n['wiki_table']] = ['custom_value_edit_all', 'custom_value_share'];
        $member[$n['wiki_table']] = ['custom_value_edit'];
        $viewer[$n['wiki_table']] = ['custom_value_view'];

        // The two lists a project picks from. Nobody is assigned to a
        // milestone, so a row-level grant here would hide all of them.
        foreach (['milestone_table', 'category_table'] as $role) {
            $admin[$n[$role]] = ['custom_value_edit_all', 'custom_value_import', 'custom_value_export'];
            $member[$n[$role]] = ['custom_value_view_all'];
            $viewer[$n[$role]] = ['custom_value_view_all'];
        }

        return [
            self::ROLE_GROUP_ADMIN => $admin,
            self::ROLE_GROUP_MEMBER => $member,
            self::ROLE_GROUP_VIEWER => $viewer,
        ];
    }

    /**
     * Permission rows an earlier schema granted and this one withdraws, keyed
     * by the schema version that withdrew them.
     *
     * Each entry is the exact permission set the portal wrote - rolePermissions()
     * as it stood before that version - so PortalUpgrade can take back the rows
     * it created and nothing else: a row whose permissions differ was changed
     * by an administrator and stays.
     *
     * An entry with 'keep' is narrowed instead of removed: the permissions
     * in 'permissions' but not in 'keep' are taken off the row, whatever
     * else it holds, and the row stays.
     *
     * @param array<string, string> $n
     * @return array<int, array<int, array{group: string, table: string, permissions: array<int, string>, keep?: array<int, string>}>>
     */
    public static function retiredGrants(array $n): array
    {
        $narrow = ['permissions' => ['custom_value_edit', 'custom_value_share'], 'keep' => ['custom_value_edit']];

        return [
            17 => [
                ['group' => self::ROLE_GROUP_MEMBER, 'table' => $n['related_table'], 'permissions' => ['custom_value_edit_all']],
                ['group' => self::ROLE_GROUP_VIEWER, 'table' => $n['related_table'], 'permissions' => ['custom_value_view_all']],
            ],
            // Schema 28: the member group edits only.
            28 => [
                ['group' => self::ROLE_GROUP_MEMBER, 'table' => $n['project_table']] + $narrow,
                ['group' => self::ROLE_GROUP_MEMBER, 'table' => $n['issue_table']] + $narrow,
                ['group' => self::ROLE_GROUP_MEMBER, 'table' => $n['wiki_table']] + $narrow,
            ],
        ];
    }

    /**
     * Table options the portal depends on, for tables the template already
     * created without them.
     *
     * A row-level grant on its own means "you see the rows you were assigned
     * to", one row at a time. That is right for projects, where being assigned
     * is the whole point, and wrong for everything hanging off one: nobody is
     * going to assign each member to each wiki page. inherit_parent_permission
     * makes the child follow its parent, so being on a project is enough to
     * read its pages. editable_with_parent only adds the project as a second
     * hurdle for writing - nobody who may not edit the project edits its
     * pages - and PortalDefinition::enableEdit() supplies the grant Exment
     * lacks. The issue table shipped with both and the wiki table shipped
     * with neither, which is why the wiki stayed dark even once it had a
     * permission row.
     *
     * Only listed keys are touched, so an option an administrator set by hand
     * is left alone.
     *
     * @param array<string, string> $n
     * @return array<string, array<string, string>> table => option => value
     */
    public static function tableOptions(array $n): array
    {
        return [
            // Not share_setting_sync on the project table: taking someone off
            // a project is handled by PortalMembership, which removes what
            // that person held and nothing else.
            $n['wiki_table'] => [
                'inherit_parent_permission' => '1',
                'editable_with_parent' => '1',
            ],
        ];
    }

    /**
     * Metadata for the groups above, used when one has to be created because
     * the install never imported the bundled template's role groups.
     *
     * @return array<string, array<string, string>>
     */
    public static function roleGroups(): array
    {
        return [
            self::ROLE_GROUP_ADMIN => ['key' => 'role.admin', 'order' => '10'],
            self::ROLE_GROUP_MEMBER => ['key' => 'role.member', 'order' => '11'],
            self::ROLE_GROUP_VIEWER => ['key' => 'role.viewer', 'order' => '12'],
        ];
    }

    // ---- conversions ---------------------------------------------------

    /**
     * The select columns that stop being a word list and start pointing at a
     * table. Each one keeps its column name, so every view, filter and saved
     * query that mentions it keeps working; only what sits behind it changes.
     *
     * 'scoped' means the new rows hang off a project, so an issue's old value
     * maps to a different row depending on which project it belongs to.
     *
     * @param array<string, string> $n
     * @return array<int, array<string, mixed>>
     */
    public static function conversions(array $n): array
    {
        $def = PortalDefinition::config();
        $issue = $def['issue'];

        return [
            [
                'kind' => 'status',
                'table' => $n['issue_table'],
                'column' => array_get($issue, 'status'),
                'target' => $n['status_table'],
                'scoped' => true,
            ],
            [
                'kind' => 'issue_type',
                'table' => $n['issue_table'],
                'column' => array_get($issue, 'type'),
                'target' => $n['issue_type_table'],
                'scoped' => true,
            ],
            [
                'kind' => 'priority',
                'table' => $n['issue_table'],
                'column' => array_get($issue, 'priority'),
                'target' => $n['priority_table'],
                'scoped' => false,
            ],
        ];
    }

    // ---- builders ------------------------------------------------------

    /**
     * @param string $name
     * @param string $key translation key under schema.table
     * @param string $icon
     * @param array<int, array<string, mixed>> $columns
     * @param string|null $labelName name used for the label column
     * @return array<string, mixed>
     */
    protected static function table(string $name, string $key, string $icon, array $columns, ?string $labelName = null): array
    {
        return [
            'table_name' => $name,
            'table_view_name' => PortalDefinition::trans('schema.table.' . $key),
            'description' => PortalDefinition::trans('schema.table_description.' . $key),
            'system_flg' => '0',
            'showlist_flg' => '1',
            'order' => '0',
            'options' => ['search_enabled' => '1', 'icon' => $icon],
            'custom_columns' => $columns,
        ];
    }

    /**
     * @param string $name
     * @param string $key translation key under schema.column
     * @param string $type
     * @param array<string, mixed> $options
     * @param int $order
     * @return array<string, mixed>
     */
    protected static function column(string $name, string $key, string $type, array $options, int $order = 0): array
    {
        return [
            'column_name' => $name,
            'column_view_name' => PortalDefinition::trans('schema.column.' . $key),
            'column_type' => $type,
            'description' => null,
            'system_flg' => '0',
            'order' => strval($order),
            'options' => $options + [
                'select_import_table_name' => null,
                'select_import_column_name' => null,
                'select_export_table_name' => null,
                'select_export_column_name' => null,
            ],
        ];
    }

    /**
     * @param string $name
     * @param string $key
     * @param string $type
     * @param string|null $parent
     * @param string $icon
     * @param string $order
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    protected static function menu(string $name, string $key, string $type, ?string $parent, string $icon, string $order, array $extra = []): array
    {
        return $extra + [
            'order' => $order,
            'title' => PortalDefinition::trans('schema.' . $key),
            'icon' => $icon,
            'uri' => null,
            'options' => null,
            'menu_type' => $type,
            'menu_name' => $name,
            'parent_name' => $parent,
            'menu_target_name' => null,
        ];
    }

    /**
     * Build an Exment "key,label" option list from translated labels.
     *
     * @param array<int, string> $keys
     * @param string $prefix translation key prefix under schema
     * @return string
     */
    protected static function valtext(array $keys, string $prefix): string
    {
        $lines = [];
        foreach ($keys as $key) {
            $lines[] = $key . ',' . PortalDefinition::trans('schema.' . $prefix . $key);
        }

        return implode("\n", $lines);
    }
}

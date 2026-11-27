<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The chat channels of a project (ISS02939): what happens to its issues, told to Slack, a Teams
 * channel, a LINE WORKS channel, Chatwork, or any address as JSON (a webhook).
 *
 * One row per channel in pm_portal_channels, set on the project's settings (its manager), each
 * with the events it takes: a new issue, a change of the status, a comment (and, once the
 * reminders move here, the reminders). Off at first: Exment's Feature management, "プロジェクト
 * 管理：チャットへのお知らせ".
 *
 * A URL or a token is a secret: kept encrypted (Exment's data keys when the site has them, else
 * the application key), never sent back to a screen (masked), and an address is checked with
 * Exment's WebhookUrlGuard when it is saved and again before each post. A failed post logs the
 * kind of the error only (the message of an HTTP error can carry the URL).
 *
 * What a request does to issues is collected and sent once, after the answer has gone out: one
 * message an issue (its new issue, status and comment together), and one summary when a request
 * changed more issues than a channel should be told one by one (a bulk edit). Nothing is sent
 * from a command (a batch, an import, the Backlog sync): those would flood the channels.
 */
class PortalChannels
{
    const TABLE = 'pm_portal_channels';
    const FEATURE = 'exment_pm.channels';
    const FEATURES = 'Exceedone\Exment\Services\Features';

    const TYPES = ['slack', 'teams', 'line_works', 'chatwork', 'webhook'];
    const EVENTS = ['created', 'status', 'comment'];
    const MAX_CHANNELS = 10;

    /** More issues than this in one request: one summary to a channel instead of a message each. */
    const SUMMARY_OVER = 6;

    const CHATWORK_API = 'https://api.chatwork.com/v2';

    const ENCRYPTED = 'Exceedone\Exment\Services\Encryption\EncryptedValue';
    const DATA_KEYS = 'Exceedone\Exment\Services\Encryption\DataKeys';
    const GUARD = 'Exceedone\Exment\Services\WebhookUrlGuard';
    const LINE_WORKS = 'Exceedone\Exment\Services\LineWorks\LineWorks';

    /** @var \GuzzleHttp\ClientInterface|null a test gives its own */
    public static $http;

    /** @var bool an import that writes many issues turns the events off */
    public static $muted = false;

    /** @var array<int, array<int, array<string, mixed>>>|null project id => its channels, read once */
    protected static $byProject = null;

    /** @var array<int, array<string, mixed>> what a saving issue was before (spl_object_id) */
    protected static $before = [];

    /** @var array<int, array<string, mixed>> issue id => what happened to it in this request */
    protected static $pending = [];

    /** @var bool */
    protected static $flushing = false;

    // ---- the feature -----------------------------------------------------

    public static function registerFeature(): void
    {
        if (!class_exists(static::FEATURES)) {
            return;
        }
        try {
            call_user_func([static::FEATURES, 'register'], static::FEATURE, [
                'label' => strval(PortalDefinition::trans('channels.feature')),
                'description' => strval(PortalDefinition::trans('channels.feature_help')),
                'who' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ExmentPM could not register the channels feature: ' . $e->getMessage());
        }
    }

    public static function enabled(): bool
    {
        if (!static::exists()) {
            return false;
        }
        if (class_exists(static::FEATURES)) {
            try {
                return boolval(call_user_func([static::FEATURES, 'enabled'], static::FEATURE));
            } catch (\Throwable $e) {
                return false;
            }
        }
        return IssueAutofill::flag('channels_on', false);
    }

    /** @var bool|null */
    protected static $exists = null;

    /** The settings tab: for the events of this feature, or for the reminders (Feature management). */
    public static function pageOn(): bool
    {
        return static::exists() && (static::enabled() || PortalReminder::enabled());
    }

    /** @return array<int, string> the events a channel can be given now */
    public static function shownEvents(): array
    {
        $out = static::enabled() ? static::EVENTS : [];
        if (PortalReminder::enabled()) {
            $out[] = 'reminder';
        }
        return $out;
    }

    public static function exists(): bool
    {
        if (static::$exists === null) {
            try {
                static::$exists = \Schema::hasTable(static::TABLE);
            } catch (\Throwable $e) {
                return false;
            }
        }
        return static::$exists;
    }

    // ---- the rows --------------------------------------------------------

    public static function forget(): void
    {
        static::$byProject = null;
    }

    /**
     * The channels of a project, as kept (secrets encrypted).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forProject(int $projectId): array
    {
        if (static::$byProject === null) {
            static::$byProject = [];
            if (static::exists()) {
                foreach (DB::table(static::TABLE)->orderBy('id')->get() as $row) {
                    static::$byProject[intval($row->project_id)][] = static::fromRow($row);
                }
            }
        }

        return static::$byProject[$projectId] ?? [];
    }

    /**
     * The channels of a project that take this event and are on.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forEvent(int $projectId, string $event): array
    {
        return array_values(array_filter(static::forProject($projectId), function ($c) use ($event) {
            return $c['enabled'] && in_array($event, $c['events'], true);
        }));
    }

    /** @return array<string, mixed> */
    protected static function fromRow($row): array
    {
        $events = json_decode(strval($row->events ?? ''), true);

        return [
            'id' => intval($row->id),
            'project_id' => intval($row->project_id),
            'type' => strval($row->type),
            'label' => strval($row->label ?? ''),
            'secret' => strval($row->secret ?? ''),
            'target' => strval($row->target ?? ''),
            'sign_secret' => strval($row->sign_secret ?? ''),
            'events' => array_values(array_intersect(is_array($events) ? $events : [], static::allEvents())),
            'enabled' => boolval($row->enabled),
            'legacy_index' => isset($row->legacy_index) ? intval($row->legacy_index) : null,
        ];
    }

    /** @return array<int, string> the events a channel may take on this site */
    public static function allEvents(): array
    {
        return array_merge(static::EVENTS, ['reminder']);
    }

    /**
     * The channels of a project for its settings: no secret, only what the screen may show.
     *
     * @return array<string, mixed>
     */
    public static function state(int $projectId): array
    {
        $rows = [];
        foreach (static::forProject($projectId) as $c) {
            $rows[] = [
                'id' => $c['id'],
                'type' => $c['type'],
                'label' => $c['label'],
                'masked' => in_array($c['type'], ['line_works'], true) ? '' : static::mask($c['type'], $c['secret']),
                'target' => $c['target'],
                'signed' => $c['sign_secret'] !== '',
                'events' => $c['events'],
                'enabled' => $c['enabled'],
            ];
        }

        return [
            'on' => static::pageOn(),
            'types' => static::types(),
            'events' => static::shownEvents(),
            'max' => static::MAX_CHANNELS,
            'channels' => $rows,
        ];
    }

    /** @return array<int, string> the kinds of channel this site can post to */
    public static function types(): array
    {
        return array_values(array_filter(static::TYPES, function ($t) {
            return $t !== 'line_works' || static::lineWorksAvailable();
        }));
    }

    /**
     * Add a channel or change one. An empty secret keeps the one kept before.
     *
     * @param array<string, mixed> $input
     * @return array{0: int|null, 1: string|null} the id, or the reason it was refused
     */
    public static function save(int $projectId, array $input, int $userId): array
    {
        $id = intval($input['id'] ?? 0);
        $old = null;
        foreach (static::forProject($projectId) as $c) {
            if ($c['id'] === $id) {
                $old = $c;
            }
        }
        if ($id > 0 && $old === null) {
            return [null, 'not_found'];
        }
        if ($id === 0 && count(static::forProject($projectId)) >= static::MAX_CHANNELS) {
            return [null, 'too_many'];
        }
        $type = $old['type'] ?? strval($input['type'] ?? '');
        if (!in_array($type, static::types(), true)) {
            return [null, 'type'];
        }
        $label = mb_substr(trim(strval($input['label'] ?? '')), 0, 40);
        // the events shown now as asked; one not shown (its feature off) kept as it was
        $shown = static::shownEvents();
        $events = array_values(array_unique(array_merge(
            array_intersect((array) ($input['events'] ?? []), $shown),
            array_diff((array) ($old['events'] ?? []), $shown)
        )));
        $values = [
            'type' => $type,
            'label' => $label,
            'events' => json_encode($events),
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'updated_user_id' => $userId,
            'updated_at' => now(),
        ];
        $secret = trim(strval($input['secret'] ?? ''));
        $target = trim(strval($input['target'] ?? ''));
        switch ($type) {
            case 'line_works':
                if (!preg_match('/^[A-Za-z0-9_\-]{1,100}$/', $target)) {
                    return [null, 'channel_id'];
                }
                $values['target'] = $target;
                $values['secret'] = null;
                break;
            case 'chatwork':
                if (!preg_match('/^[0-9]{1,20}$/', $target)) {
                    return [null, 'room_id'];
                }
                $values['target'] = $target;
                if ($secret !== '') {
                    if (!preg_match('/^[A-Za-z0-9]{16,100}$/', $secret)) {
                        return [null, 'token'];
                    }
                    $values['secret'] = static::encrypt($secret);
                } elseif (($old['secret'] ?? '') === '') {
                    return [null, 'token_empty'];
                }
                break;
            default:
                // slack, teams, webhook: an https address
                if ($secret !== '') {
                    if (!static::urlAllowed($secret)) {
                        return [null, 'url'];
                    }
                    $values['secret'] = static::encrypt($secret);
                } elseif (($old['secret'] ?? '') === '') {
                    return [null, 'url_empty'];
                }
                $values['target'] = null;
                if ($type === 'webhook') {
                    $sign = trim(strval($input['sign_secret'] ?? ''));
                    if (!empty($input['sign_clear'])) {
                        $values['sign_secret'] = null;
                    } elseif ($sign !== '') {
                        if (strlen($sign) < 16 || strlen($sign) > 200) {
                            return [null, 'sign'];
                        }
                        $values['sign_secret'] = static::encrypt($sign);
                    }
                }
        }
        if ($old === null) {
            $id = intval(DB::table(static::TABLE)->insertGetId($values + [
                'project_id' => $projectId, 'created_user_id' => $userId, 'created_at' => now(),
            ]));
        } else {
            DB::table(static::TABLE)->where('id', $id)->update($values);
        }
        static::forget();
        Log::info('pm_portal: channel ' . $id . ' (' . $type . ') of project ' . $projectId . ' saved by user ' . $userId);

        return [$id, null];
    }

    public static function remove(int $projectId, int $id, int $userId): bool
    {
        $n = DB::table(static::TABLE)->where('project_id', $projectId)->where('id', $id)->delete();
        static::forget();
        if ($n > 0) {
            Log::info('pm_portal: channel ' . $id . ' of project ' . $projectId . ' removed by user ' . $userId);
        }

        return $n > 0;
    }

    // ---- secrets ---------------------------------------------------------

    /** An https URL Exment may post to. */
    public static function urlAllowed(string $url): bool
    {
        if (!preg_match('#\Ahttps://[^\s"\'<>]+\z#i', $url) || strlen($url) > 2000) {
            return false;
        }
        if (class_exists(static::GUARD)) {
            try {
                return boolval(call_user_func([static::GUARD, 'isAllowed'], $url));
            } catch (\Throwable $e) {
                return false;
            }
        }
        return true;
    }

    public static function encrypt(string $plain): string
    {
        if (class_exists(static::ENCRYPTED) && class_exists(static::DATA_KEYS)) {
            try {
                if (call_user_func([static::DATA_KEYS, 'hasKey'])) {
                    return strval(call_user_func([static::ENCRYPTED, 'encrypt'], $plain));
                }
            } catch (\Throwable $e) {
                // the application key below
            }
        }
        return 'app:' . encrypt($plain);
    }

    public static function decrypt(?string $value): ?string
    {
        $value = strval($value);
        if ($value === '') {
            return null;
        }
        try {
            if (strpos($value, 'app:') === 0) {
                return strval(decrypt(substr($value, 4)));
            }
            if (class_exists(static::ENCRYPTED) && call_user_func([static::ENCRYPTED, 'isEncrypted'], $value)) {
                $plain = call_user_func([static::ENCRYPTED, 'decrypt'], $value);
                return $plain === null ? null : strval($plain);
            }
        } catch (\Throwable $e) {
            Log::warning('pm_portal: a secret of a channel could not be decrypted');
        }
        return null;
    }

    /** The secret as the screen shows it: an address by its host and last 4 characters, a token by its last 4. */
    public static function mask(string $type, ?string $encrypted): string
    {
        $plain = static::decrypt($encrypted);
        if ($plain === null) {
            return '';
        }
        if ($type === 'chatwork') {
            return '…' . substr($plain, -4);
        }
        return 'https://' . strval(parse_url($plain, PHP_URL_HOST)) . '/…' . substr($plain, -4);
    }

    public static function lineWorksAvailable(): bool
    {
        if (!class_exists(static::LINE_WORKS)) {
            return false;
        }
        try {
            return boolval(call_user_func([static::LINE_WORKS, 'enabled']));
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ---- what happens to an issue ----------------------------------------

    /** Whether events are collected now: the feature on, a web request, not muted. */
    protected static function listening(): bool
    {
        return !static::$muted && !app()->runningInConsole() && static::enabled();
    }

    /** Before an issue is saved: what it was (its status), to see after the save what changed. */
    public static function issueSaving(CustomValue $issue): void
    {
        if (!static::listening()) {
            return;
        }
        $statusColumn = strval(array_get(PortalDefinition::config(), 'issue.status', 'status'));
        $old = $issue->exists ? (array) $issue->getOriginal('value') : [];
        static::$before[spl_object_id($issue)] = [
            'new' => !$issue->exists,
            'status' => $issue->exists ? strval(array_get($old, $statusColumn, '')) : null,
        ];
    }

    /** After an issue is saved: a new one, or one whose status changed, is told to its channels. */
    public static function issueSaved(CustomValue $issue): void
    {
        $oid = spl_object_id($issue);
        $before = static::$before[$oid] ?? null;
        unset(static::$before[$oid]);
        if ($before === null || !static::listening()) {
            return;
        }
        $projectId = intval($issue->parent_id);
        if ($projectId <= 0 || empty(static::forProject($projectId))) {
            return;
        }
        $def = PortalDefinition::config();
        $statusColumn = strval(array_get($def, 'issue.status', 'status'));
        $now = strval(array_get($issue->value, $statusColumn, ''));
        if ($before['new']) {
            static::note($issue, 'created', []);
        } elseif ($before['status'] !== $now) {
            static::note($issue, 'status', [
                'from' => static::statusLabel($issue, $statusColumn, strval($before['status'])),
                'to' => static::statusLabel($issue, $statusColumn, $now),
            ]);
        }
    }

    /** A comment posted on an issue (the portal's comment form). */
    public static function commented(CustomValue $issue, string $comment): void
    {
        if (!static::listening() || empty(static::forProject(intval($issue->parent_id)))) {
            return;
        }
        static::note($issue, 'comment', ['comment' => $comment]);
    }

    protected static function statusLabel(CustomValue $issue, string $column, string $value): string
    {
        if ($value === '') {
            return '—';
        }
        try {
            $copy = $issue->replicate();
            $copy->setValue([$column => $value]);
            $label = $copy->getValue($column, true);

            return is_scalar($label) && strval($label) !== '' ? strval($label) : $value;
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /** @param array<string, mixed> $data */
    protected static function note(CustomValue $issue, string $event, array $data): void
    {
        $id = intval($issue->id);
        if (!isset(static::$pending[$id])) {
            static::$pending[$id] = ['issue' => $issue, 'events' => []];
            if (count(static::$pending) === 1 && !static::$flushing) {
                static::$flushing = true;
                app()->terminating(function () {
                    static::flush();
                });
            }
        }
        static::$pending[$id]['issue'] = $issue;
        static::$pending[$id]['events'][$event] = $data;
        static::$pending[$id]['by'] = static::actorName($issue);
    }

    /**
     * Who the change is by: the request's user, or, for an issue the plugin makes on someone's
     * behalf (a recurring schedule's: PortalRules::onBehalf), that user, never the visitor whose
     * page load ran it.
     */
    protected static function actorName(?CustomValue $issue = null): string
    {
        if (isset($issue)) {
            $owner = PortalRules::behalfOf($issue);
            if ($owner !== null) {
                return $owner > 0 ? PortalRunOwner::nameOf($owner) : '';
            }
        }
        try {
            $user = \Exment::user();
            if (!isset($user)) {
                return '';
            }
            // LoginUser: the name of its user (the accessor), else the label of the user row
            $name = strval($user->user_name ?? '');
            if ($name === '' && isset($user->base_user)) {
                $name = strval($user->base_user->getLabel());
            }
            return $name;
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** Send what this request collected. Never throws. */
    public static function flush(): void
    {
        $pending = static::$pending;
        static::$pending = [];
        static::$flushing = false;
        try {
            $def = PortalDefinition::config();
            $projectTable = CustomTable::getEloquent(array_get($def, 'project_table'));
            // per channel, the issues it takes
            $byChannel = [];
            $projects = [];
            foreach ($pending as $issueId => $p) {
                $issue = $p['issue'];
                $pid = intval($issue->parent_id);
                if (!array_key_exists($pid, $projects)) {
                    $projects[$pid] = isset($projectTable) ? $projectTable->getValueModel()->query()->withoutGlobalScopes()->find($pid) : null;
                }
                $project = $projects[$pid];
                if (!isset($project)) {
                    continue;
                }
                foreach (static::forProject($pid) as $c) {
                    if (!$c['enabled']) {
                        continue;
                    }
                    $events = array_intersect_key($p['events'], array_flip($c['events']));
                    if (empty($events)) {
                        continue;
                    }
                    $byChannel[$c['id']]['channel'] = $c;
                    $byChannel[$c['id']]['messages'][] = static::message($project, $issue, $events, strval($p['by'] ?? ''), $def);
                }
            }
            foreach ($byChannel as $one) {
                $messages = $one['messages'];
                if (count($messages) > static::SUMMARY_OVER) {
                    $first = $messages[0];
                    $messages = [static::summary($first['project'], count($messages))];
                }
                foreach ($messages as $m) {
                    static::post($one['channel'], $m);
                }
            }
        } catch (\Throwable $e) {
            Log::error('pm_portal channels: the notices of a request failed (' . get_class($e) . ')');
        }
    }

    /**
     * One message: what happened to one issue, in the words of the channels and as data (webhook).
     *
     * @param array<string, array<string, mixed>> $events
     * @param array<string, mixed> $def
     * @return array<string, mixed>
     */
    protected static function message($project, CustomValue $issue, array $events, string $by, array $def): array
    {
        $pkey = strtoupper(strval(array_get($project->value, array_get($def, 'project.key'), '')));
        $key = ($pkey !== '' ? $pkey : '#') . '-' . $issue->id;
        $subject = strval(array_get($issue->value, array_get($def, 'issue.subject'), ''));
        $url = PortalDefinition::projectUrl($project) . '#issue-' . rawurlencode(PortalDefinition::issueAnchor($issue));

        return static::compose([
            'project' => ['id' => intval($project->id), 'name' => strval($project->getLabel()), 'key' => $pkey, 'url' => PortalDefinition::projectUrl($project)],
            'issue' => ['id' => intval($issue->id), 'key' => $key, 'subject' => $subject, 'url' => $url],
            'by' => $by,
            'events' => $events,
        ]) + ['issue_model' => $issue];
    }

    /**
     * The words of a message (no database: the CI checks it).
     *
     * @param array{project: array<string, mixed>, issue: array<string, mixed>, by: string, events: array<string, array<string, mixed>>} $m
     * @return array<string, mixed> $m with title and lines
     */
    public static function compose(array $m): array
    {
        $t = function ($key, $args = []) {
            return strval(PortalDefinition::trans('channels.msg_' . $key, $args));
        };
        $lines = [];
        $by = $m['by'] !== '' ? $m['by'] : $t('someone');
        if (isset($m['events']['created'])) {
            $lines[] = $t('created', ['by' => $by]);
        }
        if (isset($m['events']['status'])) {
            $lines[] = $t('status', ['from' => strval($m['events']['status']['from'] ?? ''), 'to' => strval($m['events']['status']['to'] ?? ''), 'by' => $by]);
        }
        if (isset($m['events']['comment'])) {
            $comment = trim(preg_replace('/\s+/u', ' ', strval($m['events']['comment']['comment'] ?? '')) ?? '');
            $lines[] = $t('comment', ['by' => $by]) . ' ' . mb_strimwidth($comment, 0, 300, '…');
        }
        $m['title'] = '[' . $m['issue']['key'] . '] ' . $m['issue']['subject'];
        $m['lines'] = $lines;

        return $m;
    }

    /**
     * @param array<string, mixed> $project
     * @return array<string, mixed>
     */
    public static function summary(array $project, int $n): array
    {
        return [
            'project' => $project,
            'issue' => null,
            'by' => '',
            'events' => ['bulk' => ['count' => $n]],
            'title' => '[' . ($project['key'] !== '' ? $project['key'] : $project['name']) . '] ' . strval(PortalDefinition::trans('channels.msg_bulk_title')),
            'lines' => [strval(PortalDefinition::trans('channels.msg_bulk', ['n' => $n]))],
        ];
    }

    // ---- posting ---------------------------------------------------------

    protected static function http(): \GuzzleHttp\ClientInterface
    {
        return static::$http ?? new \GuzzleHttp\Client(['timeout' => 5, 'connect_timeout' => 3, 'allow_redirects' => false]);
    }

    /** Text a person wrote, made harmless for a chat: no template of Exment, no tag of Chatwork. */
    public static function plain(string $s, string $type = ''): string
    {
        $s = str_replace('${', '$ {', $s);
        if ($type === 'chatwork') {
            $s = str_replace(['[', ']'], ['［', '］'], $s);
        }
        return $s;
    }

    /**
     * The body of a webhook (JSON), and its signature header when the channel has a secret.
     *
     * @param array<string, mixed> $m
     * @return array{0: string, 1: array<string, string>}
     */
    public static function webhookBody(array $m, ?string $signSecret, ?string $now = null): array
    {
        $events = array_keys($m['events']);
        $body = json_encode([
            'event' => isset($m['events']['bulk']) ? 'issues.bulk' : 'issue.' . implode('+', $events),
            'project' => $m['project'],
            'issue' => $m['issue'],
            'by' => $m['by'] !== '' ? $m['by'] : null,
            'status' => $m['events']['status'] ?? null,
            'comment' => isset($m['events']['comment']) ? strval($m['events']['comment']['comment'] ?? '') : null,
            'count' => $m['events']['bulk']['count'] ?? null,
            'title' => $m['title'],
            'text' => implode("\n", $m['lines']),
            'sent_at' => $now ?? date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type' => 'application/json', 'User-Agent' => 'ExmentPM', 'X-ExmentPM-Event' => isset($m['events']['bulk']) ? 'issues.bulk' : 'issue.' . implode('+', $events)];
        if ($signSecret !== null && $signSecret !== '') {
            $headers['X-ExmentPM-Signature'] = 'sha256=' . hash_hmac('sha256', strval($body), $signSecret);
        }

        return [strval($body), $headers];
    }

    /** The text of a Chatwork message. */
    public static function chatworkBody(array $m): string
    {
        $url = $m['issue']['url'] ?? $m['project']['url'] ?? '';

        return '[info][title]' . static::plain($m['title'], 'chatwork') . '[/title]'
            . static::plain(implode("\n", $m['lines']), 'chatwork') . "\n" . $url . '[/info]';
    }

    /**
     * Post a message to a channel. Never throws.
     *
     * @param array<string, mixed> $c
     * @param array<string, mixed> $m
     */
    public static function post(array $c, array $m): bool
    {
        $url = strval($m['issue']['url'] ?? $m['project']['url'] ?? '');
        $issue = $m['issue_model'] ?? null;
        try {
            switch ($c['type']) {
                case 'line_works':
                    if (!static::lineWorksAvailable()) {
                        return false;
                    }
                    $content = call_user_func([static::LINE_WORKS, 'content'], static::plain($m['title']), static::plain(implode("\n", $m['lines'])), $url);
                    call_user_func([static::LINE_WORKS, 'send'], call_user_func([static::LINE_WORKS, 'toChannel'], $c['target']), $content);
                    return true;
                case 'chatwork':
                    $token = static::decrypt($c['secret']);
                    if ($token === null || !preg_match('/^[0-9]{1,20}$/', strval($c['target']))) {
                        return false;
                    }
                    $res = static::http()->request('POST', static::CHATWORK_API . '/rooms/' . $c['target'] . '/messages', [
                        'headers' => ['X-ChatWorkToken' => $token],
                        'form_params' => ['body' => static::chatworkBody($m)],
                        'http_errors' => false,
                    ]);
                    return static::answered($c, $res->getStatusCode());
                case 'webhook':
                    $hook = static::decrypt($c['secret']);
                    if ($hook === null || !static::urlAllowed($hook)) {
                        return false;
                    }
                    [$body, $headers] = static::webhookBody($m, static::decrypt($c['sign_secret']));
                    $res = static::http()->request('POST', $hook, ['headers' => $headers, 'body' => $body, 'http_errors' => false]);
                    return static::answered($c, $res->getStatusCode());
                default:
                    // slack, teams: Exment's own senders
                    $hook = static::decrypt($c['secret']);
                    if ($hook === null || !static::urlAllowed($hook)) {
                        return false;
                    }
                    $method = $c['type'] === 'slack' ? 'notifySlack' : 'notifyTeams';
                    call_user_func(['Exceedone\Exment\Services\NotifyService', $method], [
                        'webhook_url' => $hook,
                        'custom_value' => $issue,
                        'subject' => static::plain($m['title']),
                        'body' => static::plain(implode("\n", $m['lines'])) . "\n" . $url,
                    ]);
                    return true;
            }
        } catch (\Throwable $e) {
            // the class only: the message of an HTTP error can carry the URL, a secret
            Log::warning('pm_portal channel ' . intval($c['id'] ?? 0) . ' (' . $c['type'] . ') failed (' . get_class($e) . ')');
            return false;
        }
    }

    protected static function answered(array $c, int $status): bool
    {
        if ($status >= 200 && $status < 300) {
            return true;
        }
        Log::warning('pm_portal channel ' . intval($c['id'] ?? 0) . ' (' . $c['type'] . ') answered ' . $status);
        return false;
    }

    /**
     * A test message from the settings: whether the channel takes it.
     */
    public static function test(int $projectId, int $id, $project): bool
    {
        foreach (static::forProject($projectId) as $c) {
            if ($c['id'] !== $id) {
                continue;
            }
            $p = ['id' => intval($project->id), 'name' => strval($project->getLabel()), 'key' => '', 'url' => PortalDefinition::projectUrl($project)];
            return static::post($c, [
                'project' => $p,
                'issue' => null,
                'by' => static::actorName(),
                'events' => ['test' => []],
                'title' => strval(PortalDefinition::trans('channels.msg_test_title', ['project' => $p['name']])),
                'lines' => [strval(PortalDefinition::trans('channels.msg_test'))],
            ]);
        }
        return false;
    }
}

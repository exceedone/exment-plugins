<?php

namespace App\Plugins\ExmentITSupport;

use Carbon\Carbon;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Model\CustomValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The agents' screens (behind Exment's login).
 *
 * Who is an agent: someone who may edit every ticket ("edit all" on the
 * ticket table) - by default the
 * members of the "ITサポート 担当者" role group the setup made, and system
 * administrators. Anybody else who opens the plugin (a customer in the
 * customer role group) is sent to their own list.
 */
class Desk
{
    const PER_PAGE = 50;

    /** Queues down the left of the inbox, in order. */
    const QUEUES = [
        'todo' => '対応が必要',
        'mine' => '自分の担当',
        'unassigned' => '担当者なし',
        'replied' => 'お客様から返事',
        'sla' => 'SLA 注意・超過',
        'pending' => 'お客様の返事待ち',
        'on_hold' => '保留',
        'alerts' => '監視・エラー',
        'resolved' => '解決',
        'closed' => '完了',
        'all' => 'すべて',
    ];

    // ---- who --------------------------------------------------------------

    public static function isAdmin(): bool
    {
        $user = \Exment::user();

        return isset($user) && $user->isAdministrator();
    }

    public static function isAgent(): bool
    {
        if (static::isAdmin()) {
            return true;
        }
        $table = Its::table(Its::T_TICKET);

        // The desk shows and changes every ticket, so it needs "edit all" on
        // the ticket table (or the table's own admin), not "edit own records".
        return isset($table) && $table->hasPermission([Permission::CUSTOM_TABLE, Permission::CUSTOM_VALUE_EDIT_ALL]);
    }

    protected function view(string $name, array $data = [])
    {
        return view(Its::view($name), array_merge([
            'isAdmin' => static::isAdmin(),
            'flash' => session()->get('its_flash'),
            'flashError' => session()->get('its_flash_error'),
            'needsUpgrade' => Schema::needsUpgrade(),
        ], $data));
    }

    /**
     * Where a form goes after it is sent. The desk posts with fetch() and
     * wants JSON; a plain form (or a browser without JavaScript) gets the
     * redirect with a flash message.
     */
    protected function back(string $url, string $message, bool $error = false)
    {
        $request = request();
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => !$error, 'message' => $message, 'redirect' => $url], $error ? 422 : 200);
        }
        session()->flash($error ? 'its_flash_error' : 'its_flash', $message);

        return redirect($url);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response|null a response that ends the request
     */
    protected function gate(bool $adminOnly = false)
    {
        if (!Its::isInstalled()) {
            return redirect(Its::url('setup'));
        }
        if ($adminOnly ? !static::isAdmin() : !static::isAgent()) {
            return $adminOnly ? response($this->view('denied')->render(), 403) : redirect(Its::url('my'));
        }
        // Only for agents: someone who is merely logged in should not be able
        // to start the upgrade.
        Schema::ensureCurrent();

        return null;
    }

    // ---- inbox -------------------------------------------------------------

    /**
     * The desk: one screen, three panes (queues, tickets, the open ticket).
     * The page itself is a shell; lists and tickets come from apiList() and
     * apiTicket(), so moving between tickets never reloads the page.
     */
    public function index(Request $request, $openId = null)
    {
        if ($r = $this->gate()) {
            return $r;
        }
        $user = \Exment::user();

        return $this->view('desk', [
            'boot' => [
                'me' => ['id' => strval($user->getUserId()), 'name' => strval($user->user_name)],
                'open' => $openId !== null ? intval($openId) : null,
                'queue' => isset(static::QUEUES[$request->get('queue')]) ? strval($request->get('queue')) : 'todo',
                'queues' => static::QUEUES,
                'statuses' => Its::STATUSES,
                'statusColors' => Its::STATUS_COLORS,
                'priorities' => Its::PRIORITIES,
                'priorityColors' => Its::PRIORITY_COLORS,
                'channels' => Its::CHANNELS,
                'slaStates' => Its::SLA_STATES,
                'slaColors' => Its::SLA_COLORS,
                'agents' => (object) Tickets::agentOptions(),
                'categories' => (object) Tickets::options(Its::T_CATEGORY),
                'customers' => (object) Tickets::options(Its::T_CUSTOMER),
                'templates' => $this->templates(),
                'state' => [
                    'imap_on' => Its::setting('imap_enabled') === '1',
                    'imap_error' => array_get(Its::state(), 'imap_error'),
                    'imap_checked_at' => array_get(Its::state(), 'imap_checked_at'),
                    'batch_at' => array_get(Its::state(), 'batch_at'),
                ],
                'url' => [
                    'base' => Its::url(),
                    'list' => Its::url('api', 'list'),
                    'ticket' => Its::url('api', 'ticket', '__ID__'),
                    'reply' => Its::url('ticket', '__ID__', 'reply'),
                    'update' => Its::url('ticket', '__ID__', 'update'),
                    'share' => Its::url('ticket', '__ID__', 'share'),
                    'merge' => Its::url('ticket', '__ID__', 'merge'),
                    'lock' => Its::url('ticket', '__ID__', 'lock'),
                    'knowledge' => Its::url('ticket', '__ID__', 'to-knowledge'),
                    'ksearch' => Its::url('knowledge', 'search'),
                    'bulk' => Its::url('bulk'),
                    'edit' => Its::dataUrl(Its::T_TICKET, '__ID__', 'edit'),
                    'customer' => Its::dataUrl(Its::T_CUSTOMER, '__ID__'),
                    'asset' => Its::dataUrl(Its::T_ASSET, '__ID__'),
                    'public' => Its::publicUrl(),
                ],
            ],
        ]);
    }

    /**
     * One page of a queue, as JSON for the desk.
     */
    public function apiList(Request $request)
    {
        if (!Its::isInstalled() || !static::isAgent()) {
            return response()->json(['error' => 'forbidden'], 403);
        }
        $queue = isset(static::QUEUES[$request->get('queue')]) ? strval($request->get('queue')) : 'todo';
        $f = $this->filters($request);
        $query = $this->queueQuery($queue);
        $this->applyFilters($query, $f);
        $total = (clone $query)->count();
        $page = max(1, intval($request->get('page', 1)));
        $sort = in_array($f['sort'], ['due', 'new', 'old', 'updated', 'priority'], true) ? $f['sort'] : 'due';
        $this->applySort($query, $sort);
        $tickets = $query->skip(($page - 1) * static::PER_PAGE)->take(static::PER_PAGE)->get();
        $labels = Tickets::labels($tickets);

        // The newest message of each ticket, for the preview line.
        $last = [];
        if ($tickets->isNotEmpty()) {
            foreach (Its::table(Its::T_MESSAGE)->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
                ->where('parent_type', Its::T_TICKET)->whereIn('parent_id', $tickets->pluck('id')->all())
                ->where('value->kind', '<>', 'system')->orderByDesc('id')->limit(1000)->get(['id', 'parent_id', 'value']) as $m) {
                if (!isset($last[$m->parent_id])) {
                    $last[$m->parent_id] = $m;
                }
            }
        }

        $counts = [];
        foreach (array_keys(static::QUEUES) as $q) {
            if (!in_array($q, ['all', 'closed', 'resolved'], true)) {
                $counts[$q] = $this->queueQuery($q)->count();
            }
        }

        return response()->json([
            'queue' => $queue,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, intval(ceil($total / static::PER_PAGE))),
            'counts' => $counts,
            'items' => $tickets->map(function ($t) use ($labels, $last) {
                return $this->listItem($t, $labels, $last[$t->id] ?? null);
            })->values()->all(),
        ]);
    }

    /**
     * @param array<string, array<string, string>> $labels
     * @return array<string, mixed>
     */
    protected function listItem(CustomValue $t, array $labels, ?CustomValue $last): array
    {
        $v = (array) $t->value;
        $status = strval($v['status'] ?? 'new');
        $active = in_array($status, Its::ACTIVE_STATUSES, true);
        $due = empty($v['first_responded_at']) ? ($v['first_response_due'] ?? null) : ($v['resolve_due'] ?? null);
        $assignee = strval($v['assignee'] ?? '');
        $preview = $last ? strval(array_get($last->value, 'body')) : strval($v['description'] ?? '');

        return [
            'id' => intval($t->id),
            'no' => strval($v['ticket_no'] ?? ''),
            'subject' => strval($v['subject'] ?? ''),
            'requester' => strval(($v['requester_name'] ?? '') ?: ($v['requester_email'] ?? '')),
            'customer' => $labels['customer'][strval($v['customer'] ?? '')] ?? '',
            'status' => $status,
            'priority' => strval($v['priority'] ?? 'normal'),
            'channel' => strval($v['channel'] ?? ''),
            'category' => $labels['category'][strval($v['category'] ?? '')] ?? '',
            'assignee' => $assignee,
            'assignee_name' => $labels['assignee'][$assignee] ?? '',
            'sla' => strval($v['sla_state'] ?? 'none'),
            'sla_kind' => empty($v['first_responded_at']) ? '返事' : '解決',
            'sla_left' => $active && $due ? Sla::humanLeft($due) : '',
            'sla_due' => $active && $due ? Carbon::parse($due)->toIso8601String() : null,
            'waiting' => $active && !empty($v['last_customer_at']) && (empty($v['last_agent_at']) || $v['last_customer_at'] > $v['last_agent_at']),
            'tags' => array_values(array_filter(array_map('trim', explode(',', strval($v['tags'] ?? ''))))),
            'occurrences' => intval($v['occurrences'] ?? 0),
            'internal' => !boolval($v['customer_visible'] ?? '1'),
            'preview' => mb_substr(preg_replace('/\s+/u', ' ', $preview) ?? '', 0, 120),
            'last_kind' => $last ? strval(array_get($last->value, 'kind')) : 'customer',
            'updated' => Carbon::parse($t->updated_at)->toIso8601String(),
            'created' => Carbon::parse($t->created_at)->toIso8601String(),
        ];
    }

    /**
     * Everything the right-hand panes need for one ticket.
     */
    public function apiTicket(Request $request, $id)
    {
        if (!Its::isInstalled() || !static::isAgent()) {
            return response()->json(['error' => 'forbidden'], 403);
        }
        $ticket = Tickets::find($id);
        if (!isset($ticket)) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $v = (array) $ticket->value;
        $customerId = $v['customer'] ?? null;
        $customer = !empty($customerId) ? Its::table(Its::T_CUSTOMER)->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($customerId) : null;
        $assetId = $v['asset'] ?? null;
        $asset = !empty($assetId) ? Its::table(Its::T_ASSET)->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($assetId) : null;
        $related = [];
        if (!empty($customerId)) {
            $related = Tickets::query()->where('value->customer', strval($customerId))->where('id', '<>', $ticket->id)->orderByDesc('id')->limit(6)->get();
        } elseif (!empty($v['requester_email'])) {
            $related = Tickets::query()->where('value->requester_email', $v['requester_email'])->where('id', '<>', $ticket->id)->orderByDesc('id')->limit(6)->get();
        }
        $messages = Tickets::messages($ticket);
        $first = $messages->first();
        $skip = ($first && array_get($first->value, 'kind') === 'customer' && trim(strval(array_get($first->value, 'body'))) === trim(strval($v['description'] ?? ''))) ? $first->id : null;

        $out = [
            'id' => intval($ticket->id),
            'no' => strval($v['ticket_no'] ?? ''),
            'subject' => strval($v['subject'] ?? ''),
            'description' => strval($v['description'] ?? ''),
            'description_files' => $skip ? strval(array_get($first->value, 'attachments')) : '',
            'requester_name' => strval($v['requester_name'] ?? ''),
            'requester_email' => strval($v['requester_email'] ?? ''),
            'cc' => strval($v['cc'] ?? ''),
            'status' => strval($v['status'] ?? 'new'),
            'priority' => strval($v['priority'] ?? 'normal'),
            'channel' => strval($v['channel'] ?? ''),
            'category' => strval($v['category'] ?? ''),
            'customer' => strval($customerId ?? ''),
            'assignee' => strval($v['assignee'] ?? ''),
            'asset' => strval($assetId ?? ''),
            'tags' => strval($v['tags'] ?? ''),
            'customer_visible' => boolval($v['customer_visible'] ?? '1'),
            'require_otp' => boolval($v['require_otp'] ?? '0'),
            'time_spent' => intval($v['time_spent'] ?? 0),
            'occurrences' => intval($v['occurrences'] ?? 0),
            'last_occurred_at' => strval($v['last_occurred_at'] ?? ''),
            'merged_into' => strval($v['merged_into'] ?? ''),
            'csat' => intval($v['csat'] ?? 0),
            'csat_comment' => strval($v['csat_comment'] ?? ''),
            'created' => Carbon::parse($ticket->created_at)->toIso8601String(),
            'sla' => [
                'state' => strval($v['sla_state'] ?? 'none'),
                'first_due' => $v['first_response_due'] ?? null,
                'first_done' => $v['first_responded_at'] ?? null,
                'first_left' => empty($v['first_responded_at']) ? Sla::humanLeft($v['first_response_due'] ?? null) : '',
                'resolve_due' => $v['resolve_due'] ?? null,
                'resolve_done' => $v['resolved_at'] ?? null,
                'resolve_left' => empty($v['resolved_at']) && in_array($v['status'] ?? '', Its::ACTIVE_STATUSES, true) ? Sla::humanLeft($v['resolve_due'] ?? null) : '',
                'paused' => !empty($v['sla_paused_at']),
            ],
            'messages' => $messages->filter(function ($m) use ($skip) {
                return $m->id !== $skip;
            })->map(function ($m) {
                $mv = (array) $m->value;

                return [
                    'id' => intval($m->id),
                    'kind' => strval($mv['kind'] ?? 'note'),
                    'author' => strval(($mv['author_name'] ?? '') ?: ($mv['author_email'] ?? '')),
                    'email' => strval($mv['author_email'] ?? ''),
                    'body' => strval($mv['body'] ?? ''),
                    'via' => strval($mv['via'] ?? ''),
                    'mail' => strval($mv['mail_status'] ?? 'none'),
                    'minutes' => intval($mv['time_spent'] ?? 0),
                    'files' => strval($mv['attachments'] ?? ''),
                    'at' => Carbon::parse($m->created_at)->toIso8601String(),
                ];
            })->values()->all(),
            'files' => array_map(function ($f) {
                return ['name' => $f->name, 'url' => admin_url('files/' . $f->uuid)];
            }, Tickets::files($ticket)),
            'customer_info' => $customer ? [
                'id' => intval($customer->id),
                'name' => strval(array_get($customer->value, 'name')),
                'phone' => strval(array_get($customer->value, 'phone')),
                'note' => strval(array_get($customer->value, 'note')),
                'contract_hours' => strval(array_get($customer->value, 'contract_hours')),
                'month_minutes' => $this->minutesThisMonth(intval($customer->id)),
            ] : null,
            'asset_info' => $asset ? [
                'id' => intval($asset->id),
                'name' => strval(array_get($asset->value, 'name')),
                'kind' => Its::label(Its::ASSET_KINDS, array_get($asset->value, 'kind')),
                'model' => strval(array_get($asset->value, 'maker_model')),
                'os' => strval(array_get($asset->value, 'os')),
                'warranty_end' => strval(array_get($asset->value, 'warranty_end')),
                'expires_on' => strval(array_get($asset->value, 'expires_on')),
            ] : null,
            'assets' => (object) (!empty($customerId) ? Tickets::options(Its::T_ASSET, 'name', function ($row) use ($customerId) {
                return strval(array_get($row->value, 'customer')) === strval($customerId);
            }) : []),
            'related' => collect($related)->map(function ($r) {
                return ['id' => intval($r->id), 'no' => strval(array_get($r->value, 'ticket_no')), 'subject' => strval(array_get($r->value, 'subject')), 'status' => strval(array_get($r->value, 'status'))];
            })->values()->all(),
            'links' => count(array_filter(Tokens::listForTicket(intval($ticket->id)), function ($t) {
                return empty($t->revoked_at);
            })),
            'access' => array_map(function ($a) {
                return ['at' => Carbon::parse($a->created_at)->toIso8601String(), 'action' => $a->action, 'ip' => $a->ip];
            }, Tokens::accessLog(intval($ticket->id), 8)),
            'other_editor' => Locks::other(intval($ticket->id)),
            'suggest' => Knowledge::search(strval($v['subject'] ?? ''), false, 4),
            'has_password' => Its::looksLikePassword($v['description'] ?? '') || $messages->contains(function ($m) {
                return Its::looksLikePassword(array_get($m->value, 'body'));
            }),
            'new_link' => session()->pull('its_new_link'),
        ];

        return response()->json($out);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        $list = function ($key, array $allowed) use ($request) {
            return array_values(array_intersect((array) $request->get($key, []), array_keys($allowed)));
        };

        return [
            'kw' => trim(mb_substr(strval($request->get('kw', '')), 0, 200)),
            'status' => $list('status', Its::STATUSES),
            'priority' => $list('priority', Its::PRIORITIES),
            'channel' => $list('channel', Its::CHANNELS),
            'category' => strval($request->get('category', '')),
            'customer' => strval($request->get('customer', '')),
            'assignee' => strval($request->get('assignee', '')),
            'tags' => trim(strval($request->get('tags', ''))),
            'tag_mode' => $request->get('tag_mode') === 'or' ? 'or' : 'and',
            'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($request->get('from'))) ? strval($request->get('from')) : '',
            'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($request->get('to'))) ? strval($request->get('to')) : '',
            'sort' => strval($request->get('sort', 'due')),
        ];
    }

    public function queueQuery(string $queue)
    {
        $q = Tickets::query();
        $me = strval(optional(\Exment::user())->getUserId());
        switch ($queue) {
            case 'todo':
                $q->whereIn('value->status', ['new', 'open']);
                break;
            case 'mine':
                $q->whereIn('value->status', Its::ACTIVE_STATUSES)->where('value->assignee', $me);
                break;
            case 'unassigned':
                $q->whereIn('value->status', Its::ACTIVE_STATUSES)->where(function ($w) {
                    $w->whereNull('value->assignee')->orWhere('value->assignee', '');
                });
                break;
            case 'replied':
                // The customer wrote after our last answer.
                $q->whereIn('value->status', ['new', 'open'])->whereNotNull('value->last_customer_at')
                    ->where(function ($w) {
                        $w->whereNull('value->last_agent_at')->orWhereColumn('value->last_customer_at', '>', 'value->last_agent_at');
                    })->whereNotNull('value->last_agent_at');
                break;
            case 'sla':
                $q->whereIn('value->status', Its::ACTIVE_STATUSES)->whereIn('value->sla_state', ['warning', 'breached']);
                break;
            case 'pending':
            case 'on_hold':
            case 'resolved':
            case 'closed':
                $q->where('value->status', $queue);
                break;
            case 'alerts':
                $q->whereIn('value->status', Its::ACTIVE_STATUSES)->where('value->channel', 'monitor');
                break;
        }
        $q->where(function ($w) {
            $w->whereNull('value->merged_into')->orWhere('value->merged_into', '');
        });

        return $q;
    }

    /**
     * Partial match on every word, across the ticket and its conversation;
     * tags with AND or OR. (Whole-word search and no AND/OR on tags are the
     * two search complaints that come up most in reviews of the big tools.)
     */
    protected function applyFilters($q, array $f): void
    {
        foreach (preg_split('/\s+/u', $f['kw']) as $word) {
            if ($word === '') {
                continue;
            }
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word) . '%';
            $messageTicketIds = Its::table(Its::T_MESSAGE)->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
                ->where('parent_type', Its::T_TICKET)->where('value->body', 'like', $like)->limit(2000)->pluck('parent_id')->all();
            $q->where(function ($w) use ($like, $messageTicketIds) {
                foreach (['ticket_no', 'subject', 'description', 'requester_name', 'requester_email', 'tags'] as $col) {
                    $w->orWhere('value->' . $col, 'like', $like);
                }
                if (!empty($messageTicketIds)) {
                    $w->orWhereIn('id', $messageTicketIds);
                }
            });
        }
        foreach (['status', 'priority', 'channel'] as $col) {
            if (!empty($f[$col])) {
                $q->whereIn('value->' . $col, $f[$col]);
            }
        }
        foreach (['category', 'customer'] as $col) {
            if ($f[$col] !== '' && ctype_digit($f[$col])) {
                $q->where('value->' . $col, $f[$col]);
            }
        }
        if ($f['assignee'] === 'none') {
            $q->where(function ($w) {
                $w->whereNull('value->assignee')->orWhere('value->assignee', '');
            });
        } elseif ($f['assignee'] !== '' && ctype_digit($f['assignee'])) {
            $q->where('value->assignee', $f['assignee']);
        }
        $tags = array_values(array_filter(array_map('trim', preg_split('/[,、\s]+/u', $f['tags']))));
        if (!empty($tags)) {
            $q->where(function ($w) use ($tags, $f) {
                foreach ($tags as $tag) {
                    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $tag) . '%';
                    $f['tag_mode'] === 'or' ? $w->orWhere('value->tags', 'like', $like) : $w->where('value->tags', 'like', $like);
                }
            });
        }
        if ($f['from'] !== '') {
            $q->where('created_at', '>=', $f['from'] . ' 00:00:00');
        }
        if ($f['to'] !== '') {
            $q->where('created_at', '<=', $f['to'] . ' 23:59:59');
        }
    }

    protected function applySort($q, string $sort): void
    {
        switch ($sort) {
            case 'new':
                $q->orderByDesc('id');
                break;
            case 'old':
                $q->orderBy('id');
                break;
            case 'updated':
                $q->orderByDesc('updated_at');
                break;
            case 'priority':
                $q->orderByRaw("CASE " . $this->jsonText('priority') . " WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")->orderBy('id');
                break;
            default:
                // Soonest limit first; tickets without one after them.
                $q->orderByRaw('CASE WHEN ' . $this->jsonText('resolve_due') . ' IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('value->resolve_due')->orderBy('id');
        }
    }

    /** A JSON value as text, in this database's dialect. */
    protected function jsonText(string $column): string
    {
        $grammar = DB::connection()->getQueryGrammar();

        return $grammar->wrap('value->' . $column);
    }

    // ---- one ticket --------------------------------------------------------

    /** A link to one ticket opens the desk with that ticket open. */
    public function ticket(Request $request, $id)
    {
        return $this->index($request, $id);
    }

    /**
     * @return array<int, array{id: int, name: string, body: string, status: string}>
     */
    protected function templates(): array
    {
        $table = Its::table(Its::T_TEMPLATE);
        if (!isset($table)) {
            return [];
        }

        return $table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->orderBy('id')->limit(200)->get()->map(function ($t) {
            return ['id' => intval($t->id), 'name' => strval(array_get($t->value, 'name')), 'body' => strval(array_get($t->value, 'body')), 'status' => strval(array_get($t->value, 'set_status'))];
        })->all();
    }

    protected function minutesThisMonth(int $customerId): int
    {
        $start = Carbon::now()->startOfMonth()->format('Y-m-d H:i:s');
        $ticketIds = Tickets::query()->where('value->customer', strval($customerId))->pluck('id')->all();
        if (empty($ticketIds)) {
            return 0;
        }

        return intval(Its::table(Its::T_MESSAGE)->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where('parent_type', Its::T_TICKET)->whereIn('parent_id', $ticketIds)->where('created_at', '>=', $start)
            ->get(['value'])->sum(function ($m) {
                return intval(array_get($m->value, 'time_spent', 0));
            }));
    }

    public function postReply(Request $request, $id)
    {
        if ($r = $this->gate()) {
            return $r;
        }
        $ticket = Tickets::find($id);
        if (!isset($ticket)) {
            return $this->back(Its::url(), 'チケットが見つかりません。', true);
        }
        $body = trim(strval($request->get('body', '')));
        $files = [];
        foreach ((array) $request->file('files', []) as $file) {
            if ($file && $file->isValid() && count($files) < 10) {
                $files[] = ['name' => $file->getClientOriginalName(), 'content' => file_get_contents($file->getRealPath())];
            }
        }
        if ($body === '' && empty($files)) {
            return $this->back(Its::url('ticket', strval($ticket->id)), '本文を書いてください。', true);
        }
        $result = Tickets::reply($ticket, [
            'body' => $body,
            'kind' => $request->get('kind') === 'note' ? 'note' : 'reply',
            'status' => strval($request->get('status', '')),
            'time_spent' => intval($request->get('time_spent', 0)),
            'cc' => $request->has('cc') ? strval($request->get('cc')) : null,
        ], $files);

        $message = $request->get('kind') === 'note' ? '社内メモを残しました。'
            : ($result['mailed'] === true ? 'お客様にメールで送りました。' : ($result['mailed'] === false ? '保存しましたが、メールを送れませんでした（メールの設定を確かめてください）。' : '保存しました（お客様のメールアドレスがないので、メールは送っていません）。'));

        return $this->back(Its::url('ticket', strval($ticket->id)), $message, $result['mailed'] === false);
    }

    public function postUpdate(Request $request, $id)
    {
        if ($r = $this->gate()) {
            return $r;
        }
        $ticket = Tickets::find($id);
        if (!isset($ticket)) {
            return $this->back(Its::url(), 'チケットが見つかりません。', true);
        }
        $values = [];
        foreach (['status' => Its::STATUSES, 'priority' => Its::PRIORITIES] as $col => $allowed) {
            if (isset($allowed[strval($request->get($col))])) {
                $values[$col] = strval($request->get($col));
            }
        }
        foreach (['assignee', 'category', 'customer', 'asset'] as $col) {
            if ($request->has($col)) {
                $v = strval($request->get($col));
                $values[$col] = ctype_digit($v) ? $v : null;
            }
        }
        foreach (['tags' => 200, 'cc' => 500, 'subject' => 250] as $col => $max) {
            if ($request->has($col)) {
                $values[$col] = mb_substr(trim(strval($request->get($col))), 0, $max);
            }
        }
        if (isset($values['subject']) && $values['subject'] === '') {
            unset($values['subject']);
        }
        foreach (['customer_visible', 'require_otp'] as $col) {
            if ($request->has($col)) {
                $values[$col] = $request->get($col) === '1' ? '1' : '0';
            }
        }
        if ($request->has('time_add') && intval($request->get('time_add')) > 0) {
            $values['time_spent'] = intval(array_get($ticket->value, 'time_spent', 0)) + intval($request->get('time_add'));
        }
        $ticket->setValue($values);
        $ticket->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return $this->back(Its::url('ticket', strval($ticket->id)), '保存しました。');
    }

    /** Make, revoke the customer's links. A new link is shown once. */
    public function postShare(Request $request, $id)
    {
        if ($r = $this->gate()) {
            return $r;
        }
        $ticket = Tickets::find($id);
        if (!isset($ticket)) {
            return $this->back(Its::url(), 'チケットが見つかりません。', true);
        }
        $url = Its::url('ticket', strval($ticket->id));
        if ($request->get('action') === 'revoke') {
            $n = Tokens::revokeTicket(intval($ticket->id));

            return $this->back($url, "リンクを {$n} 件取り消しました。前に送ったリンクは開けなくなります。");
        }
        $email = Its::normalizeEmail(strval(array_get($ticket->value, 'requester_email')));
        $link = Tokens::linkFor(intval($ticket->id), Its::isEmail($email) ? $email : null);
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'link' => $link, 'message' => 'お客様のリンクです。コピーして送れます。']);
        }
        session()->flash('its_new_link', $link);

        return $this->back($url, 'お客様のリンクです。コピーして送れます。');
    }

    public function postToKnowledge(Request $request, $id)
    {
        if ($r = $this->gate()) {
            return $r;
        }
        $ticket = Tickets::find($id);
        $table = Its::table(Its::T_KNOWLEDGE);
        if (!isset($ticket, $table)) {
            return $this->back(Its::url(), 'チケットが見つかりません。', true);
        }
        // The last answer to the customer is usually the solution.
        $answer = Tickets::messages($ticket)->filter(function ($m) {
            return array_get($m->value, 'kind') === 'reply';
        })->last();
        $body = "【困っていたこと】\n" . trim(strval(array_get($ticket->value, 'description'))) . "\n\n【解決のしかた】\n"
            . trim(strval(isset($answer) ? array_get($answer->value, 'body') : ''));
        $row = $table->getValueModel();
        $row->setValue([
            'title' => strval(array_get($ticket->value, 'subject')),
            'body' => $body,
            'category' => array_get($ticket->value, 'category'),
            'keywords' => strval(array_get($ticket->value, 'tags')),
            'public' => '0',
            'source_ticket' => $ticket->id,
        ]);
        $row->save();

        // Exment's own edit screen: rewrite, remove names, then publish.
        return $this->back(Its::dataUrl(Its::T_KNOWLEDGE, $row->id, 'edit'), 'ナレッジの下書きを作りました。お客様の名前などを消してから「お客様に公開」にしてください。');
    }

    public function postMerge(Request $request, $id)
    {
        if ($r = $this->gate()) {
            return $r;
        }
        $ticket = Tickets::find($id);
        $no = strtoupper(trim(strval($request->get('into', ''))));
        if (ctype_digit($no)) {
            $no = 'SUP-' . str_pad($no, 5, '0', STR_PAD_LEFT);
        }
        $into = $no !== '' ? Tickets::findByNo($no) : null;
        if (!isset($ticket, $into) || intval($into->id) === intval($ticket->id)) {
            return $this->back(Its::url('ticket', strval($id)), 'まとめ先のチケット番号が見つかりません。', true);
        }
        Tickets::merge($ticket, $into);

        return $this->back(Its::url('ticket', strval($into->id)), array_get($ticket->value, 'ticket_no') . ' をこのチケットにまとめました。');
    }

    public function postLock(Request $request, $id)
    {
        if (!Its::isInstalled() || !static::isAgent()) {
            return response()->json(['ok' => false], 403);
        }
        if ($request->get('release') === '1') {
            Locks::release(intval($id));

            return response()->json(['ok' => true]);
        }

        return response()->json(array_merge(['ok' => true], Locks::hold(intval($id))));
    }

    public function postBulk(Request $request)
    {
        if ($r = $this->gate()) {
            return $r;
        }
        $ids = array_values(array_filter(array_map('intval', (array) $request->get('ids', []))));
        $action = strval($request->get('action', ''));
        $value = strval($request->get('value', ''));
        $set = null;
        if ($action === 'status' && isset(Its::STATUSES[$value])) {
            $set = ['status' => $value];
        } elseif ($action === 'priority' && isset(Its::PRIORITIES[$value])) {
            $set = ['priority' => $value];
        } elseif ($action === 'assignee') {
            $set = ['assignee' => ctype_digit($value) ? $value : null];
        }
        if (empty($ids) || $set === null) {
            return $this->back(url()->previous(), 'チケットと変える内容を選んでください。', true);
        }
        $n = 0;
        foreach (Tickets::query()->whereIn('id', array_slice($ids, 0, 200))->get() as $t) {
            $t->setValue($set);
            $t->save();
            $n++;
        }

        return $this->back(url()->previous(), "{$n} 件を変えました。");
    }

    // ---- new ticket (phone, walk-in) ---------------------------------------

    public function newTicket(Request $request)
    {
        if ($r = $this->gate()) {
            return $r;
        }

        return $this->view('new', [
            'agents' => Tickets::agentOptions(),
            'categories' => Tickets::options(Its::T_CATEGORY),
            'customers' => Tickets::options(Its::T_CUSTOMER),
            'me' => strval(optional(\Exment::user())->getUserId()),
        ]);
    }

    public function postNewTicket(Request $request)
    {
        if ($r = $this->gate()) {
            return $r;
        }
        $data = $request->validate([
            'subject' => 'required|string|max:250',
            'description' => 'nullable|string|max:20000',
            'requester_name' => 'nullable|string|max:100',
            'requester_email' => 'nullable|email|max:200',
            'channel' => 'required|in:' . implode(',', array_keys(Its::CHANNELS)),
            'priority' => 'required|in:' . implode(',', array_keys(Its::PRIORITIES)),
            'category' => 'nullable|integer',
            'customer' => 'nullable|integer',
            'assignee' => 'nullable|integer',
            'notify' => 'nullable|in:1',
        ], [], ['subject' => '件名', 'requester_email' => 'メールアドレス']);

        $ticket = Tickets::create(array_merge($data, ['status' => 'open']), [
            'body' => strval($data['description'] ?? ''),
            'kind' => $data['channel'] === 'phone' ? 'customer' : 'note',
            'author_name' => $data['requester_name'] ?? '',
            'author_email' => $data['requester_email'] ?? '',
            'via' => $data['channel'],
        ]);
        if (!empty($data['notify']) && !empty($data['requester_email'])) {
            Inbox::acknowledge($ticket);
        }

        return $this->back(Its::url('ticket', strval($ticket->id)), array_get($ticket->value, 'ticket_no') . ' を作りました。');
    }

    // ---- knowledge for the reply box ---------------------------------------

    public function knowledgeSearch(Request $request)
    {
        if (!Its::isInstalled() || !static::isAgent()) {
            return response()->json(['items' => []], 403);
        }
        $items = array_map(function ($r) {
            $row = Knowledge::find($r['id'], false);

            return [
                'id' => $r['id'], 'title' => $r['title'], 'snippet' => $r['snippet'], 'public' => $r['public'],
                'body' => isset($row) ? strval(array_get($row->value, 'body')) : '',
                'url' => $r['public'] ? Its::publicUrl('faq', strval($r['id'])) : null,
                'edit' => Its::dataUrl(Its::T_KNOWLEDGE, $r['id'], 'edit'),
            ];
        }, Knowledge::search(mb_substr(strval($request->get('q', '')), 0, 200), false, 6));

        return response()->json(['items' => $items]);
    }

    // ---- the customer's own list (logged in) -------------------------------

    /**
     * For a customer who logs in (P3). Which tickets: those of the contact
     * rows linked to this user, those sent from this user's address, and -
     * for a customer administrator - all of the company's.
     */
    public function myTickets(Request $request)
    {
        if (!Its::isInstalled()) {
            return $this->view('denied');
        }
        $user = \Exment::user();
        $email = Its::normalizeEmail(strval(optional($user->base_user)->getValue('email')));
        $contacts = Its::table(Its::T_CONTACT)->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where(function ($w) use ($user, $email) {
                $w->where('value->user', strval($user->getUserId()));
                if ($email !== '') {
                    $w->orWhere('value->email', $email);
                }
            })->get();

        $adminOf = [];
        $contactIds = [];
        foreach ($contacts as $c) {
            $contactIds[] = strval($c->id);
            if (boolval(array_get($c->value, 'is_customer_admin')) && !empty($cid = array_get($c->value, 'customer'))) {
                $adminOf[] = strval($cid);
            }
        }
        $q = Tickets::query()->where('value->customer_visible', '1')->where(function ($w) use ($email, $contactIds, $adminOf) {
            $w->whereRaw('1 = 0');
            if ($email !== '') {
                $w->orWhere('value->requester_email', $email);
            }
            if (!empty($contactIds)) {
                $w->orWhereIn('value->contact', $contactIds);
            }
            if (!empty($adminOf)) {
                $w->orWhereIn('value->customer', $adminOf);
            }
        });
        $tickets = $q->orderByDesc('id')->limit(200)->get();
        $links = [];
        foreach ($tickets as $t) {
            $links[$t->id] = Tokens::linkFor(intval($t->id), $email !== '' ? $email : null);
        }

        return $this->view('my', [
            'tickets' => $tickets,
            'links' => $links,
            'isCompanyAdmin' => !empty($adminOf),
            'requestUrl' => Its::publicUrl('request') . '?' . http_build_query(['name' => strval($user->user_name), 'email' => $email]),
            'isAgent' => static::isAgent(),
        ]);
    }

    // ---- report -------------------------------------------------------------

    public function dashboard(Request $request)
    {
        if ($r = $this->gate()) {
            return $r;
        }
        $month = preg_match('/^\d{4}-\d{2}$/', strval($request->get('month'))) ? strval($request->get('month')) : Carbon::now()->format('Y-m');

        return $this->view('dashboard', array_merge(Report::build($month), ['month' => $month]));
    }

    public function reportCsv(Request $request)
    {
        if ($r = $this->gate()) {
            return $r;
        }
        $month = preg_match('/^\d{4}-\d{2}$/', strval($request->get('month'))) ? strval($request->get('month')) : Carbon::now()->format('Y-m');
        $report = Report::build($month);
        $rows = [['お客様', 'チケット', '解決', '作業時間（時間）', '契約時間（時間）', 'SLA 達成率', '満足度']];
        foreach ($report['byCustomer'] as $c) {
            $rows[] = [$c['name'], $c['count'], $c['resolved'], round($c['minutes'] / 60, 1), $c['contract'] ?? '', $c['sla'] === null ? '' : $c['sla'] . '%', $c['csat'] ?? ''];
        }
        $csv = "\xEF\xBB\xBF" . implode("\r\n", array_map(function ($r) {
            return implode(',', array_map(function ($v) {
                return '"' . str_replace('"', '""', strval($v)) . '"';
            }, $r));
        }, $rows));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="it-support-' . $month . '.csv"',
        ]);
    }

    // ---- settings / setup ---------------------------------------------------

    public function settings()
    {
        if ($r = $this->gate(true)) {
            return $r;
        }

        return $this->view('settings', [
            's' => Its::settings(),
            'state' => Its::state(),
            'publicHome' => Its::publicUrl(),
            'intakeAlert' => Its::publicUrl('intake', 'alert'),
            'intakeMail' => Its::publicUrl('intake', 'mail'),
            'mailFrom' => Mailer::fromAddress(),
        ]);
    }

    public function postSettings(Request $request)
    {
        if ($r = $this->gate(true)) {
            return $r;
        }
        $values = [];
        foreach (array_keys(Its::DEFAULTS) as $key) {
            if ($key === 'intake_key') {
                continue;
            }
            if ($key === 'imap_password') {
                // Empty means "keep the one saved".
                if (strval($request->get($key, '')) !== '') {
                    $values[$key] = strval($request->get($key));
                }
                continue;
            }
            if ($request->has($key)) {
                $values[$key] = $request->get($key);
            } elseif (in_array($key, ['imap_enabled', 'csat_enabled', 'public_enabled'], true)) {
                $values[$key] = '0';
            }
        }
        if (isset($values['business_days']) && is_array($request->get('business_days'))) {
            $values['business_days'] = implode(',', array_map('intval', $request->get('business_days')));
        }
        Its::saveSettings($values);

        return $this->back(Its::url('settings'), '設定を保存しました。');
    }

    public function postMailTest(Request $request)
    {
        if ($r = $this->gate(true)) {
            return $r;
        }
        $client = new Mail\ImapClient(15);
        try {
            $client->connect(strval(Its::setting('imap_host')), intval(Its::setting('imap_port', 993)), strval(Its::setting('imap_security', 'ssl')));
            $client->login(strval(Its::setting('imap_user')), strval(Its::setting('imap_password')));
            $box = $client->select(strval(Its::setting('imap_folder', 'INBOX')) ?: 'INBOX');
            $unseen = count($client->unseenUids(0));
            $client->logout();

            return $this->back(Its::url('settings'), "つながりました。メールが {$box['exists']} 通、未読が {$unseen} 通あります。");
        } catch (Mail\MailException $e) {
            return $this->back(Its::url('settings'), $e->getMessage(), true);
        } catch (\Throwable $e) {
            \Log::warning('ExmentITSupport mail test failed: ' . $e->getMessage());

            return $this->back(Its::url('settings'), 'つながりませんでした。', true);
        }
    }

    public function postFetchNow(Request $request)
    {
        if ($r = $this->gate(true)) {
            return $r;
        }
        $result = Inbox::fetch();

        return $this->back(Its::url('settings'), $result['message'], !$result['ok']);
    }

    public function postIntakeKey(Request $request)
    {
        if ($r = $this->gate(true)) {
            return $r;
        }
        Its::saveSettings(['intake_key' => bin2hex(random_bytes(24))]);

        return $this->back(Its::url('settings'), '新しいキーを作りました。監視の道具の設定も変えてください。');
    }

    public function setup()
    {
        if (!static::isAdmin()) {
            return $this->view('denied');
        }

        return $this->view('setup', ['installed' => Its::isInstalled(), 'state' => Its::state()]);
    }

    public function postSetup(Request $request)
    {
        if (!static::isAdmin()) {
            return $this->view('denied');
        }
        $result = Schema::install($request->get('seed', '1') === '1');
        session()->flash('its_setup_steps', $result['steps'] ?? []);

        return $this->back($result['ok'] ? Its::url() : Its::url('setup'), $result['message'], !$result['ok']);
    }
}

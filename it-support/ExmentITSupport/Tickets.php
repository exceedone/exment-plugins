<?php

namespace App\Plugins\ExmentITSupport;

use Carbon\Carbon;
use Exceedone\Exment\Enums\FileType;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\File as ExmentFile;
use Illuminate\Support\Facades\DB;

/**
 * What happens to a ticket, whichever door it came in by.
 *
 * Mail, the public form, the portal, the monitor API and an agent typing in a
 * phone call all end up in create(); every answer in reply() or
 * customerWrote(). The rules - who the customer is, who gets the ticket,
 * when the SLA clock starts and stops - live in one place, and the event
 * plugin applies the same rules when someone edits a ticket on Exment's own
 * data screen.
 */
class Tickets
{
    /** On a ticket whose requester's address nobody has shown to be theirs yet (the public form). */
    const TAG_UNVERIFIED = 'メール未確認';

    public static function table(): CustomTable
    {
        $table = Its::table(Its::T_TICKET);
        if (!isset($table)) {
            throw new \RuntimeException('サポート窓口のセットアップがまだです。');
        }

        return $table;
    }

    public static function query()
    {
        return static::table()->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class);
    }

    public static function find($id): ?CustomValue
    {
        if (!is_numeric($id)) {
            return null;
        }

        return static::query()->find(intval($id));
    }

    public static function findByNo(string $no): ?CustomValue
    {
        return static::query()->where('value->ticket_no', $no)->first();
    }

    // ---- create ----------------------------------------------------------

    /**
     * @param array<string, mixed> $values ticket columns
     * @param array<string, mixed> $first first message: body, kind, author_name, author_email, via, message_id
     * @param array<int, array{name: string, content: string}> $files
     * @param bool $makeContact false when the address is not known to be the sender's (the
     *   public form, a mail that did not pass the sender checks): no contact is made from it
     */
    public static function create(array $values, array $first = [], array $files = [], bool $makeContact = true): CustomValue
    {
        $values = array_merge([
            'status' => 'new',
            'priority' => 'normal',
            'channel' => 'staff',
            'customer_visible' => '1',
            'require_otp' => '0',
            'time_spent' => 0,
            'sla_state' => 'none',
        ], array_filter($values, function ($v) {
            return $v !== null && $v !== '';
        }));

        $email = Its::normalizeEmail($values['requester_email'] ?? '');
        if ($email !== '') {
            $values['requester_email'] = $email;
            [$customer, $contact] = static::identify($email, strval($values['requester_name'] ?? ''), !empty($values['customer']) ? $values['customer'] : null, $makeContact);
            if (isset($customer) && empty($values['customer'])) {
                $values['customer'] = $customer->id;
            }
            if (isset($contact) && empty($values['contact'])) {
                $values['contact'] = $contact->id;
                if (empty($values['requester_name'])) {
                    $values['requester_name'] = strval(array_get($contact->value, 'name'));
                }
            }
        }

        if (in_array($values['channel'], ['mail', 'form', 'portal', 'phone'], true)) {
            $values['last_customer_at'] = Carbon::now()->format('Y-m-d H:i:s');
        }

        $ticket = static::table()->getValueModel();
        $ticket->setValue($values);
        // Rules (assignee, SLA) run in the saving event - see PluginEvent.
        $ticket->save();
        $ticket = static::find($ticket->id) ?? $ticket;

        if (!empty($first['body']) || !empty($files)) {
            static::addMessage($ticket, array_merge([
                'kind' => in_array($values['channel'], ['monitor', 'staff', 'asset'], true) ? 'note' : 'customer',
                'author_name' => $values['requester_name'] ?? '',
                'author_email' => $email,
                'via' => $values['channel'],
            ], $first), $files);
        }
        if (!empty($first['message_id'])) {
            Mailer::remember(strval($first['message_id']), intval($ticket->id), 'in');
        }

        return $ticket;
    }

    /**
     * Customer and contact for an address: the contact registered with it,
     * else the customer whose mail domain it is (a contact is then made, so
     * the next ticket from the same person is linked without a lookup).
     *
     * @return array{0: ?CustomValue, 1: ?CustomValue}
     */
    public static function identify(string $email, string $name = '', $customerId = null, bool $makeContact = true): array
    {
        $email = Its::normalizeEmail($email);
        $contacts = Its::table(Its::T_CONTACT);
        $customers = Its::table(Its::T_CUSTOMER);
        if (!isset($contacts, $customers) || !Its::isEmail($email)) {
            return [null, null];
        }

        $contact = $contacts->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->where('value->email', $email)->first();
        if (isset($contact)) {
            $cid = array_get($contact->value, 'customer');
            $customer = $cid ? $customers->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($cid) : null;

            return [$customer, $contact];
        }

        $customer = $customerId ? $customers->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($customerId) : static::customerByDomain($email);
        if (!isset($customer)) {
            return [null, null];
        }
        if (!$makeContact) {
            return [$customer, null];
        }

        $contact = $contacts->getValueModel();
        $contact->setValue([
            'name' => $name !== '' ? $name : strstr($email, '@', true),
            'email' => $email,
            'customer' => $customer->id,
        ]);
        $contact->save();

        return [$customer, $contact];
    }

    /**
     * The requester showed the address is theirs (sent something through the
     * link mailed to it, or entered the code sent to it): the "not verified"
     * tag goes, and the contact is made as for any other ticket.
     */
    public static function markAddressVerified(CustomValue $ticket): void
    {
        $tags = array_values(array_filter(array_map('trim', explode(',', strval(array_get($ticket->value, 'tags'))))));
        if (!in_array(static::TAG_UNVERIFIED, $tags, true)) {
            return;
        }
        $values = ['tags' => implode(', ', array_values(array_diff($tags, [static::TAG_UNVERIFIED])))];
        $email = strval(array_get($ticket->value, 'requester_email'));
        if ($email !== '' && empty(array_get($ticket->value, 'contact'))) {
            [, $contact] = static::identify($email, strval(array_get($ticket->value, 'requester_name')), array_get($ticket->value, 'customer') ?: null);
            if (isset($contact)) {
                $values['contact'] = $contact->id;
            }
        }
        $ticket->setValue($values);
        $ticket->save();
        static::addMessage($ticket, ['kind' => 'system', 'body' => 'メールで送ったリンク（またはコード）から操作がありました。このアドレスにメールが届くことは確かめられました（依頼の中身を書いた人が本人かどうかまでは分かりません）。', 'via' => 'portal']);
    }

    /** Add a tag to the ticket (once). */
    public static function addTag(CustomValue $ticket, string $tag): void
    {
        $tags = array_values(array_filter(array_map('trim', explode(',', strval(array_get($ticket->value, 'tags'))))));
        if (in_array($tag, $tags, true)) {
            return;
        }
        $tags[] = $tag;
        $ticket->setValue(['tags' => mb_substr(implode(', ', $tags), 0, 200)]);
        $ticket->save();
    }

    public static function customerByDomain(string $email): ?CustomValue
    {
        $domain = mb_strtolower(substr(strrchr($email, '@') ?: '', 1));
        if ($domain === '') {
            return null;
        }
        $customers = Its::table(Its::T_CUSTOMER);
        if (!isset($customers)) {
            return null;
        }
        // Few customers per install; a scan is simpler than a JSON search on
        // a multi-line field, and exact on sub-domains.
        foreach ($customers->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->cursor() as $customer) {
            foreach (preg_split('/[\s,]+/', mb_strtolower(strval(array_get($customer->value, 'email_domains')))) as $d) {
                $d = ltrim(trim($d), '@');
                if ($d !== '' && ($domain === $d || str_ends_with($domain, '.' . $d))) {
                    return $customer;
                }
            }
        }

        return null;
    }

    // ---- messages --------------------------------------------------------

    /**
     * @param array<string, mixed> $values message columns
     * @param array<int, array{name: string, content: string}> $files
     */
    public static function addMessage(CustomValue $ticket, array $values, array $files = []): CustomValue
    {
        $names = [];
        $uuids = [];
        foreach ($files as $file) {
            $saved = static::attach($ticket, strval($file['name']), strval($file['content']));
            if ($saved !== null) {
                $names[] = $saved['name'];
                $uuids[] = $saved['uuid'];
            }
        }

        $table = Its::table(Its::T_MESSAGE);
        $message = $table->getValueModel();
        $message->parent_type = Its::T_TICKET;
        $message->parent_id = $ticket->id;
        $message->setValue(array_merge([
            'kind' => 'note',
            'mail_status' => 'none',
            'time_spent' => 0,
        ], array_filter($values, function ($v) {
            return $v !== null;
        }), empty($names) ? [] : ['attachments' => implode(', ', $names), 'attachment_uuids' => implode(',', $uuids)]));
        $message->save();

        return $message;
    }

    /**
     * Store a file as a document of the ticket (Exment's attachment box on
     * the data screen), so it is backed up and permission-checked like any
     * other Exment file.
     *
     * @return array{name: string, uuid: string}|null
     */
    public static function attach(CustomValue $ticket, string $name, string $content): ?array
    {
        if ($content === '' || strlen($content) > 25 * 1024 * 1024) {
            return null;
        }
        $name = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]/u', '_', $name) ?? '') ?: 'file';
        $table = static::table();
        $file = ExmentFile::storeAs(FileType::CUSTOM_VALUE_DOCUMENT, $content, $table->table_name, $name)
            ->saveCustomValue($ticket->id, null, $table);
        $file->saveDocumentModel($ticket, $name);

        return ['name' => $name, 'uuid' => strval($file->uuid)];
    }

    /**
     * @return \Illuminate\Support\Collection<int, CustomValue>
     */
    public static function messages(CustomValue $ticket, bool $customerOnly = false)
    {
        $table = Its::table(Its::T_MESSAGE);
        $q = $table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where('parent_type', Its::T_TICKET)->where('parent_id', $ticket->id)->orderBy('id');
        if ($customerOnly) {
            $q->whereIn('value->kind', ['customer', 'reply']);
        }

        return $q->get();
    }

    /**
     * The ticket's files. For the customer pages ($customerOnly) only those
     * that came with a message the customer can see - their own or our
     * reply - so a file attached to an internal note, or added on Exment's
     * data screen, never reaches the customer (ISS00411).
     *
     * @return array<int, object> document rows: uuid, name, created_at
     */
    public static function files(CustomValue $ticket, bool $customerOnly = false): array
    {
        $allowed = null;
        if ($customerOnly) {
            $allowed = [];
            foreach (static::messages($ticket, true) as $m) {
                foreach (explode(',', strval(array_get($m->value, 'attachment_uuids'))) as $uuid) {
                    if (($uuid = trim($uuid)) !== '') {
                        $allowed[$uuid] = true;
                    }
                }
            }
        }
        $docs = CustomTable::getEloquent(SystemTableName::DOCUMENT);
        if (!isset($docs)) {
            return [];
        }
        $out = [];
        foreach ($docs->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where('parent_type', Its::T_TICKET)->where('parent_id', $ticket->id)->orderBy('id')->get() as $doc) {
            $uuid = strval(array_get($doc->value, 'file_uuid'));
            if ($allowed !== null && !isset($allowed[$uuid])) {
                continue;
            }
            $out[] = (object) [
                'uuid' => $uuid,
                'name' => strval(array_get($doc->value, 'document_name')),
                'created_at' => strval($doc->created_at),
            ];
        }

        return $out;
    }

    // ---- answers ---------------------------------------------------------

    /**
     * An agent answers. A public reply is mailed to the requester (and CC);
     * a note stays inside.
     *
     * @param array{body: string, kind?: string, status?: string, time_spent?: int, cc?: string} $input
     * @param array<int, array{name: string, content: string}> $files
     * @return array{message: CustomValue, mailed: ?bool}
     */
    public static function reply(CustomValue $ticket, array $input, array $files = []): array
    {
        $user = \Exment::user();
        $kind = ($input['kind'] ?? 'reply') === 'note' ? 'note' : 'reply';
        $body = trim(strval($input['body'] ?? ''));
        $minutes = max(0, intval($input['time_spent'] ?? 0));
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $message = static::addMessage($ticket, [
            'kind' => $kind,
            'body' => $body,
            'author_name' => isset($user) ? strval($user->user_name) : '',
            'author_email' => isset($user) ? strval(optional($user->base_user)->getValue('email')) : '',
            'via' => 'staff',
            'time_spent' => $minutes,
        ], $files);

        $mailed = null;
        $values = [];
        if ($kind === 'reply') {
            $values['last_agent_at'] = $now;
            if (empty(array_get($ticket->value, 'first_responded_at'))) {
                $values['first_responded_at'] = $now;
            }
            $to = strval(array_get($ticket->value, 'requester_email'));
            if (Its::isEmail($to)) {
                $cc = array_filter(array_map('trim', explode(',', strval($input['cc'] ?? array_get($ticket->value, 'cc', '')))));
                $link = boolval(array_get($ticket->value, 'customer_visible', '1')) && Its::portalOn()
                    ? Tokens::linkFor(intval($ticket->id), $to) : null;
                $id = Mailer::send($ticket, [$to], Mailer::subjectFor($ticket), Mailer::customerBody($ticket, $body, $link), $cc);
                $mailed = $id !== null;
                $message->setValue(['mail_status' => $mailed ? 'sent' : 'failed', 'message_id' => $id]);
                $message->save();
            }
        }

        if ($minutes > 0) {
            $values['time_spent'] = intval(array_get($ticket->value, 'time_spent', 0)) + $minutes;
        }
        $status = strval($input['status'] ?? '');
        if ($status !== '' && isset(Its::STATUSES[$status])) {
            $values['status'] = $status;
        } elseif ($kind === 'reply' && array_get($ticket->value, 'status') === 'new') {
            $values['status'] = 'open';
        }
        if (empty(array_get($ticket->value, 'assignee')) && isset($user)) {
            $values['assignee'] = $user->getUserId();
        }
        if (!empty($values)) {
            $ticket->setValue($values);
            $ticket->save();
        }
        Locks::release(intval($ticket->id));

        return ['message' => $message, 'mailed' => $mailed];
    }

    /**
     * The customer wrote (mail reply, link page, portal). Reopens a solved
     * ticket; a ticket closed long ago gets a new ticket instead, pointing
     * back at it, so the old one's history and report stay as they were.
     *
     * @param array<int, array{name: string, content: string}> $files
     */
    public static function customerWrote(CustomValue $ticket, string $body, string $via, string $email, string $name = '', ?string $messageId = null, array $files = []): CustomValue
    {
        $status = strval(array_get($ticket->value, 'status'));
        // A merged ticket is off every agent queue, so writing into it would
        // reach nobody: that goes to a new ticket too (ISS00411).
        $merged = !empty(array_get($ticket->value, 'merged_into'));
        if ($status === 'closed' || $merged) {
            $closedAt = array_get($ticket->value, 'closed_at');
            if ($merged || empty($closedAt) || Carbon::parse($closedAt)->diffInDays(Carbon::now()) > 14) {
                $new = static::create([
                    'subject' => strval(array_get($ticket->value, 'subject')),
                    'description' => '前のチケット ' . array_get($ticket->value, 'ticket_no') . ' の続きです。',
                    'channel' => $via,
                    'priority' => array_get($ticket->value, 'priority'),
                    'category' => array_get($ticket->value, 'category'),
                    'customer' => array_get($ticket->value, 'customer'),
                    'requester_email' => $email ?: array_get($ticket->value, 'requester_email'),
                    'requester_name' => $name ?: array_get($ticket->value, 'requester_name'),
                    'assignee' => array_get($ticket->value, 'assignee'),
                    'customer_visible' => array_get($ticket->value, 'customer_visible', '1'),
                    'require_otp' => array_get($ticket->value, 'require_otp', '0'),
                ], ['body' => $body, 'message_id' => $messageId], $files);

                return $new;
            }
        }

        if ($name === '' && Its::normalizeEmail($email) === Its::normalizeEmail(strval(array_get($ticket->value, 'requester_email')))) {
            $name = strval(array_get($ticket->value, 'requester_name'));
        }
        static::addMessage($ticket, [
            'kind' => 'customer',
            'body' => $body,
            'author_name' => $name,
            'author_email' => $email,
            'via' => $via,
            'message_id' => $messageId,
        ], $files);
        if (!empty($messageId)) {
            Mailer::remember($messageId, intval($ticket->id), 'in');
        }

        $values = ['last_customer_at' => Carbon::now()->format('Y-m-d H:i:s'), 'reminded_at' => null];
        if (in_array($status, ['pending', 'resolved', 'closed'], true)) {
            $values['status'] = 'open';
        }
        $ticket->setValue($values);
        $ticket->save();

        return $ticket;
    }

    /**
     * Fold a duplicate into another ticket: its conversation is copied over
     * as a note, and the duplicate is closed pointing at the survivor.
     */
    public static function merge(CustomValue $from, CustomValue $into): void
    {
        $lines = ['（' . array_get($from->value, 'ticket_no') . ' をこのチケットにまとめました）', '', strval(array_get($from->value, 'description'))];
        foreach (static::messages($from) as $m) {
            $lines[] = '';
            $lines[] = '— ' . Its::label(Its::MESSAGE_KINDS, array_get($m->value, 'kind')) . ' / ' . array_get($m->value, 'author_name') . ' / ' . $m->created_at;
            $lines[] = strval(array_get($m->value, 'body'));
        }
        static::addMessage($into, ['kind' => 'note', 'body' => implode("\n", $lines), 'via' => 'staff']);

        DB::table('its_mail_index')->where('ticket_id', $from->id)->update(['ticket_id' => $into->id]);

        $from->setValue(['status' => 'closed', 'merged_into' => $into->id]);
        $from->save();
    }

    // ---- rules (called from the saving event) ---------------------------

    /**
     * Fill in what follows from the rest: assignee, due times, the moments a
     * status was reached, the SLA pause. Only sets values; never saves.
     */
    /**
     * The 受付箱 of a ticket by its category: kind, group, stages, SLA, fields (ISS02941). One
     * read per box and request.
     *
     * @return array{id: ?int, name: string, kind: string, group: string, stages: array, sla: mixed, fields: array}
     */
    public static function boxOf($categoryId): array
    {
        static $cache = [];
        $key = strval($categoryId ?? '');
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $row = null;
        if ($key !== '' && ctype_digit($key) && ($table = Its::table(Its::T_CATEGORY)) !== null) {
            $row = $table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find(intval($key));
        }

        return $cache[$key] = static::boxFromRow($row);
    }

    /** @return array{id: ?int, name: string, kind: string, group: string, stages: array, sla: mixed, fields: array} */
    public static function boxFromRow($row): array
    {
        $v = isset($row) ? (array) $row->value : [];
        $kind = Queue::kindOf($v['kind'] ?? null);

        return [
            'id' => isset($row) ? intval($row->id) : null,
            'name' => strval($v['name'] ?? ''),
            'kind' => $kind,
            'group' => Queue::groupOf($kind),
            'stages' => Queue::stages($kind, $v['flow'] ?? ''),
            'sla' => $v['sla'] ?? null,
            'fields' => Queue::fields($v['fields'] ?? ''),
        ];
    }

    /**
     * Every box, for the desk.
     *
     * @return array<string, array>
     */
    public static function boxes(): array
    {
        $table = Its::table(Its::T_CATEGORY);
        if (!isset($table)) {
            return [];
        }
        $out = [];
        foreach ($table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->orderBy('id')->limit(1000)->get() as $row) {
            $out[strval($row->id)] = static::boxFromRow($row);
        }

        return $out;
    }

    /**
     * The stage leads: a stage picked sets the status; a status set otherwise (a reply, the
     * automation, the customer) moves the stage. The progress follows the stage.
     */
    public static function applyStage(CustomValue $ticket, array $original, bool $isNew): void
    {
        $v = (array) $ticket->value;
        $stages = static::boxOf($v['category'] ?? null)['stages'];
        if (empty($stages)) {
            return;
        }
        $status = strval($v['status'] ?? 'new') ?: 'new';
        $stage = strval($v['stage'] ?? '');
        $boxChanged = !$isNew && strval($original['category'] ?? '') !== strval($v['category'] ?? '');
        $picked = !$boxChanged && $stage !== '' && $stage !== ($isNew ? '' : strval($original['stage'] ?? ''))
            ? Queue::statusOf($stages, $stage) : null;
        if ($picked !== null) {
            $status = $picked;
        } else {
            $stage = Queue::stageFor($stages, $status, $boxChanged ? '' : $stage);
        }
        $ticket->setValue(['status' => $status, 'stage' => $stage, 'progress' => Queue::progress($stages, $stage)]);
    }

    public static function applyRules(CustomValue $ticket): void
    {
        $v = (array) $ticket->value;
        $original = (array) ($ticket->getOriginal('value') ?? []);
        if (is_string($ticket->getOriginal('value'))) {
            $original = (array) json_decode($ticket->getOriginal('value'), true);
        }
        $isNew = !$ticket->exists;
        $now = Carbon::now()->format('Y-m-d H:i:s');
        static::applyStage($ticket, $original, $isNew);
        $v = (array) $ticket->value;
        $status = strval($v['status'] ?? 'new') ?: 'new';
        $before = $isNew ? null : strval($original['status'] ?? '');
        $set = [];

        if (empty($v['assignee']) && Its::setting('auto_assign', 'category') !== 'none') {
            $assignee = static::pickAssignee($ticket);
            if ($assignee !== null) {
                $set['assignee'] = $assignee;
            }
        }

        if ($status !== $before) {
            if ($status === 'resolved' && empty($v['resolved_at'])) {
                $set['resolved_at'] = $now;
            }
            if ($status === 'closed') {
                if (empty($v['closed_at'])) {
                    $set['closed_at'] = $now;
                }
                if (empty($v['resolved_at'])) {
                    $set['resolved_at'] = $now;
                }
            }
            if (in_array($status, Its::ACTIVE_STATUSES, true) && in_array($before, ['resolved', 'closed'], true)) {
                $set['resolved_at'] = null;
                $set['closed_at'] = null;
            }
            if (in_array($status, Its::PAUSED_STATUSES, true) && empty($v['sla_paused_at'])) {
                $set['sla_paused_at'] = $now;
            }
        }
        if (!empty($set)) {
            $ticket->setValue($set);
        }

        // Leaving the pause moves the resolve due time on.
        if (!in_array($status, Its::PAUSED_STATUSES, true) && !empty(array_get($ticket->value, 'sla_paused_at'))) {
            Sla::resume($ticket);
        }

        // A priority change re-plans the clock only while nothing was met.
        if (!$isNew && strval($original['priority'] ?? '') !== strval($v['priority'] ?? '') && empty($v['first_responded_at'])) {
            $ticket->setValue(['first_response_due' => null, 'resolve_due' => null]);
        }
        Sla::applyDue($ticket);
        $ticket->setValue(['sla_state' => Sla::state($ticket)]);

        // a new due time (another priority, the clock moved on): the escalation starts again
        if (!$isNew && (strval($original['first_response_due'] ?? '') !== strval(array_get($ticket->value, 'first_response_due') ?? '')
            || strval($original['resolve_due'] ?? '') !== strval(array_get($ticket->value, 'resolve_due') ?? '')
            || (empty($original['first_responded_at']) && !empty(array_get($ticket->value, 'first_responded_at'))))) {
            $ticket->setValue(['sla_escalation' => 0]);
        }
    }

    /**
     * The category's default person, or - in round robin mode - the agent
     * with the fewest open tickets among those who answered one before.
     */
    public static function pickAssignee(CustomValue $ticket): ?string
    {
        $categoryId = array_get($ticket->value, 'category');
        if (!empty($categoryId) && ($categories = Its::table(Its::T_CATEGORY)) !== null) {
            $category = $categories->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($categoryId);
            $user = isset($category) ? array_get($category->value, 'default_assignee') : null;
            if (!empty($user)) {
                return strval($user);
            }
        }

        if (Its::setting('auto_assign', 'category') !== 'round_robin') {
            return null;
        }

        $agents = static::agentIds();
        if (empty($agents)) {
            return null;
        }
        $load = array_fill_keys($agents, 0);
        foreach (static::query()->whereIn('value->status', Its::ACTIVE_STATUSES)->get(['id', 'value']) as $t) {
            $a = strval(array_get($t->value, 'assignee'));
            if (isset($load[$a])) {
                $load[$a]++;
            }
        }
        asort($load);

        return strval(array_key_first($load));
    }

    /**
     * Users who can answer tickets: members of the agent role group, by user
     * or through an organization.
     *
     * @return array<int, string>
     */
    public static function agentIds(): array
    {
        $group = \Exceedone\Exment\Model\RoleGroup::where('role_group_name', Its::ROLE_AGENT)->first();
        if (!isset($group)) {
            return [];
        }
        $ids = [];
        foreach (\Exceedone\Exment\Model\RoleGroupUserOrganization::where('role_group_id', $group->id)->get() as $row) {
            if ($row->role_group_user_org_type === SystemTableName::USER) {
                $ids[] = strval($row->role_group_target_id);
            } elseif ($row->role_group_user_org_type === SystemTableName::ORGANIZATION) {
                $pivot = \Exceedone\Exment\Model\CustomRelation::getRelationNamebyTables(SystemTableName::ORGANIZATION, SystemTableName::USER);
                if (hasTable($pivot)) {
                    foreach (DB::table($pivot)->where('parent_id', $row->role_group_target_id)->pluck('child_id') as $uid) {
                        $ids[] = strval($uid);
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, string> user id => name, for the agent pickers
     */
    /**
     * The assignees of a ticket: one user as the plugin makes the column, or several when a site
     * made it a column of many users.
     *
     * @return array<int, string>
     */
    public static function assigneeIds(CustomValue $ticket): array
    {
        $value = array_get($ticket->value, 'assignee');
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $value = json_decode($value, true);
        }
        $list = is_array($value) ? $value : (is_null($value) || $value === '' ? [] : explode(',', strval($value)));

        return array_values(array_unique(array_filter(array_map(function ($id) {
            return is_scalar($id) ? trim(strval($id)) : '';
        }, $list), function ($id) {
            return $id !== '' && ctype_digit($id);
        })));
    }

    public static function agentOptions(): array
    {
        $users = CustomTable::getEloquent(SystemTableName::USER);
        $ids = static::agentIds();
        $q = $users->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class);
        if (!empty($ids)) {
            $q->whereIn('id', $ids);
        }
        $out = [];
        foreach ($q->limit(500)->get() as $u) {
            $out[strval($u->id)] = strval(array_get($u->value, 'user_name'));
        }
        asort($out);

        return $out;
    }

    // ---- helpers for the screens ----------------------------------------

    /**
     * Labels for select_table values, one query per table.
     *
     * @param array<int, CustomValue> $tickets
     * @return array<string, array<string, string>> [column => [id => label]]
     */
    public static function labels($tickets): array
    {
        $map = [
            'customer' => [Its::T_CUSTOMER, 'name'],
            'contact' => [Its::T_CONTACT, 'name'],
            'category' => [Its::T_CATEGORY, 'name'],
            'asset' => [Its::T_ASSET, 'name'],
        ];
        $out = ['assignee' => []];
        foreach ($map as $column => [$table_name, $label]) {
            $ids = [];
            foreach ($tickets as $t) {
                if (!empty($id = array_get($t->value, $column))) {
                    $ids[] = intval($id);
                }
            }
            $out[$column] = [];
            $table = Its::table($table_name);
            if (!empty($ids) && isset($table)) {
                foreach ($table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->whereIn('id', array_unique($ids))->get() as $row) {
                    $out[$column][strval($row->id)] = strval(array_get($row->value, $label));
                }
            }
        }
        $userIds = [];
        foreach ($tickets as $t) {
            if (!empty($id = array_get($t->value, 'assignee'))) {
                $userIds[] = intval($id);
            }
        }
        if (!empty($userIds)) {
            foreach (CustomTable::getEloquent(SystemTableName::USER)->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->whereIn('id', array_unique($userIds))->get() as $u) {
                $out['assignee'][strval($u->id)] = strval(array_get($u->value, 'user_name'));
            }
        }

        return $out;
    }

    /**
     * Rows of a small master table for a select box.
     *
     * @return array<string, string>
     */
    public static function options(string $table_name, string $label = 'name', ?callable $filter = null): array
    {
        $table = Its::table($table_name);
        if (!isset($table)) {
            return [];
        }
        $out = [];
        foreach ($table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->orderBy('id')->limit(1000)->get() as $row) {
            if ($filter !== null && !$filter($row)) {
                continue;
            }
            $out[strval($row->id)] = strval(array_get($row->value, $label));
        }

        return $out;
    }
}

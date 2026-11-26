<?php

namespace App\Plugins\ExmentITSupport;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;

/**
 * The quote and the trial key of a ticket (ISS02941 step 4).
 *
 * - 見積: a ticket of a quote box makes a quote (見積) of the sales pack (Exment 7.3's
 *   sales_pack, table sales_quote) in one press: its title from the ticket, its customer
 *   when the sales pack knows the requester's email; then it opens for the amounts. The
 *   ticket keeps the quote's id (sales_quote) and shows its title and total.
 * - 試用キー: until ISS00417's licences come, the key and its end are typed on the ticket
 *   (trial_key, trial_until) and go into a reply as {trial_key} and {trial_until}.
 *
 * Both off at first (Feature::SALES_LINK).
 */
class SalesLink
{
    const QUOTE_TABLE = 'sales_quote';
    const CUSTOMER_TABLE = 'sales_customer';

    public static function on(): bool
    {
        return Feature::on(Feature::SALES_LINK);
    }

    public static function quotesAvailable(): bool
    {
        return static::on() && CustomTable::getEloquent(static::QUOTE_TABLE) !== null;
    }

    /** A trial key as it is kept: printable, no spaces at the ends, at most 200. */
    public static function cleanKey($v): string
    {
        $v = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', strval($v ?? '')) ?? '');

        return mb_substr($v, 0, 200);
    }

    public static function cleanDate($v): ?string
    {
        $v = strval($v ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate(intval($m[2]), intval($m[3]), intval($m[1]))) {
            return null;
        }

        return $v;
    }

    /**
     * A reply's placeholders of the trial key.
     */
    public static function fill(string $text, string $key, ?string $until): string
    {
        return str_replace(['{trial_key}', '{trial_until}'], [$key, strval($until ?? '')], $text);
    }

    public static function quoteUrl(int $id): string
    {
        return admin_url('data/' . static::QUOTE_TABLE . '/' . $id);
    }

    /** @return array{url: string, title: string, total: string}|null */
    public static function quoteOf(CustomValue $ticket): ?array
    {
        $id = strval(array_get($ticket->value, 'sales_quote') ?? '');
        $table = CustomTable::getEloquent(static::QUOTE_TABLE);
        if (!ctype_digit($id) || !isset($table)) {
            return null;
        }
        $quote = $table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find(intval($id));
        if (!isset($quote)) {
            return ['url' => '', 'title' => '（消された見積）', 'total' => ''];
        }

        return ['url' => static::quoteUrl(intval($id)), 'title' => strval(array_get($quote->value, 'quote_title')), 'total' => strval($quote->getValue('total', true) ?? '')];
    }

    /** @return array{ok: bool, message: string, url?: string} */
    public static function createQuote(CustomValue $ticket): array
    {
        if (!static::quotesAvailable()) {
            return ['ok' => false, 'message' => '見積を作る機能は、いま使えません（営業のパックの「見積」のテーブルが要ります）。'];
        }
        if (strval(array_get($ticket->value, 'sales_quote') ?? '') !== '') {
            return ['ok' => false, 'message' => 'このチケットの見積は、もうあります。'];
        }
        $table = CustomTable::getEloquent(static::QUOTE_TABLE);
        if (!$table->hasPermission(\Exceedone\Exment\Enums\Permission::AVAILABLE_EDIT_CUSTOM_VALUE)) {
            return ['ok' => false, 'message' => '見積を作る権限がありません。'];
        }
        $no = strval(array_get($ticket->value, 'ticket_no'));
        $values = [
            'quote_title' => mb_substr(strval(array_get($ticket->value, 'subject')), 0, 250),
            'quote_date' => now()->format('Y-m-d'),
            'valid_until' => now()->addDays(30)->format('Y-m-d'),
            'note' => 'サポート窓口のチケット ' . $no . '：' . Its::url('ticket', strval($ticket->id)),
        ];
        $customer = static::customerByEmail(strval(array_get($ticket->value, 'requester_email')));
        if ($customer !== null) {
            $values['customer'] = $customer;
        }
        $quote = $table->getValueModel();
        $quote->setValue($values);
        $quote->save();

        $ticket->setValue(['sales_quote' => strval($quote->id)]);
        $ticket->save();
        $url = static::quoteUrl(intval($quote->id));
        Tickets::addMessage($ticket, ['kind' => 'system', 'body' => '見積を作りました：' . $values['quote_title'] . "\n" . $url, 'via' => 'staff']);

        return ['ok' => true, 'message' => '見積を作りました。金額と明細を入れてください。', 'url' => admin_url('data/' . static::QUOTE_TABLE . '/' . $quote->id . '/edit')];
    }

    /** The sales pack's customer whose email is the requester's, or null. */
    protected static function customerByEmail(string $email): ?string
    {
        $email = Its::normalizeEmail($email);
        $table = CustomTable::getEloquent(static::CUSTOMER_TABLE);
        if ($email === '' || !isset($table)) {
            return null;
        }
        $row = $table->getValueModel()->query()->where('value->email', $email)->first();

        return isset($row) ? strval($row->id) : null;
    }
}

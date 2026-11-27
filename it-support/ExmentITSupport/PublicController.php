<?php

namespace App\Plugins\ExmentITSupport;

use App\Plugins\ExmentITSupport\Alerts\Adapters;
use App\Plugins\ExmentITSupport\Alerts\Rules;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\File as ExmentFile;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * The customer's side, without login. See PublicRoutes for what keeps it
 * safe; the rules every method follows:
 *
 *  - a page shows one ticket (the token's) or one address's list, never more;
 *  - internal notes, other customers and agents' addresses never appear;
 *  - an unknown, expired and revoked token all get the same "not found";
 *  - every action is rate limited per IP and written to the access log.
 */
class PublicController extends Controller
{
    /** Whether resolve() moved from the link's ticket to the one it was merged into. */
    protected bool $followedMerge = false;

    protected function guard(): void
    {
        if (!Its::isInstalled() || !Its::portalOn()) {
            abort(404);
        }
    }

    protected function page(string $view, array $data = [], int $status = 200)
    {
        return response()->view(Its::view('public.' . $view), array_merge([
            'title' => Its::setting('portal_title', 'サポート窓口'),
        ], $data), $status)->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Frame-Options', 'DENY')
            ->header('Cache-Control', 'no-store');
    }

    protected function notFound()
    {
        return $this->page('message', [
            'heading' => 'ページが見つかりません',
            'text' => 'リンクの期限が切れたか、取り消されたか、まちがっています。お手数ですが「自分の依頼を見る」から新しいリンクを受け取ってください。',
            'showMine' => true,
        ], 404);
    }

    protected function tooMany()
    {
        return $this->page('message', ['heading' => 'しばらくお待ちください', 'text' => '短い時間に続けて操作されました。1 分ほどしてからもう一度お試しください。'], 429);
    }

    // ---- home / request -------------------------------------------------

    public function home()
    {
        $this->guard();

        return $this->page('home', [
            'message' => Its::setting('portal_message', ''),
            'popular' => Knowledge::popular(6),
        ]);
    }

    public function requestForm(Request $request)
    {
        $this->guard();
        // the request catalog (Catalog): the menu, or the form of one item; an item
        // not there (or the catalog not used) is not found, as the post says
        $item = $request->has('item') ? Catalog::item($request->get('item')) : null;
        if ($request->has('item') && !isset($item)) {
            return $this->notFound();
        }

        return $this->page('request', [
            'catalog' => Catalog::publicItems(),
            'item' => isset($item) ? ['id' => intval($item->id), 'name' => strval(array_get($item->value, 'name')), 'description' => strval(array_get($item->value, 'description')),
                'approval' => boolval(array_get($item->value, 'needs_approval', '0')), 'fields' => Catalog::fields($item)] : null,
            'categories' => Tickets::options(Its::T_CATEGORY, 'name', function ($row) {
                return boolval(array_get($row->value, 'public', '1'));
            }),
            'descriptions' => Tickets::options(Its::T_CATEGORY, 'description'),
            'old' => [
                'name' => strval($request->get('name', '')),
                'email' => strval($request->get('email', '')),
                'company' => strval($request->get('company', '')),
            ],
        ]);
    }

    public function postRequest(Request $request)
    {
        $this->guard();
        if (!Tokens::allow('request', 5)) {
            return $this->tooMany();
        }
        // A field people do not see; bots fill it in.
        if (trim(strval($request->get('website', ''))) !== '') {
            return $this->page('message', ['heading' => '受け付けました', 'text' => 'ありがとうございました。']);
        }

        // an item of the request catalog: its own fields instead of the subject and the text
        $item = $request->has('item') ? Catalog::item($request->get('item')) : null;
        if ($request->has('item') && !isset($item)) {
            return $this->notFound();
        }
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:200',
            'company' => 'nullable|string|max:200',
            'category' => 'nullable|integer',
            'subject' => isset($item) ? 'nullable|string|max:200' : 'required|string|max:200',
            'body' => isset($item) ? 'nullable|string|max:20000' : 'required|string|max:20000',
            'urgent' => 'nullable|in:1',
            'files.*' => 'file|max:10240',
        ], [], [
            'name' => 'お名前', 'email' => 'メールアドレス', 'subject' => '件名', 'body' => '内容', 'files.*' => '添付ファイル',
        ]);

        $files = [];
        foreach ((array) $request->file('files', []) as $file) {
            if ($file && $file->isValid() && count($files) < 5) {
                $files[] = ['name' => $file->getClientOriginalName(), 'content' => file_get_contents($file->getRealPath())];
            }
        }

        $catalogValues = [];
        if (isset($item)) {
            $answers = (array) $request->input('cf', []);
            $errors = \App\Plugins\ExmentITSupport\Alerts\CatalogSpec::validate(Catalog::fields($item), $answers);
            if (!empty($errors)) {
                throw \Illuminate\Validation\ValidationException::withMessages($errors);
            }
            $catalogValues = Catalog::ticketValues($item, $answers, strval($data['body'] ?? ''));
            $data['subject'] = $catalogValues['subject'];
            $data['body'] = $catalogValues['description'];
        }

        $categories = Tickets::options(Its::T_CATEGORY);
        $body = trim($data['body']) . (!empty($data['company']) ? "\n\n（会社名: " . $data['company'] . '）' : '');
        // Anyone can type any address here. Until its owner opens the link
        // mailed to it, the ticket says so, and no contact is made from it
        // (a contact of a customer may write into that customer's tickets).
        // ...except a logged-in user of the portal asking from their own address
        $user = \Exment::user();
        $own = isset($user) && Its::normalizeEmail(strval(optional($user->base_user)->getValue('email'))) === Its::normalizeEmail($data['email']);
        $values = [
            'tags' => $own ? null : Tickets::TAG_UNVERIFIED,
            'subject' => $data['subject'],
            'description' => $body,
            'channel' => 'form',
            'requester_name' => $data['name'],
            'requester_email' => $data['email'],
            'category' => isset($categories[strval($data['category'] ?? '')]) ? $data['category'] : null,
            'priority' => !empty($data['urgent']) ? 'high' : Inbox::guessPriority($data['subject'] . "\n" . $data['body']),
        ];
        if (!empty($catalogValues)) {
            $values = array_merge($values, array_diff_key($catalogValues, ['subject' => 1, 'description' => 1]), [
                'tags' => implode(', ', array_filter([$values['tags'], $catalogValues['tags']])),
                'priority' => !empty($data['urgent']) ? 'high' : $catalogValues['priority'],
                'category' => $catalogValues['category'] ?? $values['category'],
            ]);
        }
        $ticket = Tickets::create($values, ['body' => $body], $files, $own);
        Catalog::askApproval($ticket);

        // The link goes only to the address, never on this page: else anyone
        // could ask in somebody else's name and read the agents' answers.
        $mailed = Inbox::acknowledge($ticket);

        return $this->page('message', [
            'heading' => '受け付けました',
            'text' => 'チケット番号は ' . array_get($ticket->value, 'ticket_no') . ' です。' . ($mailed
                ? '確認のメールを ' . $data['email'] . ' にお送りしました。進み具合は、メールにあるリンクからいつでも見られます（ログインは要りません）。メールが届かないときは、アドレスをお確かめのうえ、もう一度お送りください。'
                : 'いま確認のメールをお送りできませんでした。担当者から ' . $data['email'] . ' にご連絡します。'),
            'warnPassword' => Its::looksLikePassword($data['body']),
        ]);
    }

    // ---- the approval of a request (Catalog) -----------------------------

    /**
     * The page the approver opens from the mail. Here, not among the plugin's pages: an
     * approver is often no agent of IT Support (a manager), and the plugin's pages ask for its
     * role group. The Exment login is asked for still, and only the approver (or a system
     * administrator) may decide.
     *
     * @return mixed
     */
    protected function approvalTicket(Request $request, $id)
    {
        if (!Catalog::enabled()) {
            return $this->notFound();
        }
        if (\Exment::user() === null) {
            session()->put('url.intended', $request->fullUrl());

            return redirect(admin_url('auth/login'));
        }
        $ticket = Tickets::find($id);
        if (!isset($ticket) || empty(array_get($ticket->value, 'catalog')) || !Catalog::mayDecide($ticket)) {
            return $this->page('message', [
                'heading' => 'この申請は承認できません',
                'text' => '承認できるのは、申請メニューで決めた「承認する人」と、システム管理者だけです。',
            ], 403);
        }

        return $ticket;
    }

    public function approvalPage(Request $request, $id)
    {
        $this->guard();
        $ticket = $this->approvalTicket($request, $id);
        if (!$ticket instanceof CustomValue) {
            return $ticket;
        }

        return $this->page('approval', ['ticket' => $ticket, 'states' => Catalog::APPROVAL_STATES]);
    }

    public function postApproval(Request $request, $id)
    {
        $this->guard();
        if (!Tokens::allow('approval', 20)) {
            return $this->tooMany();
        }
        $ticket = $this->approvalTicket($request, $id);
        if (!$ticket instanceof CustomValue) {
            return $ticket;
        }
        $decision = strval($request->get('decision'));
        if (!in_array($decision, ['approve', 'reject'], true)) {
            return redirect(Its::publicUrl('approval', strval($ticket->id)));
        }
        $approve = $decision === 'approve';
        if (!Catalog::decide($ticket, $approve, strval($request->get('comment', '')))) {
            return $this->page('message', ['heading' => 'この申請は、もう承認待ちではありません', 'text' => 'ほかの人が先に決めたか、取り下げられました。']);
        }

        return $this->page('message', [
            'heading' => $approve ? '承認しました' : '却下しました',
            'text' => $approve ? '担当者が対応を始めます。' : '依頼した人にメールで知らせました。',
        ]);
    }

    // ---- FAQ --------------------------------------------------------------

    public function faq(Request $request)
    {
        $this->guard();
        $q = trim(strval($request->get('q', '')));

        return $this->page('faq', [
            'q' => $q,
            'results' => $q !== '' ? Knowledge::search($q, true, 20) : [],
            'popular' => $q === '' ? Knowledge::popular(20) : [],
        ]);
    }

    public function faqSearch(Request $request)
    {
        $this->guard();
        if (!Tokens::allow('faq', 60)) {
            return response()->json(['items' => []], 429);
        }
        $items = array_map(function ($r) {
            return ['title' => $r['title'], 'snippet' => $r['snippet'], 'url' => Its::publicUrl('faq', strval($r['id']))];
        }, Knowledge::search(mb_substr(strval($request->get('q', '')), 0, 200), true, 5));

        return response()->json(['items' => $items]);
    }

    public function faqShow($id)
    {
        $this->guard();
        $row = Knowledge::find($id, true);
        if (!isset($row)) {
            return $this->notFound();
        }
        $key = 'its_faq_seen_' . $row->id;
        if (!session()->has($key)) {
            session()->put($key, 1);
            Knowledge::bump($row, 'views');
        }

        return $this->page('faq_show', ['row' => $row, 'voted' => session()->get('its_faq_vote_' . $row->id)]);
    }

    public function faqVote(Request $request, $id)
    {
        $this->guard();
        $row = Knowledge::find($id, true);
        if (!isset($row) || !Tokens::allow('vote', 20)) {
            return $this->notFound();
        }
        $key = 'its_faq_vote_' . $row->id;
        if (!session()->has($key)) {
            $helpful = $request->get('helpful') === '1';
            session()->put($key, $helpful ? 'yes' : 'no');
            Knowledge::bump($row, $helpful ? 'helpful' : 'not_helpful');
        }

        return redirect(Its::publicUrl('faq', strval($row->id)));
    }

    // ---- my requests ------------------------------------------------------

    public function mineForm()
    {
        $this->guard();

        return $this->page('mine_form');
    }

    /**
     * Mail the list link to the address. The page says the same thing
     * whether the address is known or not, so nobody can use this form to
     * find out who our customers are.
     */
    public function postMine(Request $request)
    {
        $this->guard();
        if (!Tokens::allow('mine', 3)) {
            return $this->tooMany();
        }
        $email = Its::normalizeEmail(strval($request->get('email', '')));
        if (Its::isEmail($email)) {
            $ticket = Tickets::query()->where('value->requester_email', $email)->where('value->customer_visible', '1')->orderByDesc('id')->first();
            if (isset($ticket)) {
                $link = Its::publicUrl('mine', Tokens::forMine($email));
                $text = "ご依頼の一覧は、次のリンクから見られます（" . Tokens::MINE_DAYS . " 日間有効・ログインは要りません）。\n\n{$link}\n\nこのメールに心当たりがないときは、何もしないでください。";
                try {
                    \Illuminate\Support\Facades\Mail::raw($text, function ($m) use ($email) {
                        $from = Mailer::fromAddress();
                        if (Its::isEmail($from)) {
                            $m->from($from, strval(Its::setting('support_name', '')) ?: null);
                        }
                        $m->to($email)->subject('【' . Its::setting('portal_title', 'サポート窓口') . '】ご依頼の一覧のリンク');
                    });
                } catch (\Throwable $e) {
                    \Log::warning('ExmentITSupport mine mail failed: ' . $e->getMessage());
                }
            }
            Tokens::log(null, null, 'mine_request');
        }

        return $this->page('message', [
            'heading' => 'メールをご確認ください',
            'text' => 'このアドレスでご依頼がある場合は、一覧のリンクをメールでお送りしました。数分たっても届かないときは、迷惑メールのフォルダもご確認ください。',
        ]);
    }

    public function mineList($token)
    {
        $this->guard();
        if (!Tokens::allow('open', 30)) {
            return $this->tooMany();
        }
        $row = Tokens::find(strval($token), Tokens::KIND_MINE);
        if (!isset($row) || empty($row->email)) {
            return $this->notFound();
        }
        Tokens::touch($row, 'mine_open');
        $tickets = Tickets::query()->where('value->requester_email', $row->email)->where('value->customer_visible', '1')
            ->orderByDesc('id')->limit(100)->get();
        $links = [];
        foreach ($tickets as $t) {
            $links[$t->id] = Tokens::linkFor(intval($t->id), $row->email);
        }

        return $this->page('mine_list', ['email' => $row->email, 'tickets' => $tickets, 'links' => $links]);
    }

    // ---- one ticket ------------------------------------------------------

    /**
     * @return array{0: ?object, 1: ?CustomValue}
     */
    protected function resolve($token): array
    {
        $row = Tokens::find(strval($token), Tokens::KIND_TICKET);
        if (!isset($row)) {
            return [null, null];
        }
        $ticket = Tickets::find($row->ticket_id);
        if (!isset($ticket) || !boolval(array_get($ticket->value, 'customer_visible', '1'))) {
            return [null, null];
        }
        // A merged ticket shows the one it went into - only when the customer
        // could see the survivor anyway: shown to customers and asked by the
        // same address. The address on the link was never verified (the public
        // form takes any address), so following a merge always asks for the
        // one-time code sent to it (see otpPassed). Otherwise the link stays on
        // the merged ticket (ISS00411). A chain A -> B -> C is followed too.
        $this->followedMerge = false;
        $email = Its::normalizeEmail(strval($row->email ?: array_get($ticket->value, 'requester_email')));
        for ($hop = 0; $hop < 5; $hop++) {
            $into = array_get($ticket->value, 'merged_into');
            if (empty($into) || ($target = Tickets::find($into)) === null) {
                break;
            }
            if ($email === '' || !boolval(array_get($target->value, 'customer_visible', '1'))
                || Its::normalizeEmail(strval(array_get($target->value, 'requester_email'))) !== $email) {
                break;
            }
            $ticket = $target;
            $this->followedMerge = true;
        }

        return [$row, $ticket];
    }

    /**
     * Someone acted through a link mailed to the requester's address (or
     * entered the code sent to it): that address is theirs.
     */
    protected function addressShown(object $row, CustomValue $ticket): void
    {
        $email = Its::normalizeEmail(strval($row->email ?? ''));
        if ($email !== '' && $email === Its::normalizeEmail(strval(array_get($ticket->value, 'requester_email')))) {
            Tickets::markAddressVerified($ticket);
        }
    }

    protected function otpPassed(object $row, CustomValue $ticket): bool
    {
        if (!$this->followedMerge && !boolval(array_get($ticket->value, 'require_otp', '0'))) {
            return true;
        }
        $until = intval(session()->get('its_otp_ok_' . $row->id, 0));

        return $until > time();
    }

    public function ticket(Request $request, $token)
    {
        $this->guard();
        if (!Tokens::allow('open', 30)) {
            return $this->tooMany();
        }
        [$row, $ticket] = $this->resolve($token);
        if (!isset($row)) {
            Tokens::log(null, null, 'bad_token');

            return $this->notFound();
        }
        if (!$this->otpPassed($row, $ticket)) {
            return $this->page('otp', ['token' => $token, 'email' => $this->maskEmail(strval($row->email ?: array_get($ticket->value, 'requester_email'))), 'sent' => session()->get('its_otp_sent_' . $row->id)]);
        }

        // The rating links in the mail only pre-select the stars; saving is a
        // POST. Mail scanners (Outlook Safe Links and the like) open every
        // link in a mail, and a GET that saved would rate on their behalf.
        $rate = intval($request->get('rate', 0));
        $rated = $rate >= 1 && $rate <= 5 ? $rate : null;
        Tokens::touch($row, 'open');

        return $this->page('ticket', [
            'token' => $token,
            'ticket' => $ticket,
            'messages' => Tickets::messages($ticket, true),
            'files' => Tickets::files($ticket, true),
            'labels' => Tickets::labels([$ticket]),
            'rated' => $rated,
            'flash' => session()->get('its_flash'),
        ]);
    }

    /** 「通話をお願いする」 (ISS02941): a line to the agents; they send the link from the ticket. */
    public function postCallRequest(Request $request, $token)
    {
        $this->guard();
        if (!GuestCall::enabled()) {
            return $this->notFound();
        }
        if (!Tokens::allow('call_request', 3)) {
            return $this->tooMany();
        }
        [$row, $ticket] = $this->resolve($token);
        if (!isset($row) || !$this->otpPassed($row, $ticket)) {
            return $this->notFound();
        }
        $when = trim(mb_substr(strval($request->input('when', '')), 0, 200));
        $email = strval($row->email ?: array_get($ticket->value, 'requester_email'));
        Tickets::customerWrote($ticket, '📞 通話をお願いしたいです。' . ($when !== '' ? "\n都合のよい時間：" . $when : ''), 'portal', $email, strval(array_get($ticket->value, 'requester_name')));
        Tokens::touch($row, 'call_request');
        session()->flash('its_flash', '通話のお願いを送りました。担当者から通話のリンクをメールでお送りします。');

        return redirect(Its::publicUrl('t', strval($token)));
    }

    public function postReply(Request $request, $token)
    {
        $this->guard();
        if (!Tokens::allow('reply', 10)) {
            return $this->tooMany();
        }
        [$row, $ticket] = $this->resolve($token);
        if (!isset($row) || !$this->otpPassed($row, $ticket)) {
            return $this->notFound();
        }
        $data = $request->validate(['body' => 'required|string|max:20000', 'files.*' => 'file|max:10240'], [], ['body' => '内容', 'files.*' => '添付ファイル']);
        $files = [];
        foreach ((array) $request->file('files', []) as $file) {
            if ($file && $file->isValid() && count($files) < 5) {
                $files[] = ['name' => $file->getClientOriginalName(), 'content' => file_get_contents($file->getRealPath())];
            }
        }
        $this->addressShown($row, $ticket);
        $email = strval($row->email ?: array_get($ticket->value, 'requester_email'));
        $after = Tickets::customerWrote($ticket, trim($data['body']), 'portal', $email, strval(array_get($ticket->value, 'requester_name')), null, $files);
        Tokens::touch($row, 'reply');

        if (intval($after->id) !== intval($ticket->id)) {
            // The old ticket was long closed: send them to the new one.
            return redirect(Tokens::linkFor(intval($after->id), $email));
        }
        session()->flash('its_flash', 'お送りしました。担当者から返事をお送りします。' . (Its::looksLikePassword($data['body']) ? '（パスワードらしき文字がありました。パスワードは書かないでください。必要なときは担当者から別の方法をご案内します。）' : ''));

        return redirect(Its::publicUrl('t', strval($token)));
    }

    public function postSolved($token)
    {
        $this->guard();
        [$row, $ticket] = $this->resolve($token);
        if (!isset($row) || !$this->otpPassed($row, $ticket) || !Tokens::allow('reply', 10)) {
            return $this->notFound();
        }
        if (in_array(array_get($ticket->value, 'status'), Its::ACTIVE_STATUSES, true)) {
            Tickets::addMessage($ticket, ['kind' => 'system', 'body' => 'お客様が「解決した」を押しました。', 'via' => 'portal']);
            $ticket->setValue(['status' => 'resolved']);
            $ticket->save();
            Tokens::touch($row, 'solved');
            $this->addressShown($row, $ticket);
        }
        session()->flash('its_flash', 'ありがとうございました。解決にしました。');

        return redirect(Its::publicUrl('t', strval($token)));
    }

    public function postRate(Request $request, $token)
    {
        $this->guard();
        [$row, $ticket] = $this->resolve($token);
        if (!isset($row) || !$this->otpPassed($row, $ticket) || !Tokens::allow('reply', 10)) {
            return $this->notFound();
        }
        $rate = intval($request->get('rate', 0));
        $values = [];
        if ($rate >= 1 && $rate <= 5) {
            $values['csat'] = $rate;
        }
        $comment = trim(mb_substr(strval($request->get('comment', '')), 0, 2000));
        if ($comment !== '') {
            $values['csat_comment'] = $comment;
        }
        if (!empty($values)) {
            $ticket->setValue($values);
            $ticket->save();
            Tokens::touch($row, 'rate');
            $this->addressShown($row, $ticket);
        }
        session()->flash('its_flash', 'ご意見ありがとうございました。');

        return redirect(Its::publicUrl('t', strval($token)));
    }

    public function postOtpSend($token)
    {
        $this->guard();
        if (!Tokens::allow('otp_send', 3)) {
            return $this->tooMany();
        }
        [$row, $ticket] = $this->resolve($token);
        if (!isset($row)) {
            return $this->notFound();
        }
        // A few codes an hour for one link, whatever the IP: a mailbox is not
        // flooded, and new codes do not give new tries (see Tokens::otpLocked).
        if (Tokens::otpLocked($row)) {
            return $this->page('message', ['heading' => 'このリンクのコードは使えません', 'text' => 'まちがったコードが続いたので、このリンクのコードは明日まで使えません。お急ぎのときは、担当者にご連絡ください（新しいリンクをお送りします）。'], 429);
        }
        $email = strval($row->email ?: array_get($ticket->value, 'requester_email'));
        $code = Its::isEmail($email) ? Tokens::withLinkLock($row, function () use ($row) {
            return Tokens::canSendOtp($row) ? Tokens::newOtp($row) : null;
        }) : '';
        if ($code === null) {
            return $this->tooMany();
        }
        if ($code !== '') {
            try {
                \Illuminate\Support\Facades\Mail::raw("確認のコード: {$code}\n\n" . Tokens::OTP_MINUTES . " 分以内に画面に入れてください。\nこのメールに心当たりがないときは、何もしないでください。", function ($m) use ($email, $ticket) {
                    $from = Mailer::fromAddress();
                    if (Its::isEmail($from)) {
                        $m->from($from, strval(Its::setting('support_name', '')) ?: null);
                    }
                    $m->to($email)->subject('[' . array_get($ticket->value, 'ticket_no') . '] 確認のコード');
                });
            } catch (\Throwable $e) {
                \Log::warning('ExmentITSupport otp mail failed: ' . $e->getMessage());
            }
            Tokens::log($row->id, $row->ticket_id, 'otp_send');
        }
        session()->put('its_otp_sent_' . $row->id, true);

        return redirect(Its::publicUrl('t', strval($token)));
    }

    public function postOtp(Request $request, $token)
    {
        $this->guard();
        if (!Tokens::allow('otp', 10)) {
            return $this->tooMany();
        }
        [$row, $ticket] = $this->resolve($token);
        if (!isset($row)) {
            return $this->notFound();
        }
        if (Tokens::checkOtp($row, strval($request->get('code', '')))) {
            // Good for this browser for 12 hours.
            session()->put('its_otp_ok_' . $row->id, time() + 12 * 3600);
            Tokens::log($row->id, $row->ticket_id, 'otp_ok');
            $this->addressShown($row, $ticket);
        } else {
            Tokens::log($row->id, $row->ticket_id, 'otp_ng');
            session()->flash('its_otp_error', Tokens::otpLocked($row)
                ? 'まちがいが続いたので、このリンクのコードは明日まで使えません。担当者にご連絡ください。'
                : 'コードがちがうか、期限が切れています。');
        }

        return redirect(Its::publicUrl('t', strval($token)));
    }

    /** An attachment of the ticket, only through its own link. */
    public function file($token, $uuid)
    {
        $this->guard();
        [$row, $ticket] = $this->resolve($token);
        if (!isset($row) || !$this->otpPassed($row, $ticket)) {
            return $this->notFound();
        }
        $match = null;
        foreach (Tickets::files($ticket, true) as $f) {
            if (hash_equals($f->uuid, strval($uuid))) {
                $match = $f;
            }
        }
        $file = isset($match) ? ExmentFile::getData($match->uuid) : null;
        if (!isset($file)) {
            return $this->notFound();
        }
        $disk = Storage::disk(config('admin.upload.disk'));
        $path = $file->path;
        if (!$disk->exists($path)) {
            return $this->notFound();
        }
        Tokens::touch($row, 'file');

        return response($disk->get($path), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename*=UTF-8''" . rawurlencode($match->name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    protected function maskEmail(string $email): string
    {
        if (!Its::isEmail($email)) {
            return '登録のメールアドレス';
        }
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 2) . str_repeat('*', max(1, mb_strlen($local) - 2)) . '@' . $domain;
    }

    // ---- machine intake --------------------------------------------------

    protected function keyOk(Request $request): bool
    {
        $key = strval(Its::setting('intake_key', ''));
        $given = strval($request->header('X-ITS-Key', $request->get('key', '')));

        return $key !== '' && $given !== '' && hash_equals($key, $given);
    }

    /**
     * POST /support/intake/alert  (JSON)
     *
     * With a sender's key (ISS02928): the body is read as that sender's tool
     * sends it (Zabbix, Mackerel, Alertmanager, Azure Monitor or the generic
     * fields), the sender may require a signature and its addresses, and a
     * recovery may resolve the ticket. With the old shared key, as before:
     * {"title": "...", "source": "web01.example.co.jp", "severity": "error",
     *  "detail": "...", "fingerprint": "optional", "customer": "code"}
     */
    public function intakeAlert(Request $request)
    {
        if (!Feature::on(Feature::PORTAL)) {
            abort(404);
        }
        if (!Its::isInstalled()) {
            return response()->json(['error' => 'not_installed'], 503);
        }
        // Guessing keys is slowed before any key is looked at.
        if (Tokens::blocked('intake_ng', 30)) {
            return response()->json(['error' => 'too_many_requests'], 429);
        }

        $source = IntakeSources::byKey(IntakeSources::keyOf($request));
        if (isset($source)) {
            if (!boolval($source->enabled)) {
                return response()->json(['error' => 'source_disabled'], 403);
            }
            if (!Rules::ipAllowed(strval($source->allowed_ips ?? ''), strval($request->ip()))) {
                Tokens::allow('intake_ng', 30);

                return response()->json(['error' => 'address_not_allowed'], 403);
            }
            if (boolval($source->require_signature) && !Rules::verify(
                IntakeSources::secretOf($source),
                strval($request->header('X-ITS-Timestamp', '')),
                $request->getContent(),
                strval($request->header('X-ITS-Signature', '')),
                time()
            )) {
                Tokens::allow('intake_ng', 30);

                return response()->json(['error' => 'bad_signature'], 401);
            }
            $options = IntakeSources::options($source);
            $limitKey = 'intake_s' . intval($source->id);
        } elseif (Its::setting('alert_shared_key', '1') === '1' && $this->keyOk($request)) {
            $options = IntakeSources::legacy();
            $limitKey = 'intake';
        } else {
            Tokens::allow('intake_ng', 30);

            return response()->json(['error' => 'unauthorized'], 401);
        }

        if (!Tokens::allow($limitKey, 120)) {
            return response()->json(['error' => 'too_many_requests'], 429);
        }
        if (strlen($request->getContent()) > 1024 * 1024) {
            return response()->json(['error' => 'too_large'], 413);
        }
        $data = $request->json()->all() ?: $request->all();
        if (!is_array($data)) {
            $data = [];
        }

        if ($options['legacy']) {
            if (empty($data['title']) && empty($data['message'])) {
                return response()->json(['error' => 'title is required'], 422);
            }

            return response()->json(Inbox::receiveAlert($data));
        }

        $events = Adapters::read($options['kind'], $data);
        IntakeSources::touch($source);
        if (empty($events)) {
            // a tool's test message, or a body that is not an alert: taken, nothing made
            return response()->json(['results' => [], 'message' => 'no alert in the body']);
        }
        $results = array_map(function ($event) use ($options) {
            return Inbox::receiveAlertEvent($event, $options);
        }, $events);

        return response()->json(count($results) === 1 ? $results[0] : ['results' => $results]);
    }

    /**
     * POST /support/intake/mail  (the raw RFC 822 message as the body)
     * For a Postfix pipe, or an inbound-mail service that can POST raw MIME.
     */
    public function intakeMail(Request $request)
    {
        if (!Feature::on(Feature::PORTAL)) {
            abort(404);
        }
        if (!Its::isInstalled()) {
            return response()->json(['error' => 'not_installed'], 503);
        }
        if (!$this->keyOk($request)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }
        $raw = $request->getContent();
        if ($raw === '' || strlen($raw) > 30 * 1024 * 1024) {
            return response()->json(['error' => 'empty or too large'], 422);
        }

        return response()->json(['result' => Inbox::receiveRaw($raw)]);
    }
}

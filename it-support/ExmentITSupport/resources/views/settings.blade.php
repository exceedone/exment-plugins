@php($Its = \App\Plugins\ExmentITSupport\Its::class)
<div class="its its-page">
@include($Its::view('_nav'), ['tab' => 'settings'])
<div class="its-head">
    <h1>設定</h1>
    <span class="its-sub">メールの受信・送信、営業時間、自動で進めること、お客様の窓口を決めます。</span>
</div>

<div class="its-grid3">
<section>
<form method="post" action="{{ $Its::url('settings') }}">
@csrf
    <div class="its-card">
        <h2><i class="fa fa-envelope" aria-hidden="true"></i>メールで受け付ける（IMAP）</h2>
        @include($Its::view('_feature_note'))
        <div class="its-sub its-lead">受付用のメールボックス（例: support@…）を 5 分ごとに読みに行き、新しいメールをチケットにします。お客様が返信すると、同じチケットに入ります。Gmail・Microsoft 365・レンタルサーバーなど、IMAP が使えればどこでも動きます。</div>
        @if(!empty($state['imap_error']))<div class="its-flash error" role="alert"><i class="fa fa-exclamation-circle" aria-hidden="true"></i><div><b>受信でエラーが出ています</b><br>{{ $state['imap_error'] }}</div></div>@endif
        <div class="its-form-grid">
            <label for="s-imap-on">受信する</label><label class="its-check"><input type="checkbox" class="its-sw" id="s-imap-on" name="imap_enabled" value="1" @checked($s['imap_enabled'] === '1')> オン</label>
            <label for="s-imap-host">IMAP サーバー</label><input type="text" id="s-imap-host" name="imap_host" value="{{ $s['imap_host'] }}" placeholder="imap.example.co.jp">
            <label for="s-imap-port">ポート・暗号化</label>
            <span class="its-row"><input type="number" id="s-imap-port" name="imap_port" value="{{ $s['imap_port'] }}" style="width:90px" aria-label="ポート">
                <select name="imap_security" aria-label="暗号化">@foreach(['ssl' => 'SSL/TLS（993）', 'tls' => 'STARTTLS（143）', 'none' => 'なし（おすすめしません）'] as $k => $l)<option value="{{ $k }}" @selected($s['imap_security'] === $k)>{{ $l }}</option>@endforeach</select></span>
            <label for="s-imap-user">ユーザー名</label><input type="text" id="s-imap-user" name="imap_user" value="{{ $s['imap_user'] }}" autocomplete="off">
            <label for="s-imap-pass">パスワード</label><input type="password" id="s-imap-pass" name="imap_password" value="" placeholder="{{ $s['imap_password'] !== '' ? '（保存済み。変えるときだけ入力）' : '' }}" autocomplete="new-password">
            <label for="s-imap-folder">フォルダ</label><input type="text" id="s-imap-folder" name="imap_folder" value="{{ $s['imap_folder'] }}">
            <label for="s-unknown">知らない差出人</label>
            <select id="s-unknown" name="unknown_sender"><option value="accept" @selected($s['unknown_sender'] === 'accept')>チケットにする（お客様なし）</option><option value="ignore" @selected($s['unknown_sender'] === 'ignore')>取り込まない（登録したお客様だけ）</option></select>
            <label for="s-mail-auth">差出人の確認</label>
            <select id="s-mail-auth" name="mail_auth"><option value="require" @selected($s['mail_auth'] === 'require')>受信サーバーの確認（DMARC・DKIM・SPF）に合格したメールだけ信じる（おすすめ）</option><option value="require_top" @selected($s['mail_auth'] === 'require_top')>同じ。ただし受信サーバーは名前のない確認の結果を一番上に付ける（Microsoft 365 など）</option><option value="fail_only" @selected($s['mail_auth'] === 'fail_only')>確認に落ちたメールだけ疑う（確認の結果を付けないサーバー向け）</option></select>
            <label for="s-mail-authserv">受信サーバーの名前</label><input type="text" id="s-mail-authserv" name="mail_authserv_id" value="{{ $s['mail_authserv_id'] }}" placeholder="例: mx.example.co.jp">
            <div></div><div class="its-sub">受信サーバーが付ける Authentication-Results の、先頭の名前です。<b>これを入れるまで、メールの差出人は確かめられたことになりません</b>（メールで来たチケットは「なりすましの疑い」になります）。@if(($state['mail_authserv_seen'] ?? '') === '(none)')いくつかの送り主からのメールで、一番上の確認の結果に名前がありません。Microsoft 365 などなら、上で「名前のない確認の結果を一番上に付ける」を選んでください。@elseif(!empty($state['mail_authserv_seen']))参考: 最近のメールの一番上にあった名前は <code>{{ $state['mail_authserv_seen'] }}</code> です。送り主もこのヘッダーを書けるので、そのまま入れずに、受信サーバーの設定（またはメールサービスの説明）で自分のサーバーの名前を確かめてから入れてください。@endif 確認の結果は、1 つのヘッダーにまとめて書くサーバーにしてください（一番上の 1 つだけを読みます）。 受信サーバーが確認の結果を付けないとき（Postfix から直接受けるなど）は、「確認に落ちたメールだけ疑う」にしてください。送り主が書いたヘッダーが一番上に残るからです。</div>
        </div>
        <div class="its-sub its-foot"><i class="fa fa-lock" aria-hidden="true"></i> パスワードはアプリの鍵で暗号化して保存します。最後の確認: <span class="its-num">{{ $state['imap_checked_at'] ?? '—' }}</span></div>
    </div>

    <div class="its-card">
        <h2><i class="fa fa-paper-plane" aria-hidden="true"></i>メールを送る</h2>
        <div class="its-sub its-lead">送信は Exment の「システム設定 → メール」の設定を使います。いま送る元のアドレス: <code>{{ $mailFrom ?: '（未設定）' }}</code></div>
        <div class="its-form-grid">
            <label for="s-addr">窓口のアドレス</label><input type="email" id="s-addr" name="support_address" value="{{ $s['support_address'] }}" placeholder="support@example.co.jp（返信先になります）">
            <label for="s-name">差出人の名前</label><input type="text" id="s-name" name="support_name" value="{{ $s['support_name'] }}">
            <label for="s-prefix">件名の頭に付ける文字</label><input type="text" id="s-prefix" name="subject_prefix" value="{{ $s['subject_prefix'] }}" placeholder="例: 【〇〇サポート】">
            <label for="s-sign" class="top">署名</label><textarea id="s-sign" name="signature" style="min-height:90px">{{ $s['signature'] }}</textarea>
        </div>
    </div>

    <div class="its-card">
        <h2><i class="fa fa-clock-o" aria-hidden="true"></i>営業時間（SLA の時計）</h2>
        <div class="its-form-grid">
            <span class="lbl" id="s-days-l">営業日</span>
            <span class="its-days" role="group" aria-labelledby="s-days-l">@foreach([1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金', 6 => '土', 7 => '日'] as $d => $l)<label><input type="checkbox" name="business_days[]" value="{{ $d }}" @checked(in_array((string) $d, explode(',', $s['business_days']), true))> {{ $l }}</label>@endforeach</span>
            <label for="s-bstart">時間</label><span class="its-row"><input type="time" id="s-bstart" name="business_start" value="{{ $s['business_start'] }}" aria-label="始まり"> 〜 <input type="time" name="business_end" value="{{ $s['business_end'] }}" aria-label="終わり"></span>
            <label for="s-holidays" class="top">休日</label><textarea id="s-holidays" name="holidays" class="its-num" style="min-height:120px;max-width:260px" placeholder="2026-12-29（1 行に 1 日）">{{ $s['holidays'] }}</textarea>
        </div>
        <div class="its-sub its-foot">日本の祝日は、セットアップのときに今年と来年の分を入れてあります。年末年始・夏休みなどを足してください。SLA の目安の時間は「<a href="{{ $Its::dataUrl($Its::T_SLA) }}">SLA</a>」のテーブルで決めます。</div>
    </div>

    <div class="its-card">
        <h2><i class="fa fa-magic" aria-hidden="true"></i>自動で進める</h2>
        <div class="its-form-grid">
            <label for="s-assign">担当者の決め方</label>
            <select id="s-assign" name="auto_assign">@foreach(['category' => '種類ごとの「最初の担当者」', 'round_robin' => '種類で決まらなければ、対応中が少ない人', 'none' => '自動では決めない'] as $k => $l)<option value="{{ $k }}" @selected($s['auto_assign'] === $k)>{{ $l }}</option>@endforeach</select>
            <label for="s-remind">返事待ちのお願い</label><span class="its-inline"><input type="number" id="s-remind" name="pending_remind_days" value="{{ $s['pending_remind_days'] }}" min="0"> 日たったらお客様にメール（0 で送らない）</span>
            <label for="s-presolve">返事待ちを解決に</label><span class="its-inline"><input type="number" id="s-presolve" name="pending_resolve_days" value="{{ $s['pending_resolve_days'] }}" min="0"> 日たったら（0 でしない）</span>
            <label for="s-close">解決を完了に</label><span class="its-inline"><input type="number" id="s-close" name="resolved_close_days" value="{{ $s['resolved_close_days'] }}" min="0"> 日たったら（0 でしない）</span>
            <label for="s-asset">IT 資産の期限</label><span class="its-inline"><input type="number" id="s-asset" name="asset_notice_days" value="{{ $s['asset_notice_days'] }}" min="1"> 日前にチケットを作る</span>
            <label for="s-csat">満足度を聞く</label><label class="its-check"><input type="checkbox" class="its-sw" id="s-csat" name="csat_enabled" value="1" @checked($s['csat_enabled'] === '1')> 解決にしたとき、お客様に 5 段階で聞く</label>
        </div>
    </div>

    <div class="its-card" id="its-escalation">
        <h2><i class="fa fa-level-up" aria-hidden="true"></i>SLA のエスカレーション</h2>
        <input type="hidden" name="esc_form" value="1">
        <div class="its-sub its-lead">期限が近づいたら担当者へ、過ぎたらリーダーへ、さらに過ぎたら管理者へ、段階を追って知らせます。それぞれ 1 回だけです。使うあいだは、上の「まもなく期限」「超過」の 1 回の知らせのかわりになります。</div>
        <div class="its-form-grid">
            <label for="s-esc-on">使う</label><label class="its-check"><input type="checkbox" class="its-sw" id="s-esc-on" name="esc_enabled" value="1" @checked($s['esc_enabled'] === '1')> 段階を追って知らせる</label>
            <label for="s-esc-pct">1. 担当者へ</label><span class="its-inline">期限までの時間の <input type="number" id="s-esc-pct" name="esc_warn_percent" value="{{ $s['esc_warn_percent'] }}" min="1" max="99"> % が過ぎたら（担当者がいないときは担当者みんな）</span>
            <label for="s-esc-lead" class="top">2. 期限を過ぎたらリーダーへ</label>
            <select id="s-esc-lead" name="esc_lead_users[]" multiple size="4">@foreach($agentOptions as $id => $name)<option value="{{ $id }}" @selected(in_array((string) $id, explode(',', $s['esc_lead_users']), true))>{{ $name }}</option>@endforeach</select>
            <label for="s-esc-after">3. 管理者へ</label><span class="its-inline">期限から <input type="number" id="s-esc-after" name="esc_manager_after" value="{{ $s['esc_manager_after'] }}" min="0"> 分過ぎたら（0 でしない）</span>
            <label for="s-esc-mgr" class="top">管理者</label>
            <select id="s-esc-mgr" name="esc_manager_users[]" multiple size="4">@foreach($agentOptions as $id => $name)<option value="{{ $id }}" @selected(in_array((string) $id, explode(',', $s['esc_manager_users']), true))>{{ $name }}</option>@endforeach</select>
            <span class="lbl">知らせ方</span>
            <span class="its-days" role="group" aria-label="知らせ方">@foreach($escChannels as $k => $l)<label><input type="checkbox" name="esc_channels[]" value="{{ $k }}" @checked(in_array($k, explode(',', $s['esc_channels']), true))> {{ $l }}</label>@endforeach</span>
            <label for="s-esc-teams">Teams のワークフローの URL</label><input type="url" id="s-esc-teams" name="esc_teams_url" value="" placeholder="{{ $s['esc_teams_url'] !== '' ? '（保存済み。変えるときだけ入力。消すときは -）' : 'https://...' }}" autocomplete="off">
            <label for="s-esc-hook">Webhook の URL</label><input type="url" id="s-esc-hook" name="esc_webhook_url" value="" placeholder="{{ $s['esc_webhook_url'] !== '' ? '（保存済み。変えるときだけ入力。消すときは -）' : 'https://...' }}" autocomplete="off">
            <label for="s-esc-lw">LINE WORKS のチャンネル ID</label><input type="text" id="s-esc-lw" name="esc_lineworks_channel" value="{{ $s['esc_lineworks_channel'] }}" placeholder="空なら、知らせる人の LINE WORKS へ">
        </div>
        <div class="its-sub its-foot">リーダー・管理者を選ばないときは、ひとつ前の段階の人に知らせます。LINE WORKS は Exment の「LINE WORKS」の設定を使います（Exment 7.3 以降）。Webhook には JSON（event・step・ticket_no・subject・priority・due・url）を送ります。</div>
    </div>

    <div class="its-card">
        <h2><i class="fa fa-globe" aria-hidden="true"></i>お客様の窓口（ログインなし）</h2>
        @include($Its::view('_feature_note'))
        <div class="its-form-grid">
            <label for="s-public">使う</label><label class="its-check"><input type="checkbox" class="its-sw" id="s-public" name="public_enabled" value="1" @checked($s['public_enabled'] === '1')> 受付フォーム・進み具合のリンク・FAQ を使う</label>
            <span class="lbl">窓口の URL</span><span style="min-width:0;word-break:break-all"><a href="{{ $publicHome }}" target="_blank" rel="noopener">{{ $publicHome }} <i class="fa fa-external-link" aria-hidden="true"></i></a></span>
            <label for="s-ptitle">窓口の名前</label><input type="text" id="s-ptitle" name="portal_title" value="{{ $s['portal_title'] }}">
            <label for="s-pmsg" class="top">はじめのメッセージ</label><textarea id="s-pmsg" name="portal_message" style="min-height:80px">{{ $s['portal_message'] }}</textarea>
            <label for="s-linkdays">リンクの期限</label><span class="its-inline">完了から <input type="number" id="s-linkdays" name="link_days_after_close" value="{{ $s['link_days_after_close'] }}" min="1"> 日で開けなくなる</span>
        </div>
        <div class="its-sub its-foot">リンクは推測できない長い番号で、チケット 1 件だけが見えます。パスワードはメールで送りません。大事なチケットは「確認のコード」をオンにすると、登録のメールに届く 6 桁のコードがないと開けません。</div>
    </div>

    <div class="its-savebar">
        <button class="its-btn primary big" type="submit"><i class="fa fa-save" aria-hidden="true"></i>設定を保存</button>
        <span class="its-sub">この画面の設定をまとめて保存します。</span>
    </div>
</form>
</section>

<aside>
    <div class="its-card">
        <h3><i class="fa fa-plug" aria-hidden="true"></i>メールの確認</h3>
        <div class="its-sub" style="margin-bottom:10px">先に設定を保存してください。</div>
        <div class="its-row">
            <form method="post" action="{{ $Its::url('settings', 'mail-test') }}">@csrf<button class="its-btn" type="submit"><i class="fa fa-exchange" aria-hidden="true"></i>IMAP につながるか試す</button></form>
            <form method="post" action="{{ $Its::url('settings', 'fetch-now') }}">@csrf<button class="its-btn" type="submit"><i class="fa fa-cloud-download" aria-hidden="true"></i>いますぐ受信する</button></form>
        </div>
    </div>

    <div class="its-card" id="its-sources">
        <h3><i class="fa fa-bell" aria-hidden="true"></i>監視・エラーログから起票</h3>
        @include($Its::view('_feature_note'))
        <div class="its-sub">監視の道具（Zabbix・Mackerel・Prometheus Alertmanager・Azure Monitor・アプリのログなど）から次の URL に送ると、チケットになります。同じ問題は 1 件にまとめ、回数を数えます。できたチケットは、最初はお客様に見せません。</div>
        <dl class="its-props">
            <dt>URL</dt><dd><code class="its-codeline">{{ $intakeAlert }}</code></dd>
        </dl>

        <h4 style="margin-top:14px">送り元（監視の道具ごとのキー）</h4>
        <div class="its-sub">送り元ごとにキーを作ります。キーは作ったときに 1 回だけ出ます（保存するのはハッシュだけ）。作った送り元は「使わない」なので、設定を確かめてから「使う」にしてください。キーは、ヘッダー <code>X-ITS-Key</code>・<code>Authorization: Bearer</code>・URL の Basic 認証（<code>https://its:キー@…</code>）・URL の <code>?key=</code> のどれでも送れます。道具ごとの設定は、README の「監視の道具とつなぐ」を見てください。</div>

        @if(!empty($newSource))
        <div class="its-flash" role="status"><i class="fa fa-key" aria-hidden="true"></i><div>
            <b>新しいキー（この画面を離れると、もう出ません）</b>
            <dl class="its-props">
                <dt>キー</dt><dd><code class="its-codeline">{{ $newSource['key'] }}</code></dd>
                <dt>署名の秘密（署名を使うときだけ）</dt><dd><code class="its-codeline">{{ $newSource['secret'] }}</code></dd>
            </dl>
        </div></div>
        @endif

        @if(!$sourcesReady)
        <div class="its-sub">送り元のテーブルがまだありません。セットアップの画面で「テーブルを更新」を押してください。</div>
        @else
        @foreach($sources as $src)
        <form method="post" action="{{ $Its::url('settings', 'intake-source', $src->id) }}" class="its-card" style="margin:10px 0;padding:10px">@csrf
            <div class="its-row" style="justify-content:space-between">
                <b>{{ $src->name }}</b>
                <span class="its-sub">{{ $sourceKinds[$src->kind] ?? $src->kind }}・キー {{ $src->key_hint }}…・{{ $src->enabled ? '使う' : '使わない' }}・受けた数 {{ $src->use_count }}@if($src->last_used_at)（最後 {{ $src->last_used_at }}）@endif</span>
            </div>
            <div class="its-form-grid">
                <label for="src{{ $src->id }}-name">名前</label><input type="text" id="src{{ $src->id }}-name" name="name" value="{{ $src->name }}" maxlength="100">
                <label for="src{{ $src->id }}-on">使う</label><label class="its-check"><input type="checkbox" class="its-sw" id="src{{ $src->id }}-on" name="enabled" value="1" @checked($src->enabled)> このキーで受ける</label>
                <label for="src{{ $src->id }}-res">復旧の知らせ</label><select id="src{{ $src->id }}-res" name="on_resolved">@foreach($onResolvedOptions as $k => $l)<option value="{{ $k }}" @selected($src->on_resolved === $k)>{{ $l }}</option>@endforeach</select>
                <label for="src{{ $src->id }}-flap">ばたつき</label><span class="its-row"><input type="number" id="src{{ $src->id }}-flap" name="flap_minutes" value="{{ $src->flap_minutes }}" min="0" max="1440" style="width:90px"> 分以内にまた起きたら、同じチケットを開け直す（0：しない）</span>
                <label for="src{{ $src->id }}-asset">IT 資産</label><label class="its-check"><input type="checkbox" class="its-sw" id="src{{ $src->id }}-asset" name="link_asset" value="1" @checked($src->link_asset)> ホスト名・IP アドレスで IT 資産を探してつなぐ</label>
                <label for="src{{ $src->id }}-sig">署名</label><label class="its-check"><input type="checkbox" class="its-sw" id="src{{ $src->id }}-sig" name="require_signature" value="1" @checked($src->require_signature)> 署名のない知らせは受けない（汎用・Zabbix。<code>X-ITS-Timestamp</code>・<code>X-ITS-Signature</code>）</label>
                <label for="src{{ $src->id }}-ips">送り元の IP</label><textarea id="src{{ $src->id }}-ips" name="allowed_ips" rows="2" placeholder="空：どこからでも。1 行に 1 つ（192.0.2.10、192.0.2.0/24）">{{ $src->allowed_ips }}</textarea>
            </div>
            <div class="its-row" style="margin-top:8px">
                <button class="its-btn small" type="submit" name="do" value="save"><i class="fa fa-save" aria-hidden="true"></i>保存</button>
                <button class="its-btn small" type="submit" name="do" value="renew" onclick="return confirm('今のキーは使えなくなります。よろしいですか？')"><i class="fa fa-refresh" aria-hidden="true"></i>キーを作り直す</button>
                <button class="its-btn small danger" type="submit" name="do" value="delete" onclick="return confirm('この送り元を消します。キーは使えなくなります（できたチケットは残ります）。よろしいですか？')"><i class="fa fa-trash" aria-hidden="true"></i>消す</button>
            </div>
        </form>
        @endforeach
        <form method="post" action="{{ $Its::url('settings', 'intake-source') }}" class="its-row" style="margin-top:10px">@csrf
            <select name="kind" aria-label="監視の道具">@foreach($sourceKinds as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            <input type="text" name="name" placeholder="名前（例：本社の Zabbix）" maxlength="100" aria-label="名前">
            <button class="its-btn small" type="submit"><i class="fa fa-plus" aria-hidden="true"></i>送り元を作る</button>
        </form>
        @endif

        <h4 style="margin-top:16px">共有のキー（前からの送り方）</h4>
        <div class="its-sub">1.0.9 までの、1 つのキーです。メールの受け口 <code>{{ $intakeMail }}</code>（Postfix のパイプなど、元のメールをそのまま POST）は、このキーを使います。アラートは、上の送り元のキーに切りかえてください。</div>
        <dl class="its-props">
            <dt>キー（ヘッダー <code>X-ITS-Key</code>）</dt><dd><code class="its-codeline">{{ $s['intake_key'] }}</code></dd>
        </dl>
<pre class="its-code">curl -X POST {{ $intakeAlert }} \
 -H "X-ITS-Key: （キー）" \
 -H "Content-Type: application/json" \
 -d '{"title":"Disk full on web01","source":"web01.example.co.jp","severity":"error","detail":"/var 98%"}'</pre>
        <div class="its-row" style="margin-top:12px">
            <form method="post" action="{{ $Its::url('settings', 'alert-shared-key') }}">@csrf
                <input type="hidden" name="alert_shared_key" value="{{ $s['alert_shared_key'] === '1' ? '0' : '1' }}">
                <button class="its-btn small" type="submit">{{ $s['alert_shared_key'] === '1' ? '共有のキーではアラートを受けない' : '共有のキーでもアラートを受ける' }}</button>
            </form>
            <form method="post" action="{{ $Its::url('settings', 'intake-key') }}" onsubmit="return confirm('今のキーは使えなくなります。よろしいですか？')">@csrf<button class="its-btn small danger" type="submit"><i class="fa fa-refresh" aria-hidden="true"></i>共有のキーを作り直す</button></form>
        </div>
        <div class="its-sub">今：共有のキーで{{ $s['alert_shared_key'] === '1' ? 'もアラートを受けます' : 'は、アラートを受けません' }}。</div>
    </div>

    <div class="its-card">
        <h3><i class="fa fa-table" aria-hidden="true"></i>テーブル・権限</h3>
        <div class="its-sub">項目・画面・ワークフローは Exment の画面で自由に変えられます。担当者は役割グループ「ITサポート 担当者」に、ログインするお客様は「ITサポート お客様」に入れてください。</div>
        <ul class="its-tables" aria-label="ITサポートのテーブル">
            @foreach([[$Its::T_TICKET, 'チケット'], [$Its::T_CUSTOMER, 'お客様（会社）'], [$Its::T_CONTACT, 'お客様の担当者'], [$Its::T_ASSET, 'IT 資産'], [$Its::T_KNOWLEDGE, 'ナレッジ（FAQ）'], [$Its::T_TEMPLATE, '定型文'], [$Its::T_CATEGORY, 'チケットの種類'], [$Its::T_SLA, 'SLA']] as [$tn, $label])
            <li><a href="{{ $Its::dataUrl($tn) }}">{{ $label }}</a></li>
            @endforeach
        </ul>
        <div style="margin-top:10px"><a class="its-btn small" href="{{ $Its::url('setup') }}"><i class="fa fa-wrench" aria-hidden="true"></i>セットアップ・テーブルの更新</a></div>
    </div>
</aside>
</div>
</div>

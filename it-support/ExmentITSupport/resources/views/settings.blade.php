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

    <div class="its-card">
        <h2><i class="fa fa-globe" aria-hidden="true"></i>お客様の窓口（ログインなし）</h2>
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

    <div class="its-card">
        <h3><i class="fa fa-bell" aria-hidden="true"></i>監視・エラーログから起票</h3>
        <div class="its-sub">監視の道具（Zabbix・Mackerel・Uptime Kuma・アプリのログなど）から次の URL に送ると、チケットになります。同じエラーは 1 件にまとめ、回数を数えます。できたチケットは、最初はお客様に見せません。</div>
        <dl class="its-props">
            <dt>URL</dt><dd><code class="its-codeline">{{ $intakeAlert }}</code></dd>
            <dt>キー（ヘッダー <code>X-ITS-Key</code>）</dt><dd><code class="its-codeline">{{ $s['intake_key'] }}</code></dd>
        </dl>
<pre class="its-code">curl -X POST {{ $intakeAlert }} \
 -H "X-ITS-Key: {{ $s['intake_key'] }}" \
 -H "Content-Type: application/json" \
 -d '{"title":"Disk full on web01","source":"web01.example.co.jp","severity":"error","detail":"/var 98%"}'</pre>
        <div class="its-sub">ほかの項目: <code>fingerprint</code>（まとめる印）・<code>customer</code>（お客様コード）。メールを直接送る仕組み（Postfix のパイプなど）は <code>{{ $intakeMail }}</code> に元のメールをそのまま POST します。</div>
        <form method="post" action="{{ $Its::url('settings', 'intake-key') }}" style="margin-top:12px" onsubmit="return confirm('今のキーは使えなくなります。よろしいですか？')">@csrf<button class="its-btn small danger" type="submit"><i class="fa fa-refresh" aria-hidden="true"></i>キーを作り直す</button></form>
    </div>

    <div class="its-card">
        <h3><i class="fa fa-table" aria-hidden="true"></i>テーブル・権限</h3>
        <div class="its-sub">項目・画面・ワークフローは Exment の画面で自由に変えられます。担当者は役割グループ「ITサポート 担当者」に、ログインするお客様は「ITサポート お客様」に入れてください。</div>
        <div style="margin-top:10px"><a class="its-btn small" href="{{ $Its::url('setup') }}"><i class="fa fa-wrench" aria-hidden="true"></i>セットアップ・テーブルの更新</a></div>
    </div>
</aside>
</div>
</div>

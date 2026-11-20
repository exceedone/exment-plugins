@php($Its = \App\Plugins\ExmentITSupport\Its::class)
<div class="its">
@include($Its::view('_nav'), ['tab' => 'settings'])

<div class="its-grid3">
<section>
<form method="post" action="{{ $Its::url('settings') }}">
@csrf
    <div class="its-card">
        <h2><i class="fa fa-envelope"></i> メールで受け付ける（IMAP）</h2>
        <div class="its-sub" style="margin-bottom:8px">受付用のメールボックス（例: support@…）を 5 分ごとに読みに行き、新しいメールをチケットにします。お客様が返信すると、同じチケットに入ります。Gmail・Microsoft 365・レンタルサーバーなど、IMAP が使えればどこでも動きます。</div>
        <div class="its-form-grid">
            <label>受信する</label><label><input type="checkbox" name="imap_enabled" value="1" @checked($s['imap_enabled'] === '1')> オン</label>
            <label>IMAP サーバー</label><input type="text" name="imap_host" value="{{ $s['imap_host'] }}" placeholder="imap.example.co.jp">
            <label>ポート・暗号化</label>
            <span class="its-row"><input type="number" name="imap_port" value="{{ $s['imap_port'] }}" style="width:90px">
                <select name="imap_security">@foreach(['ssl' => 'SSL/TLS（993）', 'tls' => 'STARTTLS（143）', 'none' => 'なし（おすすめしません）'] as $k => $l)<option value="{{ $k }}" @selected($s['imap_security'] === $k)>{{ $l }}</option>@endforeach</select></span>
            <label>ユーザー名</label><input type="text" name="imap_user" value="{{ $s['imap_user'] }}" autocomplete="off">
            <label>パスワード</label><input type="password" name="imap_password" value="" placeholder="{{ $s['imap_password'] !== '' ? '（保存済み。変えるときだけ入力）' : '' }}" autocomplete="new-password">
            <label>フォルダ</label><input type="text" name="imap_folder" value="{{ $s['imap_folder'] }}">
            <label>知らない差出人</label>
            <select name="unknown_sender"><option value="accept" @selected($s['unknown_sender'] === 'accept')>チケットにする（お客様なし）</option><option value="ignore" @selected($s['unknown_sender'] === 'ignore')>取り込まない（登録したお客様だけ）</option></select>
        </div>
        <div class="its-sub" style="margin-top:8px">
            パスワードはアプリの鍵で暗号化して保存します。最後の確認: {{ $state['imap_checked_at'] ?? '—' }}
            @if(!empty($state['imap_error']))<br><b style="color:#c0392b">{{ $state['imap_error'] }}</b>@endif
        </div>
    </div>

    <div class="its-card">
        <h2><i class="fa fa-paper-plane"></i> メールを送る</h2>
        <div class="its-sub" style="margin-bottom:8px">送信は Exment の「システム設定 → メール」の設定を使います。いま送る元のアドレス: <code>{{ $mailFrom ?: '（未設定）' }}</code></div>
        <div class="its-form-grid">
            <label>窓口のアドレス</label><input type="email" name="support_address" value="{{ $s['support_address'] }}" placeholder="support@example.co.jp（返信先になります）" style="width:320px">
            <label>差出人の名前</label><input type="text" name="support_name" value="{{ $s['support_name'] }}">
            <label>件名の頭に付ける文字</label><input type="text" name="subject_prefix" value="{{ $s['subject_prefix'] }}" placeholder="例: 【〇〇サポート】">
            <label class="full">署名</label><textarea class="full" name="signature" style="min-height:80px">{{ $s['signature'] }}</textarea>
        </div>
    </div>

    <div class="its-card">
        <h2><i class="fa fa-clock-o"></i> 営業時間（SLA の時計）</h2>
        <div class="its-form-grid">
            <label>営業日</label>
            <span class="its-row">@foreach([1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金', 6 => '土', 7 => '日'] as $d => $l)<label><input type="checkbox" name="business_days[]" value="{{ $d }}" @checked(in_array((string) $d, explode(',', $s['business_days']), true))> {{ $l }}</label>@endforeach</span>
            <label>時間</label><span class="its-row"><input type="time" name="business_start" value="{{ $s['business_start'] }}"> 〜 <input type="time" name="business_end" value="{{ $s['business_end'] }}"></span>
            <label>休日</label><textarea name="holidays" style="min-height:90px" placeholder="2026-12-29（1 行に 1 日）">{{ $s['holidays'] }}</textarea>
        </div>
        <div class="its-sub">日本の祝日は、セットアップのときに今年と来年の分を入れてあります。年末年始・夏休みなどを足してください。SLA の目安の時間は「<a href="{{ $Its::dataUrl($Its::T_SLA) }}">SLA</a>」のテーブルで決めます。</div>
    </div>

    <div class="its-card">
        <h2><i class="fa fa-magic"></i> 自動で進める</h2>
        <div class="its-form-grid">
            <label>担当者の決め方</label>
            <select name="auto_assign">@foreach(['category' => '種類ごとの「最初の担当者」', 'round_robin' => '種類で決まらなければ、対応中が少ない人', 'none' => '自動では決めない'] as $k => $l)<option value="{{ $k }}" @selected($s['auto_assign'] === $k)>{{ $l }}</option>@endforeach</select>
            <label>返事待ちのお願い</label><span><input type="number" name="pending_remind_days" value="{{ $s['pending_remind_days'] }}" min="0" style="width:70px"> 日たったらお客様にメール（0 で送らない）</span>
            <label>返事待ちを解決に</label><span><input type="number" name="pending_resolve_days" value="{{ $s['pending_resolve_days'] }}" min="0" style="width:70px"> 日たったら（0 でしない）</span>
            <label>解決を完了に</label><span><input type="number" name="resolved_close_days" value="{{ $s['resolved_close_days'] }}" min="0" style="width:70px"> 日たったら（0 でしない）</span>
            <label>IT 資産の期限</label><span><input type="number" name="asset_notice_days" value="{{ $s['asset_notice_days'] }}" min="1" style="width:70px"> 日前にチケットを作る</span>
            <label>満足度を聞く</label><label><input type="checkbox" name="csat_enabled" value="1" @checked($s['csat_enabled'] === '1')> 解決にしたとき、お客様に 5 段階で聞く</label>
        </div>
    </div>

    <div class="its-card">
        <h2><i class="fa fa-globe"></i> お客様の窓口（ログインなし）</h2>
        <div class="its-form-grid">
            <label>使う</label><label><input type="checkbox" name="public_enabled" value="1" @checked($s['public_enabled'] === '1')> 受付フォーム・進み具合のリンク・FAQ を使う</label>
            <label>窓口の URL</label><span><a href="{{ $publicHome }}" target="_blank" rel="noopener">{{ $publicHome }}</a></span>
            <label>窓口の名前</label><input type="text" name="portal_title" value="{{ $s['portal_title'] }}">
            <label class="full">はじめのメッセージ</label><textarea class="full" name="portal_message" style="min-height:70px">{{ $s['portal_message'] }}</textarea>
            <label>リンクの期限</label><span>完了から <input type="number" name="link_days_after_close" value="{{ $s['link_days_after_close'] }}" min="1" style="width:70px"> 日で開けなくなる</span>
        </div>
        <div class="its-sub">リンクは推測できない長い番号で、チケット 1 件だけが見えます。パスワードはメールで送りません。大事なチケットは「確認のコード」をオンにすると、登録のメールに届く 6 桁のコードがないと開けません。</div>
    </div>

    <button class="its-btn primary" type="submit"><i class="fa fa-save"></i> 設定を保存</button>
</form>
</section>

<aside>
    <div class="its-card">
        <h3>メールの確認</h3>
        <form method="post" action="{{ $Its::url('settings', 'mail-test') }}" style="margin-bottom:6px">@csrf<button class="its-btn" type="submit">IMAP につながるか試す</button></form>
        <form method="post" action="{{ $Its::url('settings', 'fetch-now') }}">@csrf<button class="its-btn" type="submit">いますぐ受信する</button></form>
        <div class="its-sub" style="margin-top:6px">先に設定を保存してください。</div>
    </div>

    <div class="its-card">
        <h3><i class="fa fa-bell"></i> 監視・エラーログから起票</h3>
        <div class="its-sub">監視の道具（Zabbix・Mackerel・Uptime Kuma・アプリのログなど）から次の URL に送ると、チケットになります。同じエラーは 1 件にまとめ、回数を数えます。できたチケットは、最初はお客様に見せません。</div>
        <div style="margin-top:6px"><b>URL</b><br><code>{{ $intakeAlert }}</code></div>
        <div style="margin-top:6px"><b>キー</b>（ヘッダー <code>X-ITS-Key</code>）<br><code>{{ $s['intake_key'] }}</code></div>
<pre class="its-code">curl -X POST {{ $intakeAlert }} \
 -H "X-ITS-Key: {{ $s['intake_key'] }}" \
 -H "Content-Type: application/json" \
 -d '{"title":"Disk full on web01","source":"web01.example.co.jp","severity":"error","detail":"/var 98%"}'</pre>
        <div class="its-sub">ほかの項目: <code>fingerprint</code>（まとめる印）・<code>customer</code>（お客様コード）。メールを直接送る仕組み（Postfix のパイプなど）は <code>{{ $intakeMail }}</code> に元のメールをそのまま POST します。</div>
        <form method="post" action="{{ $Its::url('settings', 'intake-key') }}" style="margin-top:6px" onsubmit="return confirm('今のキーは使えなくなります。よろしいですか？')">@csrf<button class="its-btn small danger" type="submit">キーを作り直す</button></form>
    </div>

    <div class="its-card">
        <h3>テーブル・権限</h3>
        <div class="its-sub">項目・画面・ワークフローは Exment の画面で自由に変えられます。担当者は役割グループ「ITサポート 担当者」に、ログインするお客様は「ITサポート お客様」に入れてください。</div>
        <div style="margin-top:6px"><a href="{{ $Its::url('setup') }}">セットアップ・テーブルの更新</a></div>
    </div>
</aside>
</div>
</div>

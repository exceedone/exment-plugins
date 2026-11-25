{{-- Exment Plugin Groupware: the top page. Web mail, and the state of the browser Office (ibisOffice) and its installation. --}}
@if ($mail_used)
<div class="box card">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-envelope-o" aria-hidden="true"></i> Web メール</h3></div>
    <div class="box-body">
        <p>今お使いのメール（Xserver・KAGOYA・Microsoft 365・Gmail・そのほかの IMAP / SMTP のサーバー）に、ブラウザーからつないで、読む・書く・送る・検索する・フォルダーで整理することができます。メールはサーバーに残るので、Outlook やスマホのメールと並べて使えます。</p>
        @if (!$mail_ready)
            <p class="text-warning"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> テーブルがまだありません。プラグインの zip をもう一度アップロードしてください。</p>
        @else
            <a href="{{ $mail_url }}" class="btn btn-primary"><i class="fa fa-inbox" aria-hidden="true"></i> メールを開く</a>
            <a href="{{ $mail_url }}/accounts" class="btn btn-default">{{ $mail_accounts ? 'アカウントの設定（' . $mail_accounts . '）' : 'アカウントを追加する' }}</a>
        @endif
    </div>
</div>
@elseif ($feature_url)
<div class="box card">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-envelope-o" aria-hidden="true"></i> Web メール</h3></div>
    <div class="box-body">
        <p>Web メールは「使わない」になっています。管理者設定の<a href="{{ $feature_url }}">「機能の管理」</a>で「使う」にして、使える人（役割グループ）を選ぶと使えます。</p>
    </div>
</div>
@endif

<div class="box card">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-file-word-o" aria-hidden="true"></i> Office（ブラウザーで Word・Excel・PowerPoint を開く）</h3></div>
    <div class="box-body">
        <p>ファイル共有の {{ implode('・', array_map(function ($e) { return '.' . $e; }, $extensions)) }} のファイルに「Office で開く」ボタンが出ます。開くと、ブラウザーの中で編集でき、保存すると新しい版になります。開いている間はチェックアウト（編集中）になるので、ほかの人は変更できません。</p>

        <table class="table table-condensed" style="max-width:720px">
            <tr><th style="width:200px">ibisOffice</th><td>{{ $version }}（株式会社アイビス、AGPL-3.0）</td></tr>
            <tr><th>状態</th><td>
                @if ($custom_url)
                    ほかの場所の ibisOffice を使います: <code>{{ $custom_url }}</code>
                @elseif ($installed)
                    <span class="text-success"><i class="fa fa-check" aria-hidden="true"></i> このサイトにインストール済み</span>
                @elseif ($update_available)
                    <span class="text-warning"><i class="fa fa-arrow-circle-up" aria-hidden="true"></i> {{ $active_version }} がインストール済み。新しい版 {{ $version }} があります（更新するまでは {{ $active_version }} で開きます）</span>
                @else
                    <span class="text-warning"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> まだインストールしていません</span>
                @endif
            </td></tr>
            <tr><th>ファイル共有</th><td>
                @if ($file_server_url)
                    <a href="{{ $file_server_url }}">ファイル共有を開く</a>
                @else
                    この Exment にはファイル共有がありません（Exment 本体の更新が必要です）。
                @endif
            </td></tr>
            <tr><th>使えるブラウザー</th><td>WebGPU のあるブラウザー: Chrome・Edge、Safari 26 以降（HTTPS のサイト）</td></tr>
        </table>

        @if ($can_install && !$custom_url && !empty($problems))
            <div class="alert alert-warning" style="max-width:720px">
                <p><strong>このままでは ibisOffice を入れられません。</strong></p>
                <ul style="margin-bottom:6px">@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul>
                <p style="margin:0">{{ $elsewhere }}</p>
            </div>
        @endif
        @if ($can_install && !$custom_url)
            <form method="post" action="{{ $install_url }}" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'インストール中…（数分かかります）';">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <button type="submit" class="btn btn-primary">{{ $installed ? 'ibisOffice ' . $version . ' を入れ直す' : ($update_available ? 'ibisOffice を ' . $version . ' に更新する' : 'ibisOffice ' . $version . ' をインストールする') }}</button>
                <span class="help-block" style="display:inline-block;margin-left:8px">GitHub のリリース（約 40 MB）をダウンロードし、SHA-256 を確かめてから、サイトの public フォルダーに置きます。</span>
            </form>
        @endif

        <hr>
        <p class="text-muted" style="font-size:12px;margin:0">
            Powered by <a href="{{ $project_url }}" target="_blank" rel="noopener">ibisOffice</a>.
            ibisOffice は株式会社アイビスのソフトウェアで、AGPL-3.0 と商用ライセンスのデュアルライセンスです。このプラグインは ibisOffice を改変せず、別の iframe で動かします。ソースコードは <a href="{{ $project_url }}" target="_blank" rel="noopener">{{ $project_url }}</a> にあります。
        </p>
    </div>
</div>

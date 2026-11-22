{{-- Exment Plugin Groupware: in the preview of an Office file while ibisOffice is not installed (system administrators only, ISS02827). --}}
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ibisOffice</title>
<style>
    body { margin: 0; font: 14px/1.7 -apple-system, BlinkMacSystemFont, "Segoe UI", "Hiragino Sans", "Noto Sans JP", Meiryo, sans-serif; color: #333; background: #fff; }
    .box { max-width: 640px; margin: 24px auto; padding: 0 16px; }
    h1 { font-size: 16px; margin: 0 0 8px; }
    p { margin: 0 0 10px; }
    ul { margin: 0 0 10px; padding-left: 20px; }
    .warn { color: #8a6d3b; background: #fcf8e3; border: 1px solid #faebcc; border-radius: 4px; padding: 8px 12px; margin: 0 0 12px; }
    .btn { display: inline-block; padding: 6px 14px; border-radius: 4px; background: #3c8dbc; color: #fff; text-decoration: none; }
    .muted { color: #777; font-size: 12px; }
</style>
</head>
<body>
<div class="box">
    @if ($installed)
        <h1>ibisOffice は入っています</h1>
        <p>このページを開き直すと、ここでファイルを見られます。</p>
    @else
        <h1>ibisOffice を入れると、ここで見る・編集ができます</h1>
        <p>Word・Excel・PowerPoint のファイルを、ブラウザーの Office（ibisOffice {{ $version }}）で開けるようになります。LibreOffice は要りません。入れられるのはシステム管理者だけで、ほかの人にはこの案内は出ません。</p>
        <p>要るもの：PHP の zip の拡張と、サイトの public/ に約 125MB の空き。GitHub から取って、照合値を確かめてから入れます。</p>
        @if (!empty($problems))
            <div class="warn">
                <p><strong>このままでは入れられません。</strong></p>
                <ul>@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul>
                <p style="margin:0">{{ $elsewhere }}</p>
            </div>
        @endif
        <p><a class="btn" href="{{ $install_url }}" target="_top">ibisOffice を入れる画面へ</a></p>
        <p class="muted">Exment Plugin Groupware ／ Powered by ibisOffice</p>
    @endif
</div>
</body>
</html>

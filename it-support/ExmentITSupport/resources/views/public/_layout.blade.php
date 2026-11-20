@php($Its = \App\Plugins\ExmentITSupport\Its::class)
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>{{ $title }}</title>
<style>
:root{--bg:#f5f7fa;--card:#fff;--line:#e2e6eb;--text:#1f2933;--sub:#68737f;--accent:#2563eb;--soft:#e8efff;--red:#c0392b;--green:#1e8e4e}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font:15px/1.65 -apple-system,BlinkMacSystemFont,"Segoe UI","Hiragino Sans","Hiragino Kaku Gothic ProN","Noto Sans JP",Meiryo,sans-serif}
a{color:var(--accent)}
header{background:var(--card);border-bottom:1px solid var(--line)}
header .in{max-width:880px;margin:0 auto;padding:12px 16px;display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center}
header .brand{font-weight:700;font-size:17px;color:var(--text);text-decoration:none}
header nav{display:flex;gap:4px;flex-wrap:wrap;margin-left:auto}
header nav a{padding:6px 10px;border-radius:6px;text-decoration:none;color:var(--sub);font-size:14px}
header nav a:hover{background:var(--soft);color:var(--accent)}
main{max-width:880px;margin:0 auto;padding:20px 16px 48px}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:18px;margin-bottom:16px}
h1{font-size:21px;margin:0 0 12px}
h2{font-size:17px;margin:0 0 10px}
.sub{color:var(--sub);font-size:13px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9px 16px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--text);text-decoration:none;cursor:pointer;font-size:15px}
.btn.primary{background:var(--accent);border-color:var(--accent);color:#fff}
.btn.big{padding:14px 20px;font-size:16px;width:100%}
label.f{display:block;font-weight:600;margin:14px 0 4px}
input[type=text],input[type=email],textarea,select{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;font:inherit;background:#fff}
textarea{min-height:150px}
.req{color:var(--red);font-size:12px;margin-left:4px}
.flash{padding:12px 14px;border-radius:8px;background:#e7f6ec;border:1px solid #b7e2c6;margin-bottom:16px}
.err{padding:12px 14px;border-radius:8px;background:#fdecea;border:1px solid #f5c2bd;margin-bottom:16px}
.warn{padding:12px 14px;border-radius:8px;background:#fff6e5;border:1px solid #f3d595;margin-bottom:16px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media (max-width:640px){.grid2{grid-template-columns:1fr}}
.pill{display:inline-block;padding:2px 10px;border-radius:12px;font-size:13px;color:#fff}
.msg{border:1px solid var(--line);border-radius:10px;padding:12px 14px;margin-bottom:10px;background:#fff}
.msg.reply{border-left:4px solid var(--green)}
.msg.customer{border-left:4px solid #3c8dbc;background:#fbfcfe}
.msg .head{font-size:13px;color:var(--sub);margin-bottom:6px}
.msg .head b{color:var(--text)}
.msg .body{white-space:pre-wrap;word-break:break-word}
.steps{display:flex;gap:0;margin:8px 0 4px;flex-wrap:wrap}
.steps span{flex:1;min-width:0;text-align:center;padding:6px 4px;font-size:13px;border-bottom:3px solid var(--line);color:var(--sub)}
.steps span.on{border-color:var(--accent);color:var(--accent);font-weight:700}
.steps span.done{border-color:#9db8f5;color:var(--text)}
.list a.item{display:block;padding:10px 0;border-bottom:1px solid var(--line);text-decoration:none;color:var(--text)}
.list a.item:hover b{color:var(--accent)}
.stars button{font-size:28px;background:none;border:0;cursor:pointer;color:#cbd2d9;padding:0 2px}
.stars button.on{color:#f5a623}
.hp{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}
footer{max-width:880px;margin:0 auto;padding:0 16px 32px;color:var(--sub);font-size:12px}
</style>
</head>
<body>
<header><div class="in">
    <a class="brand" href="{{ $Its::publicUrl() }}">{{ $title }}</a>
    <nav>
        <a href="{{ $Its::publicUrl('request') }}">依頼する</a>
        <a href="{{ $Its::publicUrl('faq') }}">よくある質問</a>
        <a href="{{ $Its::publicUrl('mine') }}">自分の依頼を見る</a>
    </nav>
</div></header>
<main>
@yield('content')
</main>
<footer>このページはお客様専用です。リンクはほかの人に教えないでください。</footer>
</body>
</html>

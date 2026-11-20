{{-- Exment Plugin Groupware: the Web mail table is not there yet (the plugin was copied in by hand, without its migration). --}}
<div class="box card">
    <div class="box-body">
        <p><i class="fa fa-exclamation-triangle text-warning" aria-hidden="true"></i> Web メールのテーブル（gw_mail_accounts）がまだありません。</p>
        <p>プラグインの画面から、このプラグインの zip をもう一度アップロードしてください（アップロードのときにテーブルが作られます）。コマンドで入れたときは <code>php artisan migrate --path=（プラグインのフォルダー）/database/migrations --realpath</code> を実行します。</p>
        <a href="{{ $plugin_url }}" class="btn btn-default">戻る</a>
    </div>
</div>

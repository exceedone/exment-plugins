@php
    $labels = [
        'project_table' => 'プロジェクト', 'milestone_table' => 'マイルストーン', 'category_table' => 'カテゴリ',
        'issue_table' => '課題', 'wiki_table' => 'Wiki', 'status_table' => 'ステータス', 'issue_type_table' => '課題種別',
        'priority_table' => '優先度', 'resolution_table' => '完了理由', 'related_table' => '関連課題',
        'comment' => 'コメント', 'document' => 'ドキュメント', 'files' => '添付ファイル', 'revisions' => '変更履歴',
        'stars' => 'スター/ウォッチ', 'authorities' => 'データ個別の権限',
    ];
@endphp

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">{{ $plugin->plugin_view_name }}</h3>
    </div>
    <div class="box-body">
        <p>プロジェクトポータルのプロジェクトを、課題・Wiki・コメント・変更履歴・添付ファイルごと別の Exment へ移します。
            移行元と移行先の両方にこのプラグインを入れて使います。</p>

        @if ($error)
            <div class="alert alert-danger">{{ $error }}</div>
        @endif
    </div>
</div>

<div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">1. 書き出し（移行元で実行）</h3></div>
    <form method="post" action="{{ $urls['export'] }}">
        {{ csrf_field() }}
        <div class="box-body">
            @if (empty($projects))
                <p class="text-muted">プロジェクトがありません。</p>
            @else
                <p>移行するプロジェクトを選択してください。親子関係のあるプロジェクトは、親も一緒に選んでください。</p>
                <table class="table table-condensed" style="max-width: 640px;">
                    @foreach ($projects as $p)
                        <tr>
                            <td style="width: 32px;"><input type="checkbox" name="projects[]" value="{{ $p['id'] }}" id="pmm_p{{ $p['id'] }}"></td>
                            <td><label for="pmm_p{{ $p['id'] }}" style="font-weight: normal;">{{ $p['name'] }}</label></td>
                            <td><code>{{ $p['code'] }}</code></td>
                            <td>@if ($p['archived'])<span class="label label-default">アーカイブ済み</span>@endif</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-primary" @if (empty($projects)) disabled @endif>ZIP を書き出す</button>
        </div>
    </form>
</div>

<div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">2. 取り込み（移行先で実行）</h3></div>
    <form method="post" action="{{ $urls['import'] }}" enctype="multipart/form-data">
        {{ csrf_field() }}
        <div class="box-body">
            <div class="form-group">
                <label for="pmm_bundle">移行ファイル(ZIP)</label>
                <input type="file" name="bundle" id="pmm_bundle" accept=".zip">
            </div>

            <div class="form-group">
                <label for="pmm_user_map">ユーザー対応表</label>
                <textarea name="user_map" id="pmm_user_map" class="form-control" rows="5" style="max-width: 640px; font-family: monospace;"
                    placeholder="# 旧ユーザーコード=新ユーザーコード（1行に1件）&#10;# * は、ここに書かれていない全員に使う既定&#10;*=kueno">{{ old('user_map', request()->input('user_map', '')) }}</textarea>
                <p class="help-block">移行元のユーザーは、ここで指定した移行先のユーザーに置き換わります（担当者・メンバー・コメントの投稿者・変更履歴の実行者を含む）。ユーザーは移行先に存在している必要があります。</p>
            </div>

            <div class="form-group">
                <label>マスター(ステータス・課題種別・優先度・完了理由)</label>
                <div class="radio"><label><input type="radio" name="master_mode" value="abort" checked> 移行先に違う内容の行があれば止める</label></div>
                <div class="radio"><label><input type="radio" name="master_mode" value="replace"> 移行先の行を削除して、移行元の内容で置き換える</label></div>
            </div>

            <div class="form-group">
                <div class="checkbox"><label>
                    <input type="checkbox" name="confirm" value="1">
                    移行先のデータベースのバックアップを取り、「確認のみ」の結果に問題がないことを確認しました（「実行」を押すときだけ必要です）
                </label></div>
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" name="action" value="dry" class="btn btn-default">確認のみ（書き込まない）</button>
            <button type="submit" name="action" value="execute" class="btn btn-danger" style="margin-left: 8px;">実行（書き込む）</button>
        </div>
    </form>
</div>

@if ($report)
    <div class="box {{ $report['ok'] ? 'box-success' : 'box-danger' }}">
        <div class="box-header with-border">
            <h3 class="box-title">
                @if ($report['executed']) 実行結果：書き込みました
                @elseif ($ran === 'execute') 実行結果：書き込みませんでした
                @elseif ($report['ok']) 確認結果：問題ありません（まだ書き込んでいません）
                @else 確認結果：エラーがあります（書き込んでいません）
                @endif
            </h3>
        </div>
        <div class="box-body">
            @foreach ($report['errors'] as $line)
                <div class="alert alert-danger" style="margin-bottom: 6px;">{{ $line }}</div>
            @endforeach
            @foreach ($report['warnings'] as $line)
                <div class="alert alert-warning" style="margin-bottom: 6px;">{{ $line }}</div>
            @endforeach
            @foreach ($report['info'] as $line)
                <div class="alert alert-info" style="margin-bottom: 6px;">{{ $line }}</div>
            @endforeach

            @if (!empty($report['projects']))
                <h4>プロジェクト</h4>
                <ul>
                    @foreach ($report['projects'] as $p)
                        <li>{{ $p['name'] }} <code>{{ $p['code'] }}</code></li>
                    @endforeach
                </ul>
            @endif

            @if (!empty($report['counts']))
                <h4>{{ $report['executed'] ? '書き込んだ件数' : '書き込む予定の件数' }}</h4>
                <table class="table table-condensed" style="max-width: 360px;">
                    @foreach ($report['counts'] as $name => $count)
                        <tr><td>{{ $labels[$name] ?? $name }}</td><td class="text-right">{{ number_format($count) }}</td></tr>
                    @endforeach
                </table>
            @endif

            @if (!empty($report['users']))
                <h4>ユーザーの置き換え</h4>
                <table class="table table-condensed" style="max-width: 640px;">
                    <tr><th>移行元</th><th>移行先</th></tr>
                    @foreach ($report['users'] as $u)
                        <tr>
                            <td>{{ $u['user_name'] ?? '—' }} <code>{{ $u['user_code'] ?? 'ID ' . $u['id'] }}</code></td>
                            <td>@if ($u['mapped'])<code>{{ $u['mapped'] }}</code>@else<span class="text-danger">未指定</span>@endif</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>
@endif

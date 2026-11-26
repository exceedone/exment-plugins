{{-- Exment Plugin kintone 連携: Plugin@index --}}
@php $t = function ($key, $replace = []) { return \App\Plugins\ExmentKintone\Lang::t($key, $replace); }; @endphp
<div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">{{ $t('title') }}</h3></div>
    <div class="box-body">
        @if($error)
            <div class="alert alert-danger">{{ $error }}</div>
        @endif
        <p>{{ $t('intro') }}</p>
        @if(!$hasSync)
            <div class="alert alert-warning">{{ $t('need_sync') }}</div>
        @elseif(!$ready)
            <div class="alert alert-warning">{{ $t('need_feature') }}</div>
        @else
            <p><a class="btn btn-default" href="{{ $syncUrl }}"><i class="fa fa-refresh"></i> {{ $t('open_sync') }}</a></p>
            <h4>{{ $t('make_header') }}</h4>
            <p class="text-muted">{{ $t('make_help') }}</p>
            <form method="post" action="{{ $makeUrl }}" class="form-horizontal">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="kintone-connection">{{ $t('connection') }}</label>
                    <div class="col-sm-5">
                        <select class="form-control" id="kintone-connection" name="connection" required>
                            @foreach($connections as $connection)
                                <option value="{{ $connection->id }}" @if(strval(old('connection')) === strval($connection->id)) selected @endif>{{ $connection->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="kintone-app">{{ $t('app') }}</label>
                    <div class="col-sm-2"><input type="text" class="form-control" id="kintone-app" name="app" pattern="[0-9]+" required value="{{ old('app') }}"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="kintone-table-name">{{ $t('table_name') }}</label>
                    <div class="col-sm-4"><input type="text" class="form-control" id="kintone-table-name" name="table_name" pattern="[a-z][a-z0-9_]{0,29}" required value="{{ old('table_name') }}"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="kintone-table-view-name">{{ $t('table_view_name') }}</label>
                    <div class="col-sm-4"><input type="text" class="form-control" id="kintone-table-view-name" name="table_view_name" maxlength="40" required value="{{ old('table_view_name') }}"></div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-8"><button type="submit" class="btn btn-primary">{{ $t('make') }}</button></div>
                </div>
            </form>
        @endif
    </div>
</div>

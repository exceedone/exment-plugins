@php($Its = \App\Plugins\ExmentSupportDesk\Its::class)
<div class="sd sd-page">
@include($Its::view('_nav'), ['tab' => 'inbox'])
<div class="sd-narrow">
<div class="sd-head">
    <a class="sd-btn small ghost icon" href="{{ $Its::url() }}" title="受付箱へ戻る" aria-label="受付箱へ戻る"><i class="fa fa-chevron-left" aria-hidden="true"></i></a>
    <h1>チケットを登録</h1>
    <span class="sd-sub">電話・来訪・社内で見つけたことを、チケットにします。</span>
</div>
<form method="post" action="{{ $Its::url('new') }}" class="sd-card">
    @csrf
    @if($errors->any())<div class="sd-flash error" role="alert"><i class="fa fa-exclamation-circle" aria-hidden="true"></i><div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>@endif
    <div class="sd-form-grid">
        <label for="n-channel">受付の方法</label>
        <select id="n-channel" name="channel">@foreach(['phone', 'staff', 'mail', 'monitor'] as $k)<option value="{{ $k }}" @selected(old('channel', 'phone') === $k)>{{ $Its::CHANNELS[$k] }}</option>@endforeach</select>
        <label for="n-subject">件名 <span class="sd-req">必須</span></label><input type="text" id="n-subject" name="subject" value="{{ old('subject') }}" required class="wide">
        <label for="n-desc" class="top">内容（聞き取ったこと）</label>
        <textarea id="n-desc" name="description" style="min-height:150px">{{ old('description') }}</textarea>
        <div class="full sd-divider">依頼した人</div>
        <label for="n-customer">お客様（会社）</label><select id="n-customer" name="customer"><option value="">（メールアドレスから自動）</option>@foreach($customers as $id => $name)<option value="{{ $id }}" @selected(old('customer') == $id)>{{ $name }}</option>@endforeach</select>
        <label for="n-rname">依頼した人</label><input type="text" id="n-rname" name="requester_name" value="{{ old('requester_name') }}">
        <label for="n-remail">メールアドレス</label><input type="email" id="n-remail" name="requester_email" value="{{ old('requester_email') }}">
        <div class="full sd-divider">進め方</div>
        <label for="n-category">種類</label><select id="n-category" name="category"><option value="">（なし）</option>@foreach($categories as $id => $name)<option value="{{ $id }}" @selected(old('category') == $id)>{{ $name }}</option>@endforeach</select>
        <label for="n-priority">優先度</label><select id="n-priority" name="priority">@foreach($Its::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected(old('priority', 'normal') === $k)>{{ $l }}</option>@endforeach</select>
        <label for="n-assignee">担当者</label><select id="n-assignee" name="assignee"><option value="">（種類から自動）</option>@foreach($agents as $id => $name)<option value="{{ $id }}" @selected(old('assignee', $me) == $id)>{{ $name }}</option>@endforeach</select>
        <label for="n-notify">受付のメール</label><label class="sd-check"><input type="checkbox" class="sd-sw" id="n-notify" name="notify" value="1" @checked(old('notify'))> お客様に受付のメール（番号と進み具合のリンク）を送る</label>
    </div>
    <div class="sd-actions"><button class="sd-btn primary big" type="submit"><i class="fa fa-check" aria-hidden="true"></i>登録する</button><a class="sd-btn ghost big" href="{{ $Its::url() }}">やめる</a></div>
</form>
</div>
</div>

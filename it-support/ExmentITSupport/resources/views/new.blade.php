@php($Its = \App\Plugins\ExmentITSupport\Its::class)
<div class="its">
@include($Its::view('_nav'), ['tab' => 'inbox'])
<form method="post" action="{{ $Its::url('new') }}" class="its-card">
    @csrf
    <h2>チケットを登録（電話・来訪・社内で見つけたこと）</h2>
    @if($errors->any())<div class="its-flash error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    <div class="its-form-grid">
        <label>受付の方法</label>
        <select name="channel">@foreach(['phone', 'staff', 'mail', 'monitor'] as $k)<option value="{{ $k }}" @selected(old('channel', 'phone') === $k)>{{ $Its::CHANNELS[$k] }}</option>@endforeach</select>
        <label>件名 *</label><input type="text" name="subject" value="{{ old('subject') }}" required style="width:100%">
        <label>お客様（会社）</label><select name="customer"><option value="">（メールアドレスから自動）</option>@foreach($customers as $id => $name)<option value="{{ $id }}" @selected(old('customer') == $id)>{{ $name }}</option>@endforeach</select>
        <label>依頼した人</label><input type="text" name="requester_name" value="{{ old('requester_name') }}">
        <label>メールアドレス</label><input type="email" name="requester_email" value="{{ old('requester_email') }}" style="width:320px">
        <label>種類</label><select name="category"><option value="">（なし）</option>@foreach($categories as $id => $name)<option value="{{ $id }}" @selected(old('category') == $id)>{{ $name }}</option>@endforeach</select>
        <label>優先度</label><select name="priority">@foreach($Its::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected(old('priority', 'normal') === $k)>{{ $l }}</option>@endforeach</select>
        <label>担当者</label><select name="assignee"><option value="">（種類から自動）</option>@foreach($agents as $id => $name)<option value="{{ $id }}" @selected(old('assignee', $me) == $id)>{{ $name }}</option>@endforeach</select>
        <label class="full">内容（聞き取ったこと）</label>
        <textarea class="full" name="description">{{ old('description') }}</textarea>
        <label></label><label><input type="checkbox" name="notify" value="1" @checked(old('notify'))> お客様に受付のメール（番号と進み具合のリンク）を送る</label>
    </div>
    <div style="margin-top:12px"><button class="its-btn primary" type="submit">登録する</button></div>
</form>
</div>

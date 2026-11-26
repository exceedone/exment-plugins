<?php
// The kintone REST API as the data sync reads it (ISS00113, ExmentKintone\KintoneApi), with a
// fake kintone: no kintone tenant here, so the calls are checked against the API as documented.
//
//   php kintone/tools/test-kintone-api.php
//
// Plain PHP: KintoneApi has no Exment and no Laravel.

require __DIR__ . '/../ExmentKintone/KintoneApi.php';

use App\Plugins\ExmentKintone\KintoneApi;

$failed = 0;
function check(string $name, bool $ok): void
{
    global $failed;
    echo ($ok ? 'ok   ' : 'FAIL ') . $name . "\n";
    $failed += $ok ? 0 : 1;
}

// the subdomain
check('host: a name', KintoneApi::host('example') === 'example.cybozu.com');
check('host: a url', KintoneApi::host('https://Example.cybozu.com/k/12/') === 'example.cybozu.com');
check('host: kintone.com', KintoneApi::host('acme.kintone.com') === 'acme.kintone.com');
check('host: not kintone', KintoneApi::host('evil.example.com') === null);
check('host: not a host', KintoneApi::host('a b') === null);

// the fake kintone: 5 records, the updated time of two the same
$records = [];
foreach ([[1, '2026-11-01T00:00:00Z'], [2, '2026-11-02T00:00:00Z'], [3, '2026-11-02T00:00:00Z'], [4, '2026-11-03T00:00:00Z'], [5, '2026-11-04T00:00:00Z']] as [$id, $at]) {
    $records[] = [
        '$id' => ['type' => '__ID__', 'value' => strval($id)],
        '$revision' => ['type' => '__REVISION__', 'value' => '1'],
        'タイトル' => ['type' => 'SINGLE_LINE_TEXT', 'value' => 'Item ' . $id],
        'qty' => ['type' => 'NUMBER', 'value' => strval($id * 10)],
        '担当者' => ['type' => 'USER_SELECT', 'value' => [['code' => 'user' . $id . '@example.com', 'name' => 'U' . $id]]],
        'tags' => ['type' => 'CHECK_BOX', 'value' => ['a', 'b']],
        '添付' => ['type' => 'FILE', 'value' => $id === 3 ? [['fileKey' => 'fk3', 'name' => 'spec.pdf', 'size' => '10']] : []],
        '明細' => ['type' => 'SUBTABLE', 'value' => []],
        '作成日時' => ['type' => 'CREATED_TIME', 'value' => '2026-10-01T00:00:00Z'],
        '更新日時' => ['type' => 'UPDATED_TIME', 'value' => $at],
    ];
}
$calls = [];
$send = function (string $path, array $query) use (&$calls, $records) {
    $calls[] = [$path, $query];
    if ($path === '/k/v1/app/form/fields.json') {
        return [200, json_encode(['properties' => [
            'タイトル' => ['type' => 'SINGLE_LINE_TEXT', 'code' => 'タイトル', 'label' => 'タイトル', 'required' => true],
            'qty' => ['type' => 'NUMBER', 'code' => 'qty', 'label' => 'Qty'],
            '状態' => ['type' => 'DROP_DOWN', 'code' => '状態', 'label' => '状態', 'options' => ['完了' => ['label' => '完了', 'index' => '1'], '未着手' => ['label' => '未着手', 'index' => '0']]],
            'グループ' => ['type' => 'GROUP', 'code' => 'グループ', 'label' => 'グループ'],
            '更新日時' => ['type' => 'UPDATED_TIME', 'code' => '更新日時', 'label' => '更新日時'],
        ]]), 'application/json'];
    }
    if ($path === '/k/v1/records.json') {
        $q = strval($query['query'] ?? '');
        $list = $records;
        if (preg_match('/\(更新日時 > "([^"]+)" or \(更新日時 = "[^"]+" and \$id > (\d+)\)\)/u', $q, $m)) {
            $list = array_values(array_filter($list, fn ($r) => $r['更新日時']['value'] > $m[1] || ($r['更新日時']['value'] === $m[1] && intval($r['$id']['value']) > intval($m[2]))));
        }
        if (preg_match('/^\$id > (\d+)/', $q, $m)) {
            $list = array_values(array_filter($list, fn ($r) => intval($r['$id']['value']) > intval($m[1])));
        }
        preg_match('/limit (\d+)/', $q, $l);
        return [200, json_encode(['records' => array_slice($list, 0, intval($l[1] ?? 500))]), 'application/json'];
    }
    if ($path === '/k/v1/app.json') {
        return [200, json_encode(['appId' => $query['id'], 'name' => '案件管理']), 'application/json'];
    }
    if ($path === '/k/v1/file.json') {
        return [200, 'PDF-BYTES', 'application/pdf'];
    }
    return [404, json_encode(['message' => 'no such API']), 'application/json'];
};
$api = new KintoneApi($send);

// the fields: the layout left out, the choices in their order
$fields = $api->fields('12');
check('fields: no group', !isset($fields['グループ']) && isset($fields['タイトル']));
check('fields: the choices in order', KintoneApi::options($fields['状態']) === ['未着手', '完了']);
check('fields: the code of the updated time', $api->codeOf('12', 'UPDATED_TIME') === '更新日時');
check('types', KintoneApi::TYPES['NUMBER'] === 'decimal' && KintoneApi::TYPES['USER_SELECT'] === 'user' && !isset(KintoneApi::TYPES['SUBTABLE']));

// the changes, 2 a page: every record once, the two of the same time both
$seen = [];
$cursor = null;
$pages = 0;
do {
    [$page, $cursor, $done] = $api->changes('12', $cursor, 2);
    foreach ($page as $record) {
        $seen[] = $record['$id']['value'];
    }
    $pages++;
} while (!$done && $pages < 10);
check('changes: each record once, oldest first', $seen === ['1', '2', '3', '4', '5']);
check('changes: the cursor of the last', KintoneApi::decodeCursor($cursor) === ['t' => '2026-11-04T00:00:00Z', 'id' => 5]);
[$none, $same, $doneAgain] = $api->changes('12', $cursor, 2);
check('changes: nothing new after the last', $none === [] && $doneAgain && $same === $cursor);
check('changes: a query of kintone', str_contains(strval(end($calls)[1]['query']), 'order by 更新日時 asc, $id asc limit 2'));

// a record as the sync reads it
$item = KintoneApi::item($records[2]);
check('item: the id', $item['id'] === '3');
check('item: a user as its code', $item['values']['担当者'] === ['user3@example.com']);
check('item: a check box as a list', $item['values']['tags'] === ['a', 'b']);
check('item: no subtable, no $id', !isset($item['values']['明細']) && !isset($item['values']['$id']) && !isset($item['values']['$revision']));
check('item: the times', $item['updated'] === '2026-11-02T00:00:00Z' && $item['created'] === '2026-10-01T00:00:00Z');
check('item: the attachments', $item['files'] === [['key' => 'fk3', 'name' => 'spec.pdf']]);
check('file', $api->file('fk3') === 'PDF-BYTES');
check('record url', KintoneApi::recordUrl('example.cybozu.com', '12', '3') === 'https://example.cybozu.com/k/12/show#record=3');

// all the ids
check('all ids', $api->allIds('12') === ['1', '2', '3', '4', '5']);

// the query cannot be broken by a value or a code
check('quote', KintoneApi::quote('a"b\\c') === '"a\\"b\\\\c"');
$refused = false;
try {
    KintoneApi::quoteCode('x" or 1=1');
} catch (InvalidArgumentException $e) {
    $refused = true;
}
check('a code with a quote is refused', $refused);
check('a cursor that is not one', KintoneApi::decodeCursor('{"t":"1 or 1","id":1}') === null);

// an error of kintone: its status and message, no more
$error = '';
try {
    (new KintoneApi(fn () => [401, json_encode(['message' => 'ユーザーのパスワード認証に失敗しました。']), 'application/json']))->appName('1');
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}
check('an error', $error === 'kintone answered HTTP 401: ユーザーのパスワード認証に失敗しました。');

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);

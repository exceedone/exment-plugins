<?php
// ExmentPMBacklog: Backlog's custom fields as ExmentPM's custom attributes (ISS02939) - the
// attribute for a field, and the values of an issue - without a database.
//
//   php project-management/tools/test-pmbl-attributes.php
//
// Plain PHP. The fields and issues follow Backlog's API (GET projects/:id/customFields, issues).

require __DIR__ . '/../ExmentPMBacklog/Attributes.php';

use App\Plugins\ExmentPMBacklog\Attributes as A;

$failed = 0;
function check(string $name, bool $ok, $extra = null): void
{
    global $failed;
    echo ($ok ? 'ok   ' : 'FAIL ') . $name . ($ok || $extra === null ? '' : '  ' . var_export($extra, true)) . "\n";
    $failed += $ok ? 0 : 1;
}

$fields = [
    ['id' => 101, 'typeId' => 1, 'name' => 'OS'],
    ['id' => 102, 'typeId' => 2, 'name' => '再現手順'],
    ['id' => 103, 'typeId' => 3, 'name' => '工数', 'unit' => 'h'],
    ['id' => 104, 'typeId' => 4, 'name' => 'リリース日'],
    ['id' => 105, 'typeId' => 5, 'name' => '環境', 'items' => [['id' => 1, 'name' => '本番'], ['id' => 2, 'name' => '検証']]],
    ['id' => 106, 'typeId' => 6, 'name' => 'ブラウザ', 'items' => [['id' => 3, 'name' => 'Chrome'], ['id' => 4, 'name' => 'Edge'], ['id' => 5, 'name' => 'Safari']]],
    ['id' => 107, 'typeId' => 7, 'name' => '確認', 'items' => [['id' => 6, 'name' => 'UI'], ['id' => 7, 'name' => 'API']]],
    ['id' => 108, 'typeId' => 8, 'name' => '重さ', 'items' => [['id' => 8, 'name' => '軽'], ['id' => 9, 'name' => '重']]],
];

// ---- the attribute for a field
$kinds = [];
foreach ($fields as $f) {
    [$what, $d] = A::plan($f, []);
    $kinds[] = $what . ':' . $d['type'];
}
check('each kind of Backlog field: a new attribute of its kind', $kinds === ['add:text', 'add:text', 'add:number', 'add:date', 'add:select', 'add:multi_select', 'add:multi_select', 'add:select'], $kinds);
[, $d] = A::plan($fields[4], []);
check('a list: its items as the choices', $d['options'] === ['本番', '検証']);
[, $d] = A::plan($fields[2], []);
check('a number: its unit, 2 decimals', $d['unit'] === 'h' && $d['decimals'] === 2);
check('an unknown kind is left out', A::plan(['id' => 1, 'typeId' => 99, 'name' => 'x'], [])[0] === 'skip');

$have = [
    ['id' => 7, 'label' => '環境', 'type' => 'select', 'options' => ['本番'], 'deleted' => false],
    ['id' => 8, 'label' => 'os', 'type' => 'text', 'options' => [], 'deleted' => false],
    ['id' => 9, 'label' => 'リリース日', 'type' => 'text', 'options' => [], 'deleted' => false],
    ['id' => 10, 'label' => '重さ', 'type' => 'select', 'options' => ['軽', '重'], 'deleted' => true],
];
[$what, $d] = A::plan($fields[4], $have);
check('a list there already: its new items added, the old kept', $what === 'update' && $d['id'] === 7 && $d['options'] === ['本番', '検証'], [$what, $d]);
check('the same name, any case: used', A::plan($fields[0], $have) === ['use', $have[1]]);
check('the same name, another kind: not mixed up', A::plan($fields[3], $have)[0] === 'skip');
check('one deleted in Exment stays deleted', A::plan($fields[7], $have)[0] === 'deleted');

// ---- the values of an issue
$map = [];
foreach ($fields as $i => $f) {
    [, $d] = A::plan($f, []);
    $d['id'] = 20 + $i;
    $map[strval($f['id'])] = $d;
}
$issue = ['customFields' => [
    ['id' => 101, 'fieldTypeId' => 1, 'name' => 'OS', 'value' => 'Windows 11'],
    ['id' => 102, 'fieldTypeId' => 2, 'name' => '再現手順', 'value' => "1. 開く\r\n2. 押す"],
    ['id' => 103, 'fieldTypeId' => 3, 'name' => '工数', 'value' => '3.456'],
    ['id' => 104, 'fieldTypeId' => 4, 'name' => 'リリース日', 'value' => '2026-12-01T00:00:00Z'],
    ['id' => 105, 'fieldTypeId' => 5, 'name' => '環境', 'value' => ['id' => 2, 'name' => '検証']],
    ['id' => 106, 'fieldTypeId' => 6, 'name' => 'ブラウザ', 'value' => [['id' => 5, 'name' => 'Safari'], ['id' => 3, 'name' => 'Chrome']]],
    ['id' => 107, 'fieldTypeId' => 7, 'name' => '確認', 'value' => [['id' => 7, 'name' => 'API']], 'otherValue' => null],
    ['id' => 108, 'fieldTypeId' => 8, 'name' => '重さ', 'value' => null],
]];
$json = A::values($issue, $map, '{"5":"not Backlog\'s","21":"old"}');
$v = json_decode($json, true);
check('text, sentence (one kind of new line), number rounded, date', $v['20'] === 'Windows 11' && $v['21'] === "1. 開く\n2. 押す" && $v['22'] === 3.46 && $v['23'] === '2026-12-01', $v);
check('lists by name, several in the order of the choices', $v['24'] === '検証' && $v['25'] === ['Chrome', 'Safari'] && $v['26'] === ['API'], $v);
check('an empty field empties its attribute; one not Backlog\'s stays', !isset($v['27']) && $v['5'] === "not Backlog's", $v);
check('keys in the order of the ids', array_map('strval', array_keys($v)) === ['5', '20', '21', '22', '23', '24', '25', '26'], array_keys($v));
check('a choice the list has not is left out', A::value($map['105'], ['name' => '本番2']) === null);
check('no fields at all: what is not Backlog\'s stays, nothing else', A::values(['customFields' => []], $map, '{"5":"x","22":1}') === '{"5":"x"}');
check('nothing left: an empty string', A::values(['customFields' => []], $map, '') === '');
check('a number that is not one', A::value($map['103'], 'abc') === null);

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);

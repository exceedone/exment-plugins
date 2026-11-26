<?php

/**
 * ExmentPM: the custom attributes of a project's issues (ISS02939) - a definition in one shape,
 * a value against its definition, a write checked (required, people of the project), the values
 * read and written, and an issue moved to another project - without a database.
 *
 *   php project-management/tools/test-pm-attributes.php
 */

use App\Plugins\ExmentPM\PortalAttributes as A;

require __DIR__ . '/../ExmentPM/PortalAttributes.php';

$failed = 0;
$check = function ($name, $cond, $extra = '') use (&$failed) {
    echo ($cond ? 'ok   ' : 'FAIL ') . $name . ($cond ? '' : '  ' . var_export($extra, true)) . PHP_EOL;
    if (!$cond) {
        $failed++;
    }
};

$os = A::definition(['id' => 12, 'label' => 'OS', 'type' => 'select', 'options' => ['Windows', 'macOS', 'Linux', 'Windows', ' ']]);
$hours = A::definition(['id' => 13, 'label' => '見積', 'type' => 'number', 'decimals' => 1, 'unit' => 'h']);
$day = A::definition(['id' => 14, 'label' => 'リリース日', 'type' => 'date', 'required' => true]);
$tags = A::definition(['id' => 15, 'label' => 'タグ', 'type' => 'multi_select', 'options' => ['a', 'b', 'c']]);
$who = A::definition(['id' => 16, 'label' => '確認者', 'type' => 'user']);
$note = A::definition(['id' => 17, 'label' => 'メモ', 'type' => 'text', 'max' => 10]);
$gone = A::definition(['id' => 18, 'label' => '古い', 'type' => 'text', 'deleted' => true]);
$defs = [$os, $hours, $day, $tags, $who, $note, $gone];

// ---- a definition
$check('choices: trimmed, no twice, none empty', $os['options'] === ['Windows', 'macOS', 'Linux'], $os['options']);
$check('a number keeps its unit and decimals', $hours['unit'] === 'h' && $hours['decimals'] === 1);
$check('an unknown kind is text', A::definition(['type' => 'html'])['type'] === 'text');

// ---- a value
$check('a choice of the list', A::normalize($os, 'Linux') === ['Linux', null]);
$check('a choice not of the list', A::normalize($os, 'BeOS')[1] === 'attr_choice');
$check('a number, rounded to its decimals', A::normalize($hours, '3.25') === [3.3, null], A::normalize($hours, '3.25'));
$check('not a number', A::normalize($hours, '3h')[1] === 'attr_number' && A::normalize($hours, true)[1] === 'attr_number');
$check('a date as Y-m-d only', A::normalize($day, '2026-12-01') === ['2026-12-01', null] && A::normalize($day, '2026-02-30')[1] === 'attr_date' && A::normalize($day, '12/1')[1] === 'attr_date');
$check('several choices, in the order of the list, once each', A::normalize($tags, ['c', 'a', 'c']) === [['a', 'c'], null]);
$check('several choices, one not of the list', A::normalize($tags, ['a', 'z'])[1] === 'attr_choice');
$check('users: ids', A::normalize($who, ['7', 9, '7']) === [[7, 9], null] && A::normalize($who, ['x'])[1] === 'attr_user');
$check('text: too long', A::normalize($note, str_repeat('あ', 11))[1] === 'attr_text_long' && A::normalize($note, " ok \r\n")[0] === 'ok');
$check('empty is nothing', A::normalize($os, '') === [null, null] && A::normalize($tags, []) === [null, null]);

// ---- the column
$check('decode: ids only, empties out', A::decode('{"12":"Linux","x":1,"13":"","15":[]}') === ['12' => 'Linux']);
$check('decode: not JSON is nothing', A::decode('Windows') === [] && A::decode(null) === []);
$check('encode: in the order of the ids, nothing is empty', A::encode(['15' => ['a'], '12' => 'Linux']) === '{"12":"Linux","15":["a"]}' && A::encode([]) === '');

// ---- a write
[$json, $errors] = A::check($defs, '{"12":"Linux","13":"2","14":"2026-12-01","16":[7]}', '{"18":"keep me"}', [7, 9]);
$check('a good write: kept, the deleted one too', $errors === [] && A::decode($json) === ['12' => 'Linux', '13' => 2, '14' => '2026-12-01', '16' => [7], '18' => 'keep me'], [$json, $errors]);
[, $errors] = A::check($defs, '{"12":"Linux"}', '', null);
$check('a required one missing', count($errors) === 1 && $errors[0] === ['リリース日', 'attr_required'], $errors);
[, $errors] = A::check($defs, '{"14":"2026-12-01","16":[7,8]}', '{"16":[8]}', [7, 9]);
$check('a person kept from before may stay, a new one must be of the project', $errors === [], $errors);
[, $errors] = A::check($defs, '{"14":"2026-12-01","16":[5]}', '', [7, 9]);
$check('a new person not of the project', $errors === [['確認者', 'attr_user']], $errors);
[$json] = A::check($defs, '{"14":"2026-12-01","99":"other project"}', '{"99":"other project"}', null);
$check('a value of an attribute not here stays as it was', A::decode($json)['99'] === 'other project');

// ---- as read
$names = [7 => '山田', 9 => '佐藤'];
$check('text: a number with its unit', A::text($hours, 1234.5) === '1,234.5 h');
$check('text: several choices', A::text($tags, ['a', 'c']) === 'a、c');
$check('text: people by name', A::text($who, [7, 9, 3], $names) === '山田、佐藤、#3');

// ---- a move
$to = [
    A::definition(['id' => 40, 'label' => 'os', 'type' => 'select', 'options' => ['Linux']]),
    A::definition(['id' => 41, 'label' => '確認者', 'type' => 'user']),
    A::definition(['id' => 42, 'label' => '見積', 'type' => 'text']),
];
[$moved, $dropped] = A::move($defs, $to, '{"12":"Linux","13":3,"16":[7,9],"17":"x"}', [9]);
$check('a move: same name and kind kept, a person of the new project only', A::decode($moved) === ['40' => 'Linux', '41' => [9]], $moved);
$check('a move: the others named', $dropped === ['見積', 'メモ'], $dropped);
[$moved, $dropped] = A::move($defs, $to, '{"12":"Windows"}');
$check('a move: a choice the new list has not is dropped', $moved === '' && $dropped === ['OS']);

echo PHP_EOL . ($failed === 0 ? 'all ok' : $failed . ' failed') . PHP_EOL;
exit($failed === 0 ? 0 : 1);

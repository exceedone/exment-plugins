<?php
// ExmentAttachmentExport: the names in the zip, and a zip made for a table (the records of a list).
//
// Run inside a test Exment (docker-exment), from the Exment folder:
//   php /path/to/attachment-export/tools/test-attachment-export.php [table_name]
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!class_exists('App\Plugins\ExmentAttachmentExport\Plugin')) {
    require __DIR__ . '/../ExmentAttachmentExport/Plugin.php';
}
use App\Plugins\ExmentAttachmentExport\Plugin;
use Exceedone\Exment\Model\CustomTable;

$fail = 0;
function ok($cond, $label, $detail = '')
{
    global $fail;
    echo ($cond ? 'ok   ' : 'FAIL ') . $label . ($cond ? '' : '  -> ' . $detail) . "\n";
    if (!$cond) {
        $fail++;
    }
}

ok(Plugin::safeName('a/b\c:d*e?"<>|') === 'a_b_c_d_e_____', 'no path and no characters Windows refuses', Plugin::safeName('a/b\c:d*e?"<>|'));
ok(Plugin::safeName(' .. ') === 'file', 'an empty name is "file"');
$names = [];
ok(Plugin::uniqueName($names, 'r_1/a.pdf') === 'r_1/a.pdf', 'the first name as it is');
ok(Plugin::uniqueName($names, 'r_1/a.pdf') === 'r_1/a (2).pdf', 'the same name: (2)');
ok(Plugin::uniqueName($names, 'r_1/A.PDF') === 'r_1/A (3).PDF', 'not by case either (Windows)');
ok(Plugin::uniqueName($names, 'r_2/a.pdf') === 'r_2/a.pdf', 'another folder');
ok(strpos(Plugin::readme(3, 2097152, 'files', 3, 1048576), '3') !== false, 'the readme says the limit');

// a zip for a table of the test data
$table = CustomTable::getEloquent($argv[1] ?? 'information');
if (isset($table)) {
    $login = \Exceedone\Exment\Model\LoginUser::query()->first();
    if (isset($login)) {
        \Auth::guard('admin')->login($login);
    }
    $records = $table->getValueModel()->newQuery()->limit(20)->get();
    $plugin = new Plugin(null, $table);
    $plugin->defaultProvider(new class ($records) {
        private $records;
        public function __construct($records)
        {
            $this->records = $records;
        }
        public function getRecords()
        {
            return $this->records;
        }
    });
    $path = $plugin->execute();
    $zip = new ZipArchive();
    ok($zip->open($path) === true, 'the zip opens');
    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[] = $zip->getNameIndex($i);
    }
    $zip->close();
    ok(in_array('_README.txt', $entries, true), 'the readme is in the zip');
    foreach ($entries as $entry) {
        if ($entry !== '_README.txt') {
            ok(preg_match('#^[^/]+_\d+/[^/]+$#', $entry) === 1, 'a file in the folder of its record: ' . $entry);
        }
    }
    echo count($entries) - 1, " file(s) of ", count($records), " record(s) of ", $table->table_name, "\n";
    @unlink($path);
    ok(preg_match('/^' . preg_quote($table->table_name, '/') . '_attachments_\d{14}\.zip$/', $plugin->getFileName()) === 1, 'the name of the download', $plugin->getFileName());
}
echo $fail === 0 ? "all ok\n" : "$fail failed\n";
exit($fail === 0 ? 0 : 1);

<?php
// ExmentPM: Exment's messages for the next page, drawn by the portal (PortalFlash, _flash.blade.php).
// Exment answers a form sent with an expired token by going back with 「ページの有効期限が切れました。…」
// (admin_error); the portal's screens showed nothing. Plain PHP, no Exment needed:
//   php project-management/tools/test-pm-flash.php
require __DIR__ . '/../ExmentPM/PortalFlash.php';
use App\Plugins\ExmentPM\PortalFlash as F;

// admin_error() and its siblings keep a MessageBag of title and message; only its get() is read.
class PmFlashBag
{
    private $items;
    public function __construct(array $items) { $this->items = $items; }
    public function get($key) { return isset($this->items[$key]) ? [$this->items[$key]] : []; }
}

$ok = 0; $bad = 0;
$check = function ($label, $cond, $extra = '') use (&$ok, &$bad) { echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n"; $cond ? $ok++ : $bad++; };

$expired = new PmFlashBag(['title' => 'エラー', 'message' => 'ページの有効期限が切れました。お手数ですが、再入力を行ってください。']);
$m = F::fromValues(['error' => $expired]);
$check('the expired page message is shown, with its title', $m === [['type' => 'error', 'title' => 'エラー', 'message' => 'ページの有効期限が切れました。お手数ですが、再入力を行ってください。']], $m);

$check('nothing in the session: nothing shown', F::fromValues([]) === [] && F::fromValues(['error' => null, 'success' => false]) === []);
$check('an empty bag: nothing shown', F::fromValues(['error' => new PmFlashBag([])]) === []);

$m = F::fromValues(['success' => 'Saved', 'error' => $expired, 'info' => new PmFlashBag(['message' => 'Note'])]);
$check('several: error first, then success and info', array_column($m, 'type') === ['error', 'success', 'info'], $m);
$check('a plain string is a message without a title', $m[1] === ['type' => 'success', 'title' => '', 'message' => 'Saved'], $m[1]);

$m = F::fromValues(['warning' => new PmFlashBag(['title' => 'T', 'message' => 'line 1<br>line 2 <a href="/x">link</a> &amp; <script>alert(1)</script>'])]);
$check('markup becomes text, a <br> a line break', $m[0]['message'] === "line 1\nline 2 link & alert(1)", $m[0]['message']);
$check('no tag is left for the page (it writes the text escaped)', strpos($m[0]['message'], '<') === false);

// a message marked as HTML: an Htmlable (Illuminate\Support\HtmlString), which the bag gives in an array
$html = new class {
    public function toHtml() { return 'line 1<br><b>bold</b> &amp; more'; }
};
$m = F::fromValues(['info' => new PmFlashBag(['title' => 'T', 'message' => $html])]);
$check('a message marked as HTML is read, as text', ($m[0]['message'] ?? null) === "line 1\nbold & more", $m);

$m = F::fromValues(['error' => str_repeat('あ', 5000)]);
$check('a long message is cut', mb_strlen($m[0]['message']) <= F::MAX + 1, mb_strlen($m[0]['message']));
$check('a key that is not a message is not read', F::fromValues(['toastr' => 'x', 'errors' => 'y']) === []);

// the views: every screen with the top bar, and the settings page, draw the messages
$views = __DIR__ . '/../ExmentPM/resources/views/';
$flash = file_get_contents($views . '_flash.blade.php');
$check('_topbar includes _flash', strpos(file_get_contents($views . '_topbar.blade.php'), "view('_flash')") !== false);
$check('the settings page includes _flash', strpos(file_get_contents($views . 'settings.blade.php'), "view('_flash')") !== false);
$check('_flash reads PortalFlash', strpos($flash, 'PortalFlash::messages()') !== false);
$check('_flash writes the messages escaped ({{ }}, never {!! !!})', strpos($flash, '{!!') === false && strpos($flash, "{{ \$m['message'] }}") !== false);
$check("Exment's copy above the portal is hidden only when the portal drew them", strpos($flash, 'section.content:has(.pp-flash-list) > .alert{display:none;}') !== false);

// the words, in ja and en
foreach (['ja', 'en'] as $locale) {
    $lang = require __DIR__ . "/../ExmentPM/resources/lang/$locale/pmportal.php";
    $check("$locale: flash.reload and flash.close", is_string($lang['flash']['reload'] ?? null) && $lang['flash']['reload'] !== ''
        && is_string($lang['flash']['close'] ?? null) && $lang['flash']['close'] !== '');
    $check("$locale: http_error.expired", is_string($lang['http_error']['expired'] ?? null) && $lang['http_error']['expired'] !== '');
}

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);

<?php
// ExmentPM の通知の受け取り方（ISS02863・ISS02931）の決め方を、Exment なしで試す: php project-management/tools/test-pm-notify-prefs.php
if (!function_exists('array_get')) {
    function array_get($array, $key, $default = null)
    {
        if (!is_array($array)) {
            return $default;
        }
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        foreach (explode('.', $key) as $part) {
            if (!is_array($array) || !array_key_exists($part, $array)) {
                return $default;
            }
            $array = $array[$part];
        }
        return $array;
    }
}
require __DIR__ . '/../ExmentPM/PortalNotifyPrefs.php';
use App\Plugins\ExmentPM\PortalNotifyPrefs as P;

$ok = 0; $bad = 0;
$check = function ($label, $cond, $extra = '') use (&$ok, &$bad) { echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : "  $extra", "\n"; $cond ? $ok++ : $bad++; };
$d = function ($choice, $prefs, $event = 'comment', $pid = 1, $now = '12:00', $teams = true) { return P::decide($choice, $prefs, $event, $pid, $now, $teams); };

// the choice of the menu, as before
$check('bell only', $d('bell', []) === ['bell' => true, 'mail' => false, 'teams' => false]);
$check('mail too', $d('mail', []) === ['bell' => true, 'mail' => true, 'teams' => false]);
$check('teams too', $d('teams', []) === ['bell' => true, 'mail' => false, 'teams' => true]);
$check('both', $d('both', []) === ['bell' => true, 'mail' => true, 'teams' => true]);
$check('teams off on the site', $d('both', [], 'comment', 1, '12:00', false)['teams'] === false);

// per event
$p = ['events' => ['comment' => ['mail' => false, 'teams' => false], 'remind' => ['mail' => true, 'teams' => false]]];
$check('a comment: nothing but the bell', $d('both', $p) === ['bell' => true, 'mail' => false, 'teams' => false]);
$check('a reminder: mail', $d('bell', $p, 'remind') === ['bell' => true, 'mail' => true, 'teams' => false]);
$check('another event: the menu', $d('mail', $p, 'new')['mail'] === true);

// a project muted
$m = ['projects' => ['7' => 'mute']];
$check('muted project: no mail, no Teams', $d('both', $m, 'comment', 7) === ['bell' => true, 'mail' => false, 'teams' => false]);
$check('another project: as before', $d('both', $m, 'comment', 8)['mail'] === true);

// quiet hours
$q = ['quiet' => ['from' => '22:00', 'to' => '07:00']];
$check('at 23:30: quiet', $d('both', $q, 'comment', 1, '23:30')['mail'] === false);
$check('at 06:59: quiet', $d('both', $q, 'comment', 1, '06:59')['teams'] === false);
$check('at 07:00: not', $d('both', $q, 'comment', 1, '07:00')['mail'] === true);
$check('at 12:00: not', $d('both', $q, 'comment', 1, '12:00')['mail'] === true);
$check('daytime hours 12:00-13:00', P::quietNow(['from' => '12:00', 'to' => '13:00'], '12:30') && !P::quietNow(['from' => '12:00', 'to' => '13:00'], '13:30'));
$check('the same time twice: never', !P::quietNow(['from' => '09:00', 'to' => '09:00'], '09:00'));
$check('not a time: never', !P::quietNow(['from' => 'x', 'to' => '07:00'], '23:00'));

// what is kept from a form
$c = P::clean([
    'events' => ['comment' => ['own' => '1', 'mail' => '1'], 'new' => ['mail' => '1'], 'bogus' => ['own' => '1']],
    'projects' => ['7' => 'mute', '8' => 'normal', 'x' => 'mute'],
    'quiet' => ['on' => '1', 'from' => '22:00', 'to' => '7:00'],
]);
$check('only events set on their own', array_keys($c['events']) === ['comment'] && $c['events']['comment'] === ['mail' => true, 'teams' => false]);
$check('only muted projects', $c['projects'] === ['7' => 'mute']);
$check('quiet hours padded', $c['quiet'] === ['from' => '22:00', 'to' => '07:00']);
$check('quiet hours off', P::clean(['quiet' => ['from' => '22:00', 'to' => '07:00']])['quiet'] === null);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);

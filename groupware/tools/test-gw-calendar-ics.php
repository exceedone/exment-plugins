<?php
// ExmentGroupware: the calendar's ICS export and its ICS links (ISS02947 part 5), without Exment:
// the text written (escaping, folding, all day, UTC, what a busy block leaves out), the ICS read
// (RRULE, EXDATE, RECURRENCE-ID, all day, TZID, broken text), the address check (HostGuard: no
// private, loopback or link-local address, a redirect checked again), the fetch with a fake
// client (ETag, size), and a link read into the calendar and exported again, on an in-memory
// stand-in of the database. No network: the hosts are *.invalid and the client is a fake.
//
//   php groupware/tools/test-gw-calendar-ics.php

namespace Illuminate\Support\Facades {
    // ---- stand-ins of Laravel's facades, enough for the code under test ----------------
    class GwFakeCollection implements \IteratorAggregate, \Countable
    {
        public $items;

        public function __construct(array $items)
        {
            $this->items = array_values($items);
        }

        public function getIterator(): \Iterator
        {
            return new \ArrayIterator($this->items);
        }

        public function count(): int
        {
            return count($this->items);
        }

        public function isEmpty(): bool
        {
            return empty($this->items);
        }

        public function all(): array
        {
            return $this->items;
        }

        public function pluck($k): self
        {
            return new self(array_map(function ($r) use ($k) {
                return is_object($r) ? $r->$k : $r[$k];
            }, $this->items));
        }

        public function map(callable $f): self
        {
            return new self(array_map($f, $this->items));
        }
    }

    class GwFakeQuery
    {
        /** @var string|null */
        public $table;
        /** @var array<int, array{0: string, 1: callable}> */
        public $wheres = [];
        public $order = [];
        public $lim = null;

        public function __construct(?string $table)
        {
            $this->table = $table;
        }

        protected static function value($v)
        {
            if ($v instanceof \DateTimeInterface) {
                return $v->format('Y-m-d H:i:s');
            }
            if (is_object($v) && method_exists($v, 'format')) {
                return $v->format('Y-m-d H:i:s');
            }
            if (is_bool($v)) {
                return $v ? 1 : 0;
            }
            return $v;
        }

        protected function add(string $bool, callable $p): self
        {
            $this->wheres[] = [$bool, $p];
            return $this;
        }

        protected static function cmp($a, string $op, $b): bool
        {
            $b = static::value($b);
            switch ($op) {
                case '=':
                    return $a !== null && strval($a) === strval($b);
                case '<':
                    return $a !== null && strcmp(strval($a), strval($b)) < 0;
                case '<=':
                    return $a !== null && strcmp(strval($a), strval($b)) <= 0;
                case '>':
                    return $a !== null && strcmp(strval($a), strval($b)) > 0;
                case '>=':
                    return $a !== null && strcmp(strval($a), strval($b)) >= 0;
            }
            throw new \LogicException('op ' . $op);
        }

        protected function pred($col, $op, $val, int $n): callable
        {
            if ($col instanceof \Closure) {
                $sub = new self(null);
                $col($sub);
                return function ($r) use ($sub) {
                    return $sub->matches($r);
                };
            }
            if ($n === 2) {
                [$op, $val] = ['=', $op];
            }
            return function ($r) use ($col, $op, $val) {
                return static::cmp($r[$col] ?? null, $op, $val);
            };
        }

        public function where($col, $op = null, $val = null)
        {
            return $this->add('and', $this->pred($col, $op, $val, func_num_args()));
        }

        public function orWhere($col, $op = null, $val = null)
        {
            return $this->add('or', $this->pred($col, $op, $val, func_num_args()));
        }

        public function whereNull($col)
        {
            return $this->add('and', function ($r) use ($col) {
                return ($r[$col] ?? null) === null;
            });
        }

        public function orWhereNull($col)
        {
            return $this->add('or', function ($r) use ($col) {
                return ($r[$col] ?? null) === null;
            });
        }

        public function whereNotNull($col)
        {
            return $this->add('and', function ($r) use ($col) {
                return ($r[$col] ?? null) !== null;
            });
        }

        public function orWhereNotNull($col)
        {
            return $this->add('or', function ($r) use ($col) {
                return ($r[$col] ?? null) !== null;
            });
        }

        public function whereIn($col, $vals)
        {
            $vals = array_map('strval', (array) $vals);
            return $this->add('and', function ($r) use ($col, $vals) {
                return isset($r[$col]) && in_array(strval($r[$col]), $vals, true);
            });
        }

        public function orWhereIn($col, $vals)
        {
            $vals = array_map('strval', (array) $vals);
            return $this->add('or', function ($r) use ($col, $vals) {
                return isset($r[$col]) && in_array(strval($r[$col]), $vals, true);
            });
        }

        public function whereNotIn($col, $vals)
        {
            $vals = array_map('strval', (array) $vals);
            return $this->add('and', function ($r) use ($col, $vals) {
                return !isset($r[$col]) || !in_array(strval($r[$col]), $vals, true);
            });
        }

        /** SQL's precedence: AND before OR. */
        public function matches(array $r): bool
        {
            if (empty($this->wheres)) {
                return true;
            }
            $groups = [[]];
            foreach ($this->wheres as $i => [$bool, $p]) {
                if ($bool === 'or' && $i > 0) {
                    $groups[] = [];
                }
                $groups[count($groups) - 1][] = $p;
            }
            foreach ($groups as $g) {
                $ok = true;
                foreach ($g as $p) {
                    $ok = $ok && $p($r);
                }
                if ($ok) {
                    return true;
                }
            }
            return false;
        }

        public function orderBy($col, $dir = 'asc')
        {
            $this->order[] = [$col, strtolower($dir)];
            return $this;
        }

        public function orderByDesc($col)
        {
            return $this->orderBy($col, 'desc');
        }

        public function limit($n)
        {
            $this->lim = $n;
            return $this;
        }

        /** @return array<int, array<string, mixed>> keyed by id */
        protected function rows(): array
        {
            $out = [];
            foreach (DB::$tables[$this->table] ?? [] as $id => $r) {
                if ($this->matches($r)) {
                    $out[$id] = $r;
                }
            }
            foreach (array_reverse($this->order) as [$col, $dir]) {
                uasort($out, function ($a, $b) use ($col, $dir) {
                    $c = strcmp(strval($a[$col] ?? ''), strval($b[$col] ?? ''));
                    return $dir === 'desc' ? -$c : $c;
                });
            }
            if ($this->lim !== null) {
                $out = array_slice($out, 0, $this->lim, true);
            }
            return $out;
        }

        public function get($cols = ['*'])
        {
            return new GwFakeCollection(array_map(function ($r) {
                return (object) $r;
            }, $this->rows()));
        }

        public function first()
        {
            $r = $this->rows();
            return empty($r) ? null : (object) reset($r);
        }

        public function pluck($col)
        {
            return $this->get()->pluck($col);
        }

        public function count()
        {
            return count($this->rows());
        }

        public function min($col)
        {
            $v = array_column($this->rows(), $col);
            return empty($v) ? null : min($v);
        }

        public function insertGetId(array $v)
        {
            $id = ++DB::$ids[$this->table];
            DB::$tables[$this->table][$id] = ['id' => $id] + array_map([static::class, 'value'], $v);
            return $id;
        }

        public function insert(array $v)
        {
            $this->insertGetId($v);
            return true;
        }

        public function update(array $v)
        {
            $n = 0;
            foreach (array_keys($this->rows()) as $id) {
                DB::$tables[$this->table][$id] = array_map([static::class, 'value'], $v) + DB::$tables[$this->table][$id];
                $n++;
            }
            return $n;
        }

        public function delete()
        {
            $n = 0;
            foreach (array_keys($this->rows()) as $id) {
                unset(DB::$tables[$this->table][$id]);
                $n++;
            }
            return $n;
        }
    }

    class DB
    {
        public static $tables = [];
        public static $ids = [];

        public static function reset(): void
        {
            foreach (['gw_cal_events', 'gw_cal_overrides', 'gw_cal_attendees', 'gw_cal_event_resources', 'gw_cal_links', 'gw_cal_feeds'] as $t) {
                static::$tables[$t] = [];
                static::$ids[$t] = 0;
            }
        }

        public static function table($t)
        {
            return new GwFakeQuery($t);
        }

        public static function transaction(callable $f)
        {
            return $f();
        }

        public static function connection()
        {
            return new class () {
                public function getDatabaseName()
                {
                    return 'fake';
                }
            };
        }
    }

    class Crypt
    {
        public static function encryptString($v)
        {
            return 'enc:' . base64_encode(strrev(strval($v)));
        }

        public static function decryptString($v)
        {
            if (strpos(strval($v), 'enc:') !== 0) {
                throw new \RuntimeException('bad');
            }
            return strrev(base64_decode(substr($v, 4)));
        }
    }

    class Log
    {
        public static $lines = [];

        public static function __callStatic($name, $args)
        {
            static::$lines[] = $name . ': ' . strval($args[0] ?? '');
        }
    }

    class Cache
    {
        public static $v = [];

        public static function add($k, $v, $ttl = null)
        {
            if (array_key_exists($k, static::$v)) {
                return false;
            }
            static::$v[$k] = $v;
            return true;
        }

        public static function forget($k)
        {
            unset(static::$v[$k]);
        }

        public static function increment($k)
        {
            return static::$v[$k] = intval(static::$v[$k] ?? 0) + 1;
        }
    }
}

namespace Exceedone\Exment\Enums {
    class SystemTableName
    {
        const USER = 'user';
    }
}

namespace {
    /** Exment's users, as Links::userActive and Events::orgsOf ask for them: ids there, ids deleted. */
    class GwFakeUser
    {
        public static $active = [5, 7, 9];
        public $id = null;
        public $deleted = false;

        public static function withoutGlobalScopes()
        {
            return new self();
        }

        public function whereNull($col)
        {
            $this->deleted = true;
            return $this;
        }

        public function where($col, $v)
        {
            $this->id = intval($v);
            return $this;
        }

        public function exists()
        {
            return in_array($this->id, static::$active, true);
        }

        public function find($id)
        {
            return null;
        }
    }
    function getModelName($t)
    {
        return GwFakeUser::class;
    }

    class Schema
    {
        public static function hasTable($t)
        {
            return true;
        }
    }

    class GwFakeNow
    {
        public $t;

        public function __construct(string $t)
        {
            $this->t = new \DateTimeImmutable($t, new \DateTimeZone('UTC'));
        }

        public function format($f)
        {
            return $this->t->format($f);
        }

        public function addMinutes($n)
        {
            return new self($this->t->modify('+' . intval($n) . ' minute')->format('Y-m-d H:i:s'));
        }

        public function setTimezone($tz)
        {
            return $this;
        }

        public function addMinute()
        {
            return $this->addMinutes(1);
        }
    }

    $GLOBALS['gw_now'] = '2026-12-01 00:00:00';
    function now()
    {
        return new GwFakeNow($GLOBALS['gw_now']);
    }
    function config($k)
    {
        return $k === 'app.timezone' ? 'Asia/Tokyo' : null;
    }

    $base = __DIR__ . '/../ExmentGroupware';
    foreach (['Mail/MailException', 'Mail/HostGuard', 'FeatureGate', 'Calendar/Rrule', 'Calendar/Occurrences', 'Calendar/Visibility', 'Calendar/Resources', 'Calendar/Events', 'Calendar/Ics', 'Calendar/IcsFetchError', 'Calendar/IcsFetch', 'Calendar/Links', 'Calendar/Feeds'] as $f) {
        require $base . '/' . $f . '.php';
    }

    use App\Plugins\ExmentGroupware\Calendar\Events;
    use App\Plugins\ExmentGroupware\Calendar\Feeds;
    use App\Plugins\ExmentGroupware\Calendar\Ics;
    use App\Plugins\ExmentGroupware\Calendar\IcsFetch;
    use App\Plugins\ExmentGroupware\Calendar\IcsFetchError;
    use App\Plugins\ExmentGroupware\Calendar\Links;
    use App\Plugins\ExmentGroupware\Mail\HostGuard;
    use App\Plugins\ExmentGroupware\Mail\MailException;
    use Illuminate\Support\Facades\DB;

    $failed = 0;
    function gw_ics_check(string $name, bool $ok, $extra = null): void
    {
        global $failed;
        echo ($ok ? 'ok   ' : 'FAIL ') . $name . ($ok || $extra === null ? '' : '  ' . var_export($extra, true)) . "\n";
        $failed += $ok ? 0 : 1;
    }
    /** The error code a call throws, or null. */
    function gw_ics_code(callable $f): ?string
    {
        try {
            $f();
        } catch (MailException|IcsFetchError $e) {
            return $e->getMessage();
        }
        return null;
    }

    // ---- writing: escaping, folding ---------------------------------------------------
    gw_ics_check('escape: backslash, semicolon, comma, line breaks', Ics::escape("a\\b;c,d\r\ne\nf") === 'a\\\\b\\;c\\,d\\ne\\nf', Ics::escape("a\\b;c,d\r\ne\nf"));
    gw_ics_check('escape: control characters dropped', Ics::escape("x\x07y\x00z") === 'xyz');
    $long = 'SUMMARY:' . str_repeat('会議室の予約', 20);
    $folded = Ics::fold($long);
    $ok = true;
    foreach (explode("\r\n", rtrim($folded, "\r\n")) as $i => $l) {
        $ok = $ok && strlen($l) <= 75 && mb_check_encoding($l, 'UTF-8') && ($i === 0 || $l[0] === ' ');
    }
    gw_ics_check('fold: lines of 75 octets at most, never inside a character, continued with a space', $ok, $folded);
    gw_ics_check('fold: unfolds to the same line', str_replace("\r\n ", '', rtrim($folded, "\r\n")) === $long);
    gw_ics_check('fold: a short line as it is', Ics::fold('A:b') === "A:b\r\n");

    // ---- writing: an export ------------------------------------------------------------
    $items = [
        ['id' => 7, 'start' => '2026-12-01 01:00:00', 'end' => '2026-12-01 02:00:00', 'original' => '2026-12-01 01:00:00', 'all_day' => false, 'title' => 'Review; plan, <b>x</b>', 'location' => "Room A\nfloor 3", 'owner' => 5, 'attendees' => [9], 'body' => 'NOTES'],
        ['id' => 8, 'start' => '2026-12-24 00:00:00', 'end' => '2026-12-26 00:00:00', 'original' => '2026-12-24 00:00:00', 'all_day' => true, 'title' => 'Holiday', 'location' => '', 'owner' => 5],
        ['busy' => true, 'start' => '2026-12-02 05:00:00', 'end' => '2026-12-02 06:00:00', 'all_day' => false, 'owner' => 5, 'title' => 'Busy'],
    ];
    $ics = Ics::build($items, ['name' => 'Calendar', 'host' => 'exment.example.invalid', 'busy' => '予定あり', 'now' => '2026-11-28 00:00:00', 'salt' => 'feed1']);
    gw_ics_check('export: a calendar, CRLF lines', strpos($ics, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n") === 0 && substr($ics, -15) === "END:VCALENDAR\r\n");
    gw_ics_check('export: times in UTC', strpos($ics, "DTSTART:20261201T010000Z\r\n") !== false && strpos($ics, "DTEND:20261201T020000Z\r\n") !== false);
    gw_ics_check('export: all day by its days (the end the day after)', strpos($ics, "DTSTART;VALUE=DATE:20261224\r\n") !== false && strpos($ics, "DTEND;VALUE=DATE:20261226\r\n") !== false);
    gw_ics_check('export: the title escaped (markup kept as text)', strpos($ics, 'SUMMARY:Review\\; plan\\, <b>x</b>') !== false);
    gw_ics_check('export: the place with its line break escaped', strpos($ics, 'LOCATION:Room A\\nfloor 3') !== false);
    gw_ics_check('export: the id of an event in its UID', strpos($ics, 'UID:gw-7-20261201010000@exment.example.invalid') !== false);
    gw_ics_check('export: no notes, no attendees', strpos($ics, 'NOTES') === false && strpos($ics, 'ATTENDEE') === false && strpos($ics, 'DESCRIPTION') === false);
    $busyBlock = substr($ics, strpos($ics, 'UID:busy-'));
    $busyBlock = substr($busyBlock, 0, strpos($busyBlock, 'END:VEVENT'));
    gw_ics_check('export: a busy block has the word only, no id', strpos($busyBlock, 'SUMMARY:予定あり') !== false && !preg_match('/gw-\d/', $busyBlock) && strpos($busyBlock, 'LOCATION') === false && strpos($busyBlock, 'CLASS:PRIVATE') !== false, $busyBlock);
    $again = Ics::build($items, ['host' => 'exment.example.invalid', 'now' => '2026-11-28 00:00:00', 'salt' => 'feed1']);
    gw_ics_check('export: the same UIDs each time (a calendar program updates, not duplicates)', preg_match_all('/UID:[^\r]+/', $ics, $a) && preg_match_all('/UID:[^\r]+/', $again, $b) && $a === $b);

    // ---- reading ----------------------------------------------------------------------
    $text = implode("\r\n", [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'BEGIN:VTIMEZONE',
        'TZID:Custom Tokyo',
        'X-LIC-LOCATION:Asia/Tokyo',
        'END:VTIMEZONE',
        'BEGIN:VEVENT',
        'UID:weekly@cal.example.invalid',
        'DTSTART;TZID=Asia/Tokyo:20261201T100000',
        'DTEND;TZID=Asia/Tokyo:20261201T110000',
        'RRULE:FREQ=WEEKLY;BYDAY=TU;WKST=SU;UNTIL=20261229T145959Z',
        'EXDATE;TZID=Asia/Tokyo:20261208T100000',
        'SUMMARY:<script>alert(1)</script> weekly\, sync',
        'DESCRIPTION:line one\nline two',
        'LOCATION:Room',
        ' B',
        'BEGIN:VALARM',
        'TRIGGER:-PT10M',
        'DESCRIPTION:alarm text',
        'END:VALARM',
        'END:VEVENT',
        'BEGIN:VEVENT',
        'UID:weekly@cal.example.invalid',
        'RECURRENCE-ID;TZID=Asia/Tokyo:20261215T100000',
        'DTSTART;TZID=Asia/Tokyo:20261216T150000',
        'DTEND;TZID=Asia/Tokyo:20261216T160000',
        'SUMMARY:moved',
        'END:VEVENT',
        'BEGIN:VEVENT',
        'UID:weekly@cal.example.invalid',
        'RECURRENCE-ID;TZID=Asia/Tokyo:20261222T100000',
        'STATUS:CANCELLED',
        'DTSTART;TZID=Asia/Tokyo:20261222T100000',
        'END:VEVENT',
        'BEGIN:VEVENT',
        'UID:allday@cal.example.invalid',
        'DTSTART;VALUE=DATE:20261231',
        'DTEND;VALUE=DATE:20270102',
        'SUMMARY:New year',
        'END:VEVENT',
        'BEGIN:VEVENT',
        'UID:utc@cal.example.invalid',
        'DTSTART:20261203T230000Z',
        'DURATION:PT1H30M',
        'SUMMARY:UTC call',
        'END:VEVENT',
        'BEGIN:VEVENT',
        'UID:win@cal.example.invalid',
        'DTSTART;TZID="Tokyo Standard Time":20261204T090000',
        'DTEND;TZID="Tokyo Standard Time":20261204T093000',
        'SUMMARY:Outlook',
        'END:VEVENT',
        'BEGIN:VEVENT',
        'UID:custom@cal.example.invalid',
        'DTSTART;TZID=Custom Tokyo:20261205T090000',
        'DTEND;TZID=Custom Tokyo:20261205T100000',
        'SUMMARY:custom zone',
        'END:VEVENT',
        'BEGIN:VEVENT',
        'UID:old@cal.example.invalid',
        'DTSTART:20250101T000000Z',
        'DTEND:20250101T010000Z',
        'SUMMARY:long ago',
        'END:VEVENT',
        'BEGIN:VEVENT',
        'UID:hourly@cal.example.invalid',
        'DTSTART:20261206T000000Z',
        'DTEND:20261206T001000Z',
        'RRULE:FREQ=HOURLY',
        'SUMMARY:unreadable rule',
        'END:VEVENT',
        'BEGIN:VEVENT',
        'UID:cancelled@cal.example.invalid',
        'STATUS:CANCELLED',
        'DTSTART:20261207T000000Z',
        'SUMMARY:gone',
        'END:VEVENT',
        'BEGIN:VEVENT',
        'SUMMARY:no uid',
        'DTSTART:20261207T000000Z',
        'END:VEVENT',
        'END:VCALENDAR',
    ]) . "\r\n";
    $r = Ics::events($text, 'Asia/Tokyo', '2026-11-01 00:00:00', '2027-06-01 00:00:00');
    $e = $r['events'];
    gw_ics_check('read: the events in the window, one per series (old, cancelled and no UID left out)', array_keys($e) === ['weekly@cal.example.invalid', 'allday@cal.example.invalid', 'utc@cal.example.invalid', 'win@cal.example.invalid', 'custom@cal.example.invalid', 'hourly@cal.example.invalid'], array_keys($e));
    $w = $e['weekly@cal.example.invalid'];
    gw_ics_check('read: TZID time in UTC, the zone kept', $w['starts_at'] === '2026-12-01 01:00:00' && $w['ends_at'] === '2026-12-01 02:00:00' && $w['tz'] === 'Asia/Tokyo', $w);
    gw_ics_check('read: RRULE as Rrule reads it (WKST of no effect dropped, UNTIL a local date)', $w['rrule'] === 'FREQ=WEEKLY;BYDAY=TU;UNTIL=20261229', $w['rrule']);
    gw_ics_check('read: EXDATE as a local date', $w['exdates'] === ['2026-12-08'], $w['exdates']);
    gw_ics_check('read: the text unescaped, the folded line joined, the alarm not mixed in', $w['title'] === '<script>alert(1)</script> weekly, sync' && $w['body'] === "line one\nline two" && $w['location'] === 'RoomB', $w);
    $ov = $w['overrides'];
    gw_ics_check('read: RECURRENCE-ID: one moved with its own title, one cancelled', count($ov) === 2
        && $ov[0]['original_start'] === '2026-12-15 01:00:00' && $ov[0]['starts_at'] === '2026-12-16 06:00:00' && $ov[0]['title'] === 'moved' && !$ov[0]['cancelled']
        && $ov[1]['original_start'] === '2026-12-22 01:00:00' && $ov[1]['cancelled'], $ov);
    $occ = \App\Plugins\ExmentGroupware\Calendar\Occurrences::between(Links::row($w, 'busy') + ['all_day' => 0], '2026-11-30 00:00:00', '2027-01-31 00:00:00', $ov);
    gw_ics_check('read: its occurrences as the calendar works them out', array_column($occ, 'start') === ['2026-12-01 01:00:00', '2026-12-16 06:00:00', '2026-12-29 01:00:00'], array_column($occ, 'start'));
    $a = $e['allday@cal.example.invalid'];
    gw_ics_check('read: all day by its days', $a['all_day'] === true && $a['starts_at'] === '2026-12-31 00:00:00' && $a['ends_at'] === '2027-01-02 00:00:00', $a);
    $u = $e['utc@cal.example.invalid'];
    gw_ics_check('read: UTC with DURATION', $u['starts_at'] === '2026-12-03 23:00:00' && $u['ends_at'] === '2026-12-04 00:30:00' && $u['tz'] === 'UTC', $u);
    gw_ics_check('read: a Windows name of a time zone', $e['win@cal.example.invalid']['starts_at'] === '2026-12-04 00:00:00' && $e['win@cal.example.invalid']['tz'] === 'Asia/Tokyo', $e['win@cal.example.invalid']);
    gw_ics_check('read: a VTIMEZONE of its own, by its X-LIC-LOCATION', $e['custom@cal.example.invalid']['starts_at'] === '2026-12-05 00:00:00');
    gw_ics_check('read: a rule it cannot read: the first occurrence, counted', $e['hourly@cal.example.invalid']['rrule'] === null && $r['rules_unread'] === 1);
    gw_ics_check('read: the broken ones counted', $r['skipped'] === 1, $r['skipped']);
    gw_ics_check('read: floating time in the site\'s zone', Ics::when('20261201T090000', [], 'Asia/Tokyo')['utc'] === '2026-12-01 00:00:00');
    gw_ics_check('read: a bad date is nothing', Ics::when('20261341T990000', [], 'Asia/Tokyo') === null && Ics::when('x', [], 'UTC') === null);
    gw_ics_check('read: WKST that changes a rule is not read differently', Ics::rule('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,SU;WKST=SU', 'UTC', false) === null);

    // malformed: never an error, nothing made up
    $bad = [
        '',
        'not a calendar at all',
        "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:x\r\nDTSTART:garbage\r\nEND:VEVENT\r\n",
        "BEGIN:VCALENDAR\nBEGIN:VEVENT\nUID:y\nDTSTART:20261201T000000Z\nRRULE:FREQ=WEEKLY;BYDAY=XX\n",
        "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\n:no name\r\n;;;:\r\nUID:\"q:x\"\r\nDTSTART;TZID=\"Nowhere/Zone\":20261201T000000\r\nEND:VEVENT\r\nEND:VCALENDAR",
        "\xff\xfe\x00B\x00E\x00G\x00I\x00N",
        str_repeat("BEGIN:VEVENT\r\n", 3000),
    ];
    $ok = true;
    foreach ($bad as $i => $t) {
        try {
            $x = Ics::events($t, 'Asia/Tokyo', '2026-11-01 00:00:00', '2027-06-01 00:00:00');
            $ok = $ok && is_array($x['events']);
        } catch (\Throwable $ex) {
            $ok = false;
            echo '      malformed #' . $i . ': ' . get_class($ex) . ' ' . $ex->getMessage() . "\n";
        }
    }
    gw_ics_check('malformed ICS: no crash', $ok);
    $x = Ics::events($bad[3], 'Asia/Tokyo', '2026-11-01 00:00:00', '2027-06-01 00:00:00');
    gw_ics_check('malformed ICS: a rule it cannot read is only its first occurrence', isset($x['events']['y']) && $x['events']['y']['rrule'] === null);
    $x = Ics::events($bad[4], 'Asia/Tokyo', '2026-11-01 00:00:00', '2027-06-01 00:00:00');
    gw_ics_check('malformed ICS: an unknown TZID is the site\'s zone', ($x['events']['"q:x"']['starts_at'] ?? '') === '2026-11-30 15:00:00', $x['events']);

    // ---- the address check (HostGuard::checkWeb) ----------------------------------------
    $dns = function ($map) {
        return function ($host) use ($map) {
            return $map[$host] ?? [];
        };
    };
    $pub = $dns(['cal.example.invalid' => ['203.0.113.10'], 'v6.example.invalid' => ['2001:4860:4860::8888']]);
    HostGuard::allowPrivate(false);
    gw_ics_check('guard: a public address', HostGuard::checkWeb('https://cal.example.invalid/a.ics', $pub)['ips'] === ['203.0.113.10']);
    gw_ics_check('guard: webcal is https', HostGuard::checkWeb('webcal://cal.example.invalid/a.ics', $pub)['url'] === 'https://cal.example.invalid/a.ics');
    $refused = [
        'http://127.0.0.1/x.ics' => 'private',
        'http://[::1]/x.ics' => 'private',
        'http://169.254.169.254/latest/meta-data' => 'private',
        'http://10.0.0.5/x.ics' => 'private',
        'http://192.168.1.2/x.ics' => 'private',
        'http://172.20.0.1/x.ics' => 'private',
        'http://100.64.0.1/x.ics' => 'private',
        'http://0.0.0.0/x.ics' => 'private',
        'http://[fe80::1]/x.ics' => 'private',
        'http://[fd00::1]/x.ics' => 'private',
        'http://[64:ff9b:1::a00:1]/x.ics' => 'private',
        'http://[2001:0:4136:e378:8000:63bf:3fff:fdd2]/x.ics' => 'private',
        'http://[64:ff9b::7f00:1]/x.ics' => 'private',
        'http://[::ffff:127.0.0.1]/x.ics' => 'private',
        'http://[::ffff:169.254.169.254]/x.ics' => 'private',
        'http://localhost/x.ics' => 'private',
        'http://inside.example.invalid/x.ics' => 'private',
        'http://nowhere.example.invalid/x.ics' => 'dns',
        'ftp://cal.example.invalid/x.ics' => 'scheme',
        'file:///etc/passwd' => 'bad_url',
        'https://user:pw@cal.example.invalid/x.ics' => 'userinfo',
        'https://cal.example.invalid:6379/x.ics' => 'port',
        "https://cal.example.invalid/x\n.ics" => 'bad_url',
    ];
    $inside = $dns(['cal.example.invalid' => ['203.0.113.10'], 'inside.example.invalid' => ['203.0.113.11', '10.1.2.3']]);
    $ok = true;
    foreach ($refused as $url => $code) {
        $got = gw_ics_code(function () use ($url, $inside) {
            HostGuard::checkWeb($url, $inside);
        });
        if ($got !== $code) {
            $ok = false;
            echo '      ' . json_encode($url) . ' gave ' . var_export($got, true) . ", not $code\n";
        }
    }
    gw_ics_check('guard: private, loopback, link-local (also inside IPv6), other schemes, user info, ports: refused', $ok);
    // the mail's setting does not open the internal network to web addresses
    HostGuard::allowPrivate(true);
    gw_ics_check('guard: the mail\'s setting (mail_allow_private) changes nothing for web addresses', gw_ics_code(function () {
        HostGuard::checkWeb('http://10.0.0.5/x.ics');
    }) === 'private' && gw_ics_code(function () {
        HostGuard::checkWeb('http://cal.example.invalid:6379/x.ics', function () {
            return ['203.0.113.10'];
        });
    }) === 'port');
    HostGuard::allowPrivate(false);
    HostGuard::allowPrivateWeb(true);
    gw_ics_check('guard: the internal network with the calendar\'s own setting', gw_ics_code(function () {
        HostGuard::checkWeb('http://10.0.0.5:8080/x.ics');
    }) === null);
    gw_ics_check('guard: other ports never, even with the setting (no port scan)', gw_ics_code(function () {
        HostGuard::checkWeb('http://10.0.0.5:22/x.ics');
    }) === 'port' && gw_ics_code(function () {
        HostGuard::checkWeb('http://10.0.0.5:6379/x.ics');
    }) === 'port');
    gw_ics_check('guard: never loopback nor link-local, even with the setting', gw_ics_code(function () {
        HostGuard::checkWeb('http://127.0.0.1/x.ics');
    }) === 'private' && gw_ics_code(function () {
        HostGuard::checkWeb('http://169.254.169.254/x.ics');
    }) === 'private');
    HostGuard::allowPrivateWeb(false);

    // ---- the fetch, with a fake client -----------------------------------------------------
    $calls = [];
    $site = function (array $pages) use (&$calls) {
        return function ($url, $headers, $opts) use ($pages, &$calls) {
            $calls[] = ['url' => $url, 'headers' => $headers, 'ip' => $opts['ip']];
            return $pages[$url] ?? ['status' => 404, 'headers' => [], 'body' => ''];
        };
    };
    $resolve = $dns(['cal.example.invalid' => ['203.0.113.10'], 'other.example.invalid' => ['203.0.113.20'], 'evil.example.invalid' => ['192.168.0.10']]);
    $ics1 = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTART:20261210T000000Z\r\nDTEND:20261210T010000Z\r\nSUMMARY:one\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    $f = new IcsFetch($site([
        'https://cal.example.invalid/a.ics' => ['status' => 302, 'headers' => ['Location' => '/b.ics'], 'body' => ''],
        'https://cal.example.invalid/b.ics' => ['status' => 200, 'headers' => ['ETag' => '"v1"', 'Last-Modified' => 'Tue, 01 Dec 2026 00:00:00 GMT'], 'body' => $ics1],
    ]), $resolve);
    $got = $f->get('https://cal.example.invalid/a.ics');
    gw_ics_check('fetch: a redirect followed (checked again), the ETag kept', $got['status'] === 200 && $got['etag'] === '"v1"' && count($calls) === 2 && $calls[1]['url'] === 'https://cal.example.invalid/b.ics' && $calls[1]['ip'] === '203.0.113.10', $calls);
    $calls = [];
    $f = new IcsFetch($site(['https://cal.example.invalid/b.ics' => ['status' => 304, 'headers' => [], 'body' => '']]), $resolve);
    $got = $f->get('https://cal.example.invalid/b.ics', '"v1"', 'Tue, 01 Dec 2026 00:00:00 GMT');
    gw_ics_check('fetch: If-None-Match and If-Modified-Since sent; 304 is "not changed"', $got['status'] === 304 && ($calls[0]['headers']['If-None-Match'] ?? '') === '"v1"' && ($calls[0]['headers']['If-Modified-Since'] ?? '') !== '');
    $calls = [];
    $f = new IcsFetch($site([
        'https://cal.example.invalid/a.ics' => ['status' => 301, 'headers' => ['location' => 'http://evil.example.invalid/x.ics'], 'body' => ''],
        'http://evil.example.invalid/x.ics' => ['status' => 200, 'headers' => [], 'body' => $ics1],
    ]), $resolve);
    gw_ics_check('fetch: a redirect to an internal address refused before it is asked', gw_ics_code(function () use ($f) {
        $f->get('https://cal.example.invalid/a.ics');
    }) === 'private' && count($calls) === 1, $calls);
    $f = new IcsFetch($site(['https://cal.example.invalid/a.ics' => ['status' => 302, 'headers' => ['Location' => 'http://169.254.169.254/latest/meta-data/'], 'body' => '']]), $resolve);
    gw_ics_check('fetch: a redirect to the metadata address refused', gw_ics_code(function () use ($f) {
        $f->get('https://cal.example.invalid/a.ics');
    }) === 'private');
    $loop = [];
    for ($i = 0; $i < 10; $i++) {
        $loop['https://cal.example.invalid/' . $i] = ['status' => 302, 'headers' => ['Location' => '/' . ($i + 1)], 'body' => ''];
    }
    $f = new IcsFetch($site($loop), $resolve);
    gw_ics_check('fetch: too many redirects', gw_ics_code(function () use ($f) {
        $f->get('https://cal.example.invalid/0');
    }) === 'redirects');
    $f = new IcsFetch($site(['https://cal.example.invalid/a.ics' => ['status' => 200, 'headers' => [], 'body' => '<html>login</html>']]), $resolve);
    gw_ics_check('fetch: not a calendar', gw_ics_code(function () use ($f) {
        $f->get('https://cal.example.invalid/a.ics');
    }) === 'not_ics');
    $f = new IcsFetch($site(['https://cal.example.invalid/a.ics' => ['status' => 200, 'headers' => [], 'body' => 'BEGIN:VCALENDAR' . str_repeat('x', IcsFetch::MAX_BYTES)]]), $resolve);
    gw_ics_check('fetch: too big', gw_ics_code(function () use ($f) {
        $f->get('https://cal.example.invalid/a.ics');
    }) === 'too_big');
    $f = new IcsFetch(function () {
        throw new IcsFetchError('network');
    }, $resolve);
    gw_ics_check('fetch: no answer (the client\'s time limit)', gw_ics_code(function () use ($f) {
        $f->get('https://cal.example.invalid/a.ics');
    }) === 'network');
    $f = new IcsFetch($site([]), $resolve);
    gw_ics_check('fetch: an HTTP error as its code', gw_ics_code(function () use ($f) {
        $f->get('https://cal.example.invalid/a.ics');
    }) === 'http_404');
    gw_ics_check('fetch: relative redirects', IcsFetch::resolveUrl('https://cal.example.invalid/a/b.ics', 'c.ics') === 'https://cal.example.invalid/a/c.ics'
        && IcsFetch::resolveUrl('https://cal.example.invalid:8443/a/b.ics', '/d.ics') === 'https://cal.example.invalid:8443/d.ics'
        && IcsFetch::resolveUrl('https://cal.example.invalid/a', '//other.example.invalid/e') === 'https://other.example.invalid/e');

    // one time limit for the whole fetch, redirects included (a slow chain of redirects)
    // (a fake clock: each answer takes 6 of the 15 seconds, so the third is the last one asked)
    $hops = [];
    $clock = 1000.0;
    $slow = function ($url, $headers, $opts) use (&$hops, &$clock) {
        $hops[] = $opts['timeout'];
        $clock += 6.0;
        return ['status' => 302, 'headers' => ['Location' => '/n' . count($hops)], 'body' => ''];
    };
    $f = new IcsFetch($slow, $resolve, 15.0, function () use (&$clock) {
        return $clock;
    });
    gw_ics_check('fetch: the time limit is for the whole fetch, not each redirect', gw_ics_code(function () use ($f) {
        $f->get('https://cal.example.invalid/a.ics');
    }) === 'network' && $hops === [15, 9, 3], $hops);

    // ---- a calendar made to be slow ---------------------------------------------------------
    gw_ics_check('rule: COUNT read from outside is 1000 at most', Ics::rule('FREQ=DAILY;COUNT=50000', 'UTC', false) === 'FREQ=DAILY;COUNT=1000');
    $t0 = microtime(true);
    gw_ics_check('repeat_until: UNTIL alone without walking the repeat; an end far away is no end', Events::repeatUntil('FREQ=DAILY;UNTIL=20991231', '2000-01-01 00:00:00', 'UTC', false) === '2100-01-02 00:00:00'
        && Events::repeatUntil('FREQ=DAILY;UNTIL=99991231', '2000-01-01 00:00:00', 'UTC', false) === null && microtime(true) - $t0 < 1.0);
    $old = ['starts_at' => '2000-01-01 01:00:00', 'ends_at' => '2000-01-01 02:00:00', 'tz' => 'UTC', 'rrule' => 'FREQ=DAILY'];
    $t0 = microtime(true);
    $occ = \App\Plugins\ExmentGroupware\Calendar\Occurrences::between($old, '2026-12-01 00:00:00', '2026-12-03 00:00:00');
    gw_ics_check('a daily repeat of 2000 still shows in 2026, at once', array_column($occ, 'start') === ['2026-12-01 01:00:00', '2026-12-02 01:00:00'] && microtime(true) - $t0 < 1.0, array_column($occ, 'start'));
    $occ = \App\Plugins\ExmentGroupware\Calendar\Occurrences::between(['starts_at' => '2001-03-06 01:00:00', 'ends_at' => '2001-03-06 02:00:00', 'tz' => 'UTC', 'rrule' => 'FREQ=WEEKLY;INTERVAL=3;BYDAY=TU'], '2026-11-30 00:00:00', '2027-01-31 00:00:00');
    $weeks = array_map(function ($o) {
        return intdiv(intval((strtotime($o['start']) - strtotime('2001-03-06 01:00:00')) / 86400), 7) % 3;
    }, $occ);
    gw_ics_check('an old repeat every 3 weeks keeps its own weeks', count($occ) === 3 && array_unique($weeks) === [0], array_column($occ, 'start'));
    gw_ics_check('an old monthly and yearly repeat', array_column(\App\Plugins\ExmentGroupware\Calendar\Occurrences::between(['starts_at' => '1990-01-31 00:00:00', 'ends_at' => '1990-02-01 00:00:00', 'all_day' => true, 'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=-1'], '2026-11-01 00:00:00', '2027-01-01 00:00:00'), 'start') === ['2026-11-30 00:00:00', '2026-12-31 00:00:00']
        && array_column(\App\Plugins\ExmentGroupware\Calendar\Occurrences::between(['starts_at' => '1980-12-24 00:00:00', 'ends_at' => '1980-12-25 00:00:00', 'all_day' => true, 'rrule' => 'FREQ=YEARLY;INTERVAL=2'], '2026-01-01 00:00:00', '2029-01-01 00:00:00'), 'start') === ['2026-12-24 00:00:00', '2028-12-24 00:00:00']);
    $big = "BEGIN:VCALENDAR\r\n";
    for ($i = 0; $i < Ics::MAX_EVENTS; $i++) {
        $big .= "BEGIN:VEVENT\r\nUID:dos-$i\r\nDTSTART:20000101T" . sprintf('%02d', $i % 24) . "0000Z\r\nDTEND:20000101T" . sprintf('%02d', $i % 24) . "3000Z\r\nRRULE:FREQ=DAILY;UNTIL=99991231\r\nSUMMARY:x\r\nEND:VEVENT\r\n";
    }
    $big .= "END:VCALENDAR\r\n";
    $t0 = microtime(true);
    $r = Ics::events($big, 'UTC', '2026-11-01 00:00:00', '2027-06-01 00:00:00');
    $plan = Links::plan($r['events'], [], 'busy');
    $took = microtime(true) - $t0;
    gw_ics_check('5000 daily repeats from 2000 to 9999: read and planned in a few seconds', count($plan['insert']) === Ics::MAX_EVENTS && $took < 8.0, $took);
    echo '      (' . round($took, 2) . " s)\n";
    $t0 = microtime(true);
    foreach (array_slice($plan['insert'], 0, 500) as $row) {
        \App\Plugins\ExmentGroupware\Calendar\Occurrences::between($row, '2026-12-01 00:00:00', '2026-12-08 00:00:00');
    }
    gw_ics_check('...and 500 of them shown for a week at once', microtime(true) - $t0 < 5.0, microtime(true) - $t0);
    // COUNT is read once, as UNTIL: 700 weekly series of 1000 from 2013 (a large real calendar)
    $cnt = "BEGIN:VCALENDAR\r\n";
    for ($i = 0; $i < 700; $i++) {
        $cnt .= "BEGIN:VEVENT\r\nUID:cnt-$i\r\nDTSTART;TZID=Asia/Tokyo:20130107T090000\r\nDTEND;TZID=Asia/Tokyo:20130107T100000\r\nRRULE:FREQ=DAILY;BYDAY=MO;COUNT=1000\r\nSUMMARY:x\r\nEND:VEVENT\r\n";
    }
    $cnt .= "END:VCALENDAR\r\n";
    $t0 = microtime(true);
    $r = Ics::events($cnt, 'Asia/Tokyo', '2026-11-01 00:00:00', '2027-06-01 00:00:00', microtime(true) + 10);
    $plan = Links::plan($r['events'], [], 'busy');
    $took = microtime(true) - $t0;
    $one = $plan['insert']['cnt-0'] ?? [];
    gw_ics_check('COUNT read as UNTIL (its last date), once: 700 series of 1000 read and planned well in time', !$r['timed_out'] && count($plan['insert']) === 700
        && ($one['rrule'] ?? '') === 'FREQ=DAILY;BYDAY=MO;UNTIL=20320301' && $took < 8.0, [$took, $one['rrule'] ?? null]);
    echo '      (' . round($took, 2) . " s)\n";
    // (as a weekly rule: walked by weeks, the COUNT reaches its end within the walk's limit)
    $asCount = ['starts_at' => '2013-01-07 00:00:00', 'ends_at' => '2013-01-07 01:00:00', 'tz' => 'Asia/Tokyo', 'rrule' => 'FREQ=WEEKLY;BYDAY=MO;COUNT=1000'];
    $same = true;
    foreach ([['2026-11-01', '2027-01-01'], ['2032-02-01', '2032-04-01'], ['2013-01-01', '2013-02-01']] as [$a, $b]) {
        $same = $same && array_column(\App\Plugins\ExmentGroupware\Calendar\Occurrences::between($asCount, $a . ' 00:00:00', $b . ' 00:00:00'), 'start')
            === array_column(\App\Plugins\ExmentGroupware\Calendar\Occurrences::between($one, $a . ' 00:00:00', $b . ' 00:00:00'), 'start');
    }
    // the 1000th Monday from 2013-01-07 is 2032-03-01 (999 weeks on): there, and nothing after
    $end = array_column(\App\Plugins\ExmentGroupware\Calendar\Occurrences::between($one, '2032-02-20 00:00:00', '2032-04-01 00:00:00'), 'start');
    gw_ics_check('...the same occurrences as the COUNT, its last one (the 1000th) included, none after', $same && $end === ['2032-02-23 00:00:00', '2032-03-01 00:00:00'], $end);
    gw_ics_check('...and its end kept for the queries', ($one['repeat_until'] ?? '') === '2032-03-03 00:00:00', $one['repeat_until'] ?? null);

    $r = Ics::events($big, 'UTC', '2026-11-01 00:00:00', '2027-06-01 00:00:00', microtime(true) - 1);
    gw_ics_check('a read past its time limit stops and gives nothing (so nothing is removed)', $r['timed_out'] === true && $r['events'] === []);

    // ---- a link: the form ------------------------------------------------------------------
    [$fl, $why] = Links::fields(['name' => 'Team', 'url' => 'webcal://cal.example.invalid/a.ics', 'show_as' => 'busy'], true, $resolve);
    gw_ics_check('link form: an address checked, its host kept apart', $why === null && $fl['url'] === 'https://cal.example.invalid/a.ics' && $fl['url_host'] === 'cal.example.invalid', $fl);
    [, $why] = Links::fields(['name' => 'X', 'url' => 'http://evil.example.invalid/a.ics'], true, $resolve);
    gw_ics_check('link form: an internal address refused at once', $why === 'link_private', $why);
    [, $why] = Links::fields(['name' => '', 'url' => 'https://cal.example.invalid/a.ics'], true, $resolve);
    gw_ics_check('link form: a name is needed', $why === 'link_name');
    [, $why] = Links::fields(['name' => 'X', 'show_as' => 'everything'], false, $resolve);
    gw_ics_check('link form: show_as is busy or details', $why === 'link_show_as');
    [$fl, $why] = Links::fields(['name' => 'X', 'url' => '', 'show_as' => 'details', 'enabled' => '0'], false, $resolve);
    gw_ics_check('link form: a change without an address keeps it', $why === null && !isset($fl['url']) && $fl['enabled'] === false);
    gw_ics_check('link: the next read staggered by link, later after failures', Links::nextAt(1, 0, '2026-12-01 00:00:00') === '2026-12-01 00:16:37'
        && Links::nextAt(2, 0, '2026-12-01 00:00:00') !== Links::nextAt(1, 0, '2026-12-01 00:00:00')
        && strcmp(Links::nextAt(1, 3, '2026-12-01 00:00:00'), '2026-12-01 02:00:00') > 0);

    // ---- a link read into the calendar, on the stand-in database ---------------------------------
    DB::reset();
    $GLOBALS['gw_now'] = '2026-12-01 00:00:00';
    $owner = 5;
    $linkId = DB::table('gw_cal_links')->insertGetId(['user_id' => $owner, 'provider' => 'ics', 'name' => 'Team', 'url_enc' => \Illuminate\Support\Facades\Crypt::encryptString('https://cal.example.invalid/a.ics'),
        'url_host' => 'cal.example.invalid', 'show_as' => 'busy', 'enabled' => 1, 'fails' => 0, 'event_count' => 0, 'next_sync_at' => null, 'etag' => null, 'last_modified' => null, 'last_error' => null, 'last_sync_at' => null]);
    gw_ics_check('link: the address is not kept as it is', strpos(json_encode(DB::$tables['gw_cal_links']), 'cal.example.invalid/a.ics') === false);
    $page = ['status' => 200, 'headers' => ['ETag' => '"v1"'], 'body' => $text];
    $calls = [];
    $fake = function ($url, $headers, $opts) use (&$page, &$calls) {
        $calls[] = $headers;
        return $page;
    };
    $res = Links::run(10, new IcsFetch($fake, $resolve));
    $link = DB::table('gw_cal_links')->where('id', $linkId)->first();
    $mine = DB::table('gw_cal_events')->where('link_id', $linkId)->get()->all();
    gw_ics_check('sync: read, its events made with source=link', $res['read'] === 1 && count($mine) === 6 && $mine[0]->source === 'link' && intval($mine[0]->owner_user_id) === $owner && $link->last_error === null && $link->etag === '"v1"', [$res, count($mine), $link]);
    gw_ics_check('sync: shown busy to others (show_as busy)', count(array_filter($mine, function ($r) {
        return $r->visibility !== 'busy';
    })) === 0);
    gw_ics_check('sync: its occurrences changed one by one kept', DB::table('gw_cal_overrides')->count() === 2);
    gw_ics_check('sync: the next read later, staggered', $link->next_sync_at === Links::nextAt($linkId, 0, '2026-12-01 00:00:00'), $link->next_sync_at);
    gw_ics_check('sync: not read again before its time', Links::run(10, new IcsFetch($fake, $resolve))['read'] === 0);

    // what the others see, and the owner
    $other = Events::window('2026-11-30 00:00:00', '2027-01-10 00:00:00', [$owner], 9, '予定あり');
    $leak = json_encode($other, JSON_UNESCAPED_UNICODE);
    gw_ics_check('others: 「予定あり」 only, no title, no id', count($other) > 0 && strpos($leak, 'script') === false && strpos($leak, 'weekly') === false && !isset($other[0]['id']), $other);
    $own = Events::window('2026-11-30 00:00:00', '2027-01-10 00:00:00', [$owner], $owner, '予定あり');
    gw_ics_check('the owner: all of it, not editable here', count($own) === count($other) && !empty($own[0]['id']) && $own[0]['editable'] === false);
    $t = array_values(array_filter($own, function ($x) {
        return strpos($x['title'], 'weekly') !== false;
    }));
    gw_ics_check('the owner: the title with markup is data, as it was (the page shows it as text)', isset($t[0]) && $t[0]['title'] === '<script>alert(1)</script> weekly, sync');

    // the export of the owner's calendar: the outside events shown busy leak nothing
    $feedTok = Feeds::newToken();
    DB::table('gw_cal_feeds')->insert(['user_id' => $owner, 'token_hash' => Feeds::hash($feedTok), 'revoked_at' => null, 'last_used_at' => null]);
    $feedRow = Feeds::find($feedTok);
    $out = Feeds::render($feedRow, '予定あり', 'Calendar', 'exment.example.invalid', '2026-12-01 00:00:00');
    gw_ics_check('export: busy blocks only for busy events (no title, place, notes, attendees)', substr_count($out, 'BEGIN:VEVENT') === count($other)
        && strpos($out, 'script') === false && strpos($out, 'weekly') === false && strpos($out, 'Room') === false && strpos($out, 'line one') === false
        && strpos($out, 'ATTENDEE') === false && strpos($out, 'UID:gw-') === false, $out);

    // a local event open to all, one for attendees only, one of another person attended
    $pub = DB::table('gw_cal_events')->insertGetId(['owner_user_id' => $owner, 'title' => 'All hands', 'location' => 'Hall', 'body' => 'secret notes', 'starts_at' => '2026-12-10 01:00:00', 'ends_at' => '2026-12-10 02:00:00',
        'all_day' => 0, 'tz' => 'Asia/Tokyo', 'rrule' => null, 'repeat_until' => null, 'exdates' => null, 'rdates' => null, 'visibility' => 'all', 'org_id' => null, 'source' => 'local', 'link_id' => null, 'deleted_at' => null]);
    $priv = DB::table('gw_cal_events')->insertGetId(['owner_user_id' => $owner, 'title' => 'Interview', 'location' => 'Room 9', 'body' => 'x', 'starts_at' => '2026-12-11 01:00:00', 'ends_at' => '2026-12-11 02:00:00',
        'all_day' => 0, 'tz' => 'Asia/Tokyo', 'rrule' => 'FREQ=DAILY;COUNT=3', 'repeat_until' => '2026-12-15 00:00:00', 'exdates' => null, 'rdates' => null, 'visibility' => 'attendees', 'org_id' => null, 'source' => 'local', 'link_id' => null, 'deleted_at' => null]);
    DB::table('gw_cal_attendees')->insert(['event_id' => $priv, 'user_id' => 7, 'response' => 'yes']);
    $org = DB::table('gw_cal_events')->insertGetId(['owner_user_id' => 7, 'title' => 'Dept budget', 'location' => '', 'body' => '', 'starts_at' => '2026-12-12 01:00:00', 'ends_at' => '2026-12-12 02:00:00',
        'all_day' => 0, 'tz' => 'Asia/Tokyo', 'rrule' => null, 'repeat_until' => null, 'exdates' => null, 'rdates' => null, 'visibility' => 'org', 'org_id' => 3, 'source' => 'local', 'link_id' => null, 'deleted_at' => null]);
    DB::table('gw_cal_attendees')->insert(['event_id' => $org, 'user_id' => $owner, 'response' => 'yes']);
    $out = Feeds::render($feedRow, '予定あり', 'Calendar', 'exment.example.invalid', '2026-12-01 00:00:00');
    gw_ics_check('export: an event open to all with its title and place, never its notes', strpos($out, 'SUMMARY:All hands') !== false && strpos($out, 'LOCATION:Hall') !== false && strpos($out, 'secret notes') === false);
    gw_ics_check('export: attendees only, and an organization\'s event it attends: busy blocks (3 days of a repeat)', strpos($out, 'Interview') === false && strpos($out, 'Room 9') === false && strpos($out, 'Dept budget') === false
        && substr_count($out, 'DTSTART:2026121') >= 5, $out);
    gw_ics_check('export: repeats expanded, one VEVENT an occurrence, no RRULE written', strpos($out, 'RRULE') === false && strpos($out, 'RECURRENCE-ID') === false);

    // the token: only its hash; a new one stops the old; stopped is unknown
    gw_ics_check('token: 64 random hex, only its hash in the table', Feeds::validToken($feedTok) && strpos(json_encode(DB::$tables['gw_cal_feeds']), $feedTok) === false);
    gw_ics_check('token: an unknown or malformed one finds nothing', Feeds::find(str_repeat('0', 64)) === null && Feeds::find('../../etc') === null && Feeds::find(strtoupper($feedTok)) === null);
    $newTok = Feeds::create($owner);
    gw_ics_check('token: a new address stops the old one', Feeds::find($feedTok) === null && Feeds::find($newTok) !== null && strpos(json_encode(DB::$tables['gw_cal_feeds']), $newTok) === false);
    Feeds::revoke($owner);
    gw_ics_check('token: stopped, it finds nothing (404)', Feeds::find($newTok) === null && Feeds::state($owner) === null);

    // upstream changes: one removed, one changed, the same ETag again (304)
    $GLOBALS['gw_now'] = '2026-12-01 01:00:00';
    $page = ['status' => 200, 'headers' => ['ETag' => '"v2"'], 'body' => str_replace(["SUMMARY:New year", "BEGIN:VEVENT\r\nUID:utc@cal.example.invalid\r\nDTSTART:20261203T230000Z\r\nDURATION:PT1H30M\r\nSUMMARY:UTC call\r\nEND:VEVENT\r\n"], ['SUMMARY:New year 2027', ''], $text)];
    $before = DB::table('gw_cal_events')->where('link_id', $linkId)->where('external_id', 'weekly@cal.example.invalid')->first();
    Links::sync(DB::table('gw_cal_links')->where('id', $linkId)->first(), new IcsFetch($fake, $resolve));
    $ext = DB::table('gw_cal_events')->where('link_id', $linkId)->pluck('external_id')->all();
    gw_ics_check('sync: an event removed there is removed here', !in_array('utc@cal.example.invalid', $ext, true) && count($ext) === 5, $ext);
    gw_ics_check('sync: a changed one changed in place (same row)', DB::table('gw_cal_events')->where('link_id', $linkId)->where('external_id', 'allday@cal.example.invalid')->first()->title === 'New year 2027');
    gw_ics_check('sync: an unchanged one left as it is', DB::table('gw_cal_events')->where('link_id', $linkId)->where('external_id', 'weekly@cal.example.invalid')->first() == $before);
    gw_ics_check('sync: the ETag sent back', ($calls[count($calls) - 1]['If-None-Match'] ?? '') === '"v1"');
    $page = ['status' => 304, 'headers' => [], 'body' => ''];
    $n = DB::table('gw_cal_events')->count();
    Links::sync(DB::table('gw_cal_links')->where('id', $linkId)->first(), new IcsFetch($fake, $resolve));
    gw_ics_check('sync: 304 changes nothing', DB::table('gw_cal_events')->count() === $n && DB::table('gw_cal_links')->where('id', $linkId)->first()->last_error === null);

    // a failure: kept as a code, never the address; the events stay; the next try later
    $page = ['status' => 500, 'headers' => [], 'body' => 'oops'];
    Links::sync(DB::table('gw_cal_links')->where('id', $linkId)->first(), new IcsFetch($fake, $resolve));
    $link = DB::table('gw_cal_links')->where('id', $linkId)->first();
    gw_ics_check('sync: an error kept in last_error (a code), the events kept', $link->last_error === 'http_500' && intval($link->fails) === 1 && DB::table('gw_cal_events')->count() === $n);
    $page = ['status' => 200, 'headers' => [], 'body' => "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:z\r\nDTSTART:bad\r\n"];
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $linkId)->first(), new IcsFetch($fake, $resolve));
    gw_ics_check('sync: a broken calendar does not crash (it reads as empty)', $err === null);
    gw_ics_check('logs: never the address', strpos(implode("\n", \Illuminate\Support\Facades\Log::$lines), 'cal.example.invalid') === false);

    // show_as details: the others see it as an event open to all
    DB::reset();
    $linkId = DB::table('gw_cal_links')->insertGetId(['user_id' => $owner, 'provider' => 'ics', 'name' => 'Open', 'url_enc' => \Illuminate\Support\Facades\Crypt::encryptString('https://cal.example.invalid/a.ics'),
        'url_host' => 'cal.example.invalid', 'show_as' => 'details', 'enabled' => 1, 'fails' => 0, 'event_count' => 0, 'next_sync_at' => null, 'etag' => null, 'last_modified' => null, 'last_error' => null, 'last_sync_at' => null]);
    $page = ['status' => 200, 'headers' => [], 'body' => $ics1];
    Links::run(10, new IcsFetch($fake, $resolve));
    $other = Events::window('2026-12-09 00:00:00', '2026-12-11 00:00:00', [$owner], 9, '予定あり');
    gw_ics_check('show_as details: the others see its title', count($other) === 1 && $other[0]['title'] === 'one' && empty($other[0]['busy']));

    // a person who is gone: the link is not read, the address is never fetched
    DB::reset();
    $calls = [];
    $gone = DB::table('gw_cal_links')->insertGetId(['user_id' => 42, 'provider' => 'ics', 'name' => 'Gone', 'url_enc' => \Illuminate\Support\Facades\Crypt::encryptString('https://cal.example.invalid/a.ics'),
        'url_host' => 'cal.example.invalid', 'show_as' => 'busy', 'enabled' => 1, 'fails' => 0, 'event_count' => 0, 'next_sync_at' => null, 'etag' => null, 'last_modified' => null, 'last_error' => null, 'last_sync_at' => null]);
    $res = Links::run(10, new IcsFetch($fake, $resolve));
    $l = DB::table('gw_cal_links')->where('id', $gone)->first();
    gw_ics_check('a deleted person\'s link: not fetched, kept with a code, tried again a day later', $res['failed'] === 1 && $calls === [] && $l->last_error === 'not_allowed' && strcmp($l->next_sync_at, '2026-12-01 12:00:00') > 0, $l);
    gw_ics_check('a person there / deleted / none', Links::userActive(5) && !Links::userActive(42) && !Links::userActive(0));

    // many events once (an outside calendar) never push a repeat out of the window
    DB::reset();
    for ($i = 0; $i < 5001; $i++) {
        DB::table('gw_cal_events')->insertGetId(['owner_user_id' => $owner, 'title' => 'once ' . $i, 'location' => null, 'body' => null, 'starts_at' => '2026-12-01 00:00:00', 'ends_at' => '2026-12-31 00:00:00',
            'all_day' => 0, 'tz' => 'UTC', 'rrule' => null, 'repeat_until' => null, 'exdates' => null, 'rdates' => null, 'visibility' => 'all', 'org_id' => null, 'source' => 'link', 'link_id' => 1, 'deleted_at' => null]);
    }
    DB::table('gw_cal_events')->insertGetId(['owner_user_id' => $owner, 'title' => 'weekly', 'location' => null, 'body' => null, 'starts_at' => '2026-12-20 01:00:00', 'ends_at' => '2026-12-20 02:00:00',
        'all_day' => 0, 'tz' => 'UTC', 'rrule' => 'FREQ=WEEKLY', 'repeat_until' => null, 'exdates' => null, 'rdates' => null, 'visibility' => 'all', 'org_id' => null, 'source' => 'local', 'link_id' => null, 'deleted_at' => null]);
    $w = Events::window('2026-12-01 00:00:00', '2027-01-01 00:00:00', [$owner], $owner, '予定あり');
    gw_ics_check('the window: a repeat is there with 5000 events once', count(array_filter($w, function ($x) {
        return $x['title'] === 'weekly';
    })) === 2, count($w));

    // a window has at most Events::MAX_ITEMS occurrences: the person's own first, then each link
    // an equal share (one link of many repeats pushes out neither)
    DB::reset();
    $ev = function (array $v) use ($owner) {
        return DB::table('gw_cal_events')->insertGetId($v + ['owner_user_id' => $owner, 'location' => null, 'body' => null, 'ends_at' => '2000-01-01 01:00:00',
            'all_day' => 0, 'tz' => 'UTC', 'repeat_until' => null, 'exdates' => null, 'rdates' => null, 'visibility' => 'all', 'org_id' => null, 'deleted_at' => null]);
    };
    for ($i = 0; $i < 60; $i++) {
        $ev(['title' => 'flood', 'starts_at' => '2000-01-01 00:00:00', 'rrule' => 'FREQ=DAILY', 'source' => 'link', 'link_id' => 1]);
    }
    $ev(['title' => 'other link', 'starts_at' => '2000-01-01 00:00:00', 'rrule' => 'FREQ=DAILY', 'source' => 'link', 'link_id' => 2]);
    $ev(['title' => 'mine', 'starts_at' => '2000-01-01 00:00:00', 'rrule' => 'FREQ=DAILY', 'source' => 'local', 'link_id' => null]);
    $logs = count(\Illuminate\Support\Facades\Log::$lines);
    $t0 = microtime(true);
    $w = Events::window('2026-01-01 00:00:00', '2027-02-05 00:00:00', [$owner], $owner, '予定あり');
    $by = array_count_values(array_column($w, 'title'));
    gw_ics_check('window: 20000 occurrences at most (61 repeats of 400 days would be 24400)', count($w) <= Events::MAX_ITEMS && count($w) > 15000, count($w));
    gw_ics_check('window: the person\'s own all there, the other link all there, the flood cut', ($by['mine'] ?? 0) === 400 && ($by['other link'] ?? 0) === 400 && ($by['flood'] ?? 0) === Events::MAX_ITEMS - 800, $by);
    gw_ics_check('window: the cut is logged', count(\Illuminate\Support\Facades\Log::$lines) > $logs);
    $feedRow = (object) ['id' => 1, 'user_id' => $owner];
    $ics = Feeds::render($feedRow, '予定あり', 'Calendar', 'exment.example.invalid', '2026-12-01 00:00:00');
    gw_ics_check('export of such a calendar: bounded, and quick', substr_count($ics, 'BEGIN:VEVENT') <= Events::MAX_ITEMS && strlen($ics) < 20000000 && microtime(true) - $t0 < 20.0, [substr_count($ics, 'BEGIN:VEVENT'), microtime(true) - $t0]);

    // the pages put every text in as text
    foreach (['calendar.blade.php', 'calendar_links.blade.php'] as $v) {
        $src = file_get_contents($base . '/resources/views/' . $v);
        gw_ics_check('page ' . $v . ': no markup from data (innerHTML and the like)', !preg_match('/innerHTML|outerHTML|insertAdjacentHTML|document\.write/', $src));
    }
    $src = file_get_contents($base . '/resources/views/calendar_links.blade.php');
    gw_ics_check('page: the address of a link is never asked for (only its host)', strpos($src, 'l.url') === false && strpos($src, '.host') !== false);
    $mig = file_get_contents($base . '/database/migrations/2026_11_28_100004_create_gw_cal_links_feeds.php');
    gw_ics_check('tables: the token\'s hash only, the address encrypted', strpos($mig, "'token_hash'") !== false && !preg_match("/string\\('token'/", $mig) && strpos($mig, "'url_enc'") !== false && !preg_match("/\\('url'/", $mig));

    echo $failed === 0 ? "all passed\n" : "$failed failed\n";
    exit($failed === 0 ? 0 : 1);
}

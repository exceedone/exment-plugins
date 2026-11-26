<?php

namespace App\Plugins\ExmentITSupport;

/**
 * The 受付箱 of the help desk (ISS02941): each row of its_category is one, with a kind (IT
 * support inside or outside, a product question, a general question, a quote, a trial key),
 * its own flow of stages, its own SLA and reply templates.
 *
 * A flow is lines of 「段階の名前|状態」: the stage is what the people see and pick, the
 * status (Its::STATUSES) is what the rest of the desk runs on (the SLA clock, the pause, the
 * automation, the customer's view). A stage with resolved or closed ends the flow.
 *
 * No database here: tools/test-queue.php runs it as it is.
 */
class Queue
{
    /** kind => [label, group, the flow when the row has none of its own] */
    const KINDS = [
        'it_internal' => ['IT サポート（社内）', 'it', "受付|new\n対応|open\n確認|pending\n完了|resolved"],
        'it_external' => ['IT サポート（社外）', 'it', "受付|new\n対応|open\nお客様の確認|pending\n完了|resolved"],
        'product' => ['製品の問い合わせ', 'inquiry', "受付|new\n回答|open\n完了|resolved"],
        'general' => ['一般的な質問', 'inquiry', "受付|new\n回答|open\n完了|resolved"],
        'quote' => ['見積', 'inquiry', "受付|new\n見積を作る|open\n送る|pending\n受注|resolved\n失注|closed"],
        'trial' => ['試用キー', 'inquiry', "受付|new\nキーを出す|open\nフォロー|pending\n終了|resolved"],
    ];

    /** The groups of the menu. */
    const GROUPS = [
        'it' => 'IT サポート（社内・社外）',
        'inquiry' => '問い合わせ（製品・見積・試用キー・一般）',
    ];

    const DEFAULT_KIND = 'it_internal';

    /** The keys of Its::STATUSES, here so this class needs nothing else (a test checks they agree). */
    const STATUS_KEYS = ['new', 'open', 'pending', 'on_hold', 'resolved', 'closed'];

    /** The statuses that end a flow. */
    const END_STATUSES = ['resolved', 'closed'];

    public static function kindOf($kind): string
    {
        return isset(static::KINDS[strval($kind)]) ? strval($kind) : static::DEFAULT_KIND;
    }

    public static function groupOf($kind): string
    {
        return static::KINDS[static::kindOf($kind)][1];
    }

    /** @return array<string, string> kind => label */
    public static function kindLabels(): array
    {
        return array_map(function ($k) {
            return $k[0];
        }, static::KINDS);
    }

    /**
     * The stages of a queue: its own flow when it reads, the kind's otherwise.
     *
     * @return array<int, array{name: string, status: string}>
     */
    public static function stages($kind, $flowText): array
    {
        $own = static::parse(strval($flowText ?? ''));
        if (empty(static::check($own))) {
            return $own;
        }

        return static::parse(static::KINDS[static::kindOf($kind)][2]);
    }

    /** @return array<int, array{name: string, status: string}> */
    public static function parse(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $out[] = ['name' => mb_substr($parts[0], 0, 40), 'status' => strval($parts[1] ?? '')];
        }

        return $out;
    }

    /**
     * What is wrong with a flow (none: it can be used).
     *
     * @param array<int, array{name: string, status: string}> $stages
     * @return array<int, string>
     */
    public static function check(array $stages): array
    {
        $errors = [];
        if (count($stages) < 2) {
            $errors[] = '段階は 2 つ以上にしてください。';
        }
        $seen = [];
        $ends = 0;
        foreach ($stages as $i => $s) {
            $n = $i + 1;
            if ($s['name'] === '') {
                $errors[] = "{$n} 行目：段階の名前がありません。";
            } elseif (isset($seen[$s['name']])) {
                $errors[] = "{$n} 行目：「{$s['name']}」が 2 回あります。";
            }
            $seen[$s['name']] = true;
            if (!in_array($s['status'], static::STATUS_KEYS, true)) {
                $errors[] = "{$n} 行目：状態は " . implode('・', static::STATUS_KEYS) . ' のどれかにしてください。';
            }
            if (in_array($s['status'], static::END_STATUSES, true)) {
                $ends++;
            }
        }
        if (!empty($stages) && $ends === 0) {
            $errors[] = '終わりの段階（resolved か closed）を 1 つは入れてください。';
        }

        return $errors;
    }

    /** The status a stage stands for, or null for a name not in the flow. */
    public static function statusOf(array $stages, string $stage): ?string
    {
        foreach ($stages as $s) {
            if ($s['name'] === $stage) {
                return $s['status'];
            }
        }

        return null;
    }

    /**
     * The stage for a status the desk set by itself (a reply, the automation, the customer
     * writing back): the stage as it is when it already stands for that status, else the first
     * stage that does; for an end status with none of its own, the first end stage; else the
     * stage as it is.
     */
    public static function stageFor(array $stages, string $status, string $current): string
    {
        if ($current !== '' && static::statusOf($stages, $current) === $status) {
            return $current;
        }
        foreach ($stages as $s) {
            if ($s['status'] === $status) {
                return $s['name'];
            }
        }
        if (in_array($status, static::END_STATUSES, true)) {
            foreach ($stages as $s) {
                if (in_array($s['status'], static::END_STATUSES, true)) {
                    return $s['name'];
                }
            }
        }
        if ($current !== '' && static::statusOf($stages, $current) !== null) {
            return $current;
        }

        return $stages[0]['name'] ?? '';
    }

    /** How far along the flow a stage is, 0 to 100 (an end stage is 100). */
    public static function progress(array $stages, string $stage): int
    {
        $open = array_values(array_filter($stages, function ($s) {
            return !in_array($s['status'], static::END_STATUSES, true);
        }));
        foreach ($stages as $s) {
            if ($s['name'] === $stage && in_array($s['status'], static::END_STATUSES, true)) {
                return 100;
            }
        }
        foreach ($open as $i => $s) {
            if ($s['name'] === $stage) {
                return count($open) > 0 ? intval(round($i * 100 / count($open))) : 0;
            }
        }

        return 0;
    }

    /**
     * The fields a queue asks for: lines of 「列の名前|必須」 (the columns of the ticket table).
     *
     * @return array<int, array{column: string, required: bool}>
     */
    public static function fields($text): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', strval($text ?? '')) ?: [] as $line) {
            $parts = array_map('trim', explode('|', trim($line)));
            if ($parts[0] === '' || !preg_match('/^[a-z][a-z0-9_]{0,49}$/', $parts[0])) {
                continue;
            }
            $out[$parts[0]] = ['column' => $parts[0], 'required' => in_array(mb_strtolower($parts[1] ?? ''), ['1', 'required', '必須', 'yes'], true)];
        }

        return array_values($out);
    }
}

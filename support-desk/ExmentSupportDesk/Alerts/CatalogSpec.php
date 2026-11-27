<?php

namespace App\Plugins\ExmentSupportDesk\Alerts;

/**
 * The fields of an item of the request catalog (the ManageEngine research of ISS02928, idea 1),
 * without the database, so the CI checks them. An item writes them one a line:
 *
 *   label|type|required|choices
 *
 *   type      text (default), textarea, date, select, yesno, email, number
 *   required  1 / 必須 / yes (else not)
 *   choices   for select: separated by "," or "、"
 *
 * e.g. "対象のシステム|select|必須|メール,VPN,そのほか". The answers become the ticket's text, one
 * "label: value" a line.
 */
class CatalogSpec
{
    const TYPES = ['text', 'textarea', 'date', 'select', 'yesno', 'email', 'number'];

    const MAX_FIELDS = 20;

    /**
     * @return array<int, array{key: string, label: string, type: string, required: bool, choices: array<int, string>}>
     */
    public static function parse(string $spec): array
    {
        $fields = [];
        foreach (preg_split('/\r\n|\r|\n/', $spec) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $label = mb_substr($parts[0], 0, 100);
            if ($label === '') {
                continue;
            }
            $type = strtolower($parts[1] ?? 'text');
            if (!in_array($type, static::TYPES, true)) {
                $type = 'text';
            }
            $required = in_array(mb_strtolower($parts[2] ?? ''), ['1', '必須', 'yes', 'required', 'true'], true);
            $choices = [];
            if ($type === 'select') {
                $choices = array_values(array_unique(array_filter(array_map(function ($c) {
                    return mb_substr(trim($c), 0, 100);
                }, preg_split('/[,、]/u', strval($parts[3] ?? '')) ?: []), function ($c) {
                    return $c !== '';
                })));
                if (empty($choices)) {
                    $type = 'text';
                }
            }
            $fields[] = ['key' => 'f' . (count($fields) + 1), 'label' => $label, 'type' => $type, 'required' => $required, 'choices' => $choices];
            if (count($fields) >= static::MAX_FIELDS) {
                break;
            }
        }

        return $fields;
    }

    /**
     * The answers checked: what is missing or not right, by key.
     *
     * @param array<int, array{key: string, label: string, type: string, required: bool, choices: array<int, string>}> $fields
     * @param array<string, mixed> $input
     * @return array<string, string> key => message
     */
    public static function validate(array $fields, array $input): array
    {
        $errors = [];
        foreach ($fields as $f) {
            $value = $input[$f['key']] ?? null;
            $value = is_scalar($value) ? trim(strval($value)) : '';
            if ($value === '') {
                if ($f['required']) {
                    $errors[$f['key']] = $f['label'] . 'を入れてください。';
                }
                continue;
            }
            if (mb_strlen($value) > ($f['type'] === 'textarea' ? 10000 : 500)) {
                $errors[$f['key']] = $f['label'] . 'が長すぎます。';
            } elseif ($f['type'] === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $errors[$f['key']] = $f['label'] . 'は日付（2026-12-01 の形）で入れてください。';
            } elseif ($f['type'] === 'select' && !in_array($value, $f['choices'], true)) {
                $errors[$f['key']] = $f['label'] . 'は一覧から選んでください。';
            } elseif ($f['type'] === 'yesno' && !in_array($value, ['1', '0'], true)) {
                $errors[$f['key']] = $f['label'] . 'は「はい」か「いいえ」で選んでください。';
            } elseif ($f['type'] === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $errors[$f['key']] = $f['label'] . 'はメールアドレスで入れてください。';
            } elseif ($f['type'] === 'number' && !is_numeric($value)) {
                $errors[$f['key']] = $f['label'] . 'は数で入れてください。';
            }
        }

        return $errors;
    }

    /**
     * The answers as the ticket's text: "label: value" a line (a long text below its label).
     *
     * @param array<int, array{key: string, label: string, type: string, required: bool, choices: array<int, string>}> $fields
     * @param array<string, mixed> $input
     */
    public static function render(array $fields, array $input): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $value = $input[$f['key']] ?? null;
            $value = is_scalar($value) ? trim(strval($value)) : '';
            if ($f['type'] === 'yesno') {
                $value = $value === '1' ? 'はい' : ($value === '0' ? 'いいえ' : '');
            }
            if ($value === '') {
                $value = '—';
            }
            $lines[] = $f['type'] === 'textarea' ? '■ ' . $f['label'] . "\n" . $value : '■ ' . $f['label'] . ': ' . $value;
        }

        return implode("\n", $lines);
    }
}

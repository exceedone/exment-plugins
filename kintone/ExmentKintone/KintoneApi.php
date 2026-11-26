<?php

namespace App\Plugins\ExmentKintone;

/**
 * The kintone REST API as the data sync of Exment reads it (ISS00113): plain PHP, no Exment and
 * no Laravel, so that it is tested on its own (kintone/tools/test-kintone-api.php).
 *
 * The calls go through $send(string $path, array $query): array{0: int, 1: string, 2: string}
 * (the status, the body, the content type), given by the connector (Laravel's Http, checked as a
 * webhook of Exment is). The API token or the password is put in the headers there.
 *
 *   GET /k/v1/app.json?id=           an app (its name)
 *   GET /k/v1/apps.json              the apps (with a password only: a token is for its own apps)
 *   GET /k/v1/app/form/fields.json   the fields of an app
 *   GET /k/v1/records.json           the records (query, at most 500, offset up to 10000)
 *   GET /k/v1/file.json?fileKey=     an attachment
 */
class KintoneApi
{
    /** The records of one call at most (kintone: 500). */
    public const MAX_LIMIT = 500;

    /** The Exment column type of each kintone field type; null: none fits (to fix by hand). */
    public const TYPES = [
        'SINGLE_LINE_TEXT' => 'text',
        'MULTI_LINE_TEXT' => 'textarea',
        'RICH_TEXT' => 'editor',
        'NUMBER' => 'decimal',
        'CALC' => 'decimal',
        'DATE' => 'date',
        'DATETIME' => 'datetime',
        'TIME' => 'time',
        'DROP_DOWN' => 'select',
        'RADIO_BUTTON' => 'select',
        'CHECK_BOX' => 'select',
        'MULTI_SELECT' => 'select',
        'USER_SELECT' => 'user',
        'LINK' => 'url',
        'FILE' => 'file',
        'RECORD_NUMBER' => 'text',
        'STATUS' => 'text',
        'CREATOR' => 'user',
        'MODIFIER' => 'user',
        'CREATED_TIME' => 'datetime',
        'UPDATED_TIME' => 'datetime',
    ];

    /** The types with more values. */
    public const MULTIPLE = ['CHECK_BOX', 'MULTI_SELECT', 'USER_SELECT'];

    /** Not fields of a record (the layout), never offered. */
    public const LAYOUT = ['GROUP', 'LABEL', 'SPACER', 'HR', '__ID__', '__REVISION__', 'CATEGORY', 'STATUS_ASSIGNEE'];

    /** @var callable(string, array<string, mixed>): array{0: int, 1: string, 2: string} */
    protected $send;

    /** @var array<string, array<string, array<string, mixed>>> app id => the fields, once a run */
    protected array $fields = [];

    /**
     * @param callable(string, array<string, mixed>): array{0: int, 1: string, 2: string} $send
     */
    public function __construct(callable $send)
    {
        $this->send = $send;
    }

    /**
     * The host of a subdomain as it may be written ("example", "example.cybozu.com",
     * "https://example.cybozu.com/k/"), null: not one of kintone.
     */
    public static function host(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? '';
        $domain = explode('/', $domain)[0];
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $domain) === 1) {
            $domain .= '.cybozu.com';
        }
        return preg_match('/^[a-z0-9][a-z0-9-]*\.(cybozu\.com|kintone\.com|cybozu-dev\.com|kintone-dev\.com|cybozu\.cn)$/', $domain) === 1 ? $domain : null;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        [$status, $body] = ($this->send)($path, $query);
        $json = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($json) ? strval($json['message'] ?? '') : '';
            throw new \RuntimeException('kintone answered HTTP ' . $status . ($message !== '' ? ': ' . mb_strimwidth($message, 0, 200, '…') : ''), $status);
        }
        if (!is_array($json)) {
            throw new \RuntimeException('kintone did not answer JSON.');
        }
        return $json;
    }

    /**
     * The name of an app.
     */
    public function appName(string $app): string
    {
        return strval($this->get('/k/v1/app.json', ['id' => $app])['name'] ?? $app);
    }

    /**
     * The apps: [id => name] (a password only).
     *
     * @return array<string, string>
     */
    public function apps(): array
    {
        $apps = [];
        $offset = 0;
        do {
            $list = (array)($this->get('/k/v1/apps.json', ['limit' => 100, 'offset' => $offset])['apps'] ?? []);
            foreach ($list as $app) {
                $apps[strval($app['appId'] ?? '')] = strval($app['name'] ?? '');
            }
            $offset += 100;
        } while (count($list) === 100 && $offset < 10000);
        unset($apps['']);
        return $apps;
    }

    /**
     * The fields of an app: code => the property of kintone (type, label, required, options).
     *
     * @return array<string, array<string, mixed>>
     */
    public function fields(string $app): array
    {
        if (!isset($this->fields[$app])) {
            $properties = (array)($this->get('/k/v1/app/form/fields.json', ['app' => $app])['properties'] ?? []);
            $this->fields[$app] = array_filter($properties, function ($property) {
                return is_array($property) && !in_array(strval($property['type'] ?? ''), static::LAYOUT, true);
            });
        }
        return $this->fields[$app];
    }

    /**
     * The code of the field of a type (the one of "updated time": its code may be renamed).
     */
    public function codeOf(string $app, string $type): ?string
    {
        foreach ($this->fields($app) as $code => $property) {
            if (strval($property['type'] ?? '') === $type) {
                return strval($code);
            }
        }
        return null;
    }

    /**
     * The choices of a select field, in their order.
     *
     * @param array<string, mixed> $property
     * @return array<int, string>
     */
    public static function options(array $property): array
    {
        $options = (array)($property['options'] ?? []);
        uasort($options, function ($a, $b) {
            return intval($a['index'] ?? 0) <=> intval($b['index'] ?? 0);
        });
        return array_values(array_map(function ($option, $key) {
            return strval(is_array($option) ? ($option['label'] ?? $key) : $key);
        }, $options, array_keys($options)));
    }

    /**
     * The records changed after the cursor, oldest first: [records, the next cursor, done].
     * The cursor is the updated time and the id of the last record read: a record changed at the
     * same time as the last one is not lost, and none is read twice.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: string|null, 2: bool}
     */
    public function changes(string $app, ?string $cursor, int $limit): array
    {
        $limit = max(1, min(static::MAX_LIMIT, $limit));
        $updated = $this->codeOf($app, 'UPDATED_TIME') ?? '更新日時';
        $at = static::decodeCursor($cursor);
        $condition = '';
        if (isset($at)) {
            $condition = '(' . static::quoteCode($updated) . ' > ' . static::quote($at['t']) . ' or (' . static::quoteCode($updated) . ' = ' . static::quote($at['t']) . ' and $id > ' . intval($at['id']) . ')) ';
        }
        $query = $condition . 'order by ' . static::quoteCode($updated) . ' asc, $id asc limit ' . $limit;
        $records = (array)($this->get('/k/v1/records.json', ['app' => $app, 'query' => $query])['records'] ?? []);
        $next = $cursor;
        $last = end($records);
        if (is_array($last)) {
            $next = static::encodeCursor(strval($last[$updated]['value'] ?? ''), intval($last['$id']['value'] ?? 0));
        }
        return [array_values($records), $next, count($records) < $limit];
    }

    /**
     * The ids of all the records of an app.
     *
     * @return array<int, string>
     */
    public function allIds(string $app): array
    {
        $ids = [];
        $last = 0;
        do {
            $records = (array)($this->get('/k/v1/records.json', ['app' => $app, 'fields' => ['$id'], 'query' => '$id > ' . $last . ' order by $id asc limit ' . static::MAX_LIMIT])['records'] ?? []);
            foreach ($records as $record) {
                $last = intval($record['$id']['value'] ?? 0);
                $ids[] = strval($last);
            }
        } while (count($records) === static::MAX_LIMIT);
        return $ids;
    }

    /**
     * The contents of an attachment.
     */
    public function file(string $fileKey): string
    {
        [$status, $body] = ($this->send)('/k/v1/file.json', ['fileKey' => $fileKey]);
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('kintone answered HTTP ' . $status . ' for an attachment', $status);
        }
        return $body;
    }

    /**
     * The values of a record as the sync reads them: code => value (a user as its code, which is
     * its login name, often its mail address; a select of more values as a list; an attachment as
     * its names), and its id, its times, its attachments.
     *
     * @param array<string, mixed> $record
     * @return array{id: string, values: array<string, mixed>, created: string|null, updated: string|null, files: array<int, array{key: string, name: string}>}
     */
    public static function item(array $record): array
    {
        $values = [];
        $files = [];
        $created = null;
        $updated = null;
        foreach ($record as $code => $field) {
            if (!is_array($field) || !array_key_exists('value', $field)) {
                continue;
            }
            $type = strval($field['type'] ?? '');
            $value = $field['value'];
            switch ($type) {
                case 'USER_SELECT':
                case 'ORGANIZATION_SELECT':
                case 'GROUP_SELECT':
                    $value = array_values(array_map(fn ($one) => strval($one['code'] ?? ''), (array)$value));
                    break;
                case 'CREATOR':
                case 'MODIFIER':
                    $value = strval($value['code'] ?? '');
                    break;
                case 'FILE':
                    foreach ((array)$value as $file) {
                        $files[] = ['key' => strval($file['fileKey'] ?? ''), 'name' => strval($file['name'] ?? '')];
                    }
                    $value = array_values(array_map(fn ($one) => strval($one['name'] ?? ''), (array)$value));
                    break;
                case 'SUBTABLE':
                    // a table in the record: not in 7.3 (listed as "to fix by hand")
                    continue 2;
                case 'CREATED_TIME':
                    $created = strval($value);
                    break;
                case 'UPDATED_TIME':
                    $updated = strval($value);
                    break;
            }
            $values[strval($code)] = $value;
        }
        $id = strval($record['$id']['value'] ?? '');
        unset($values['$id'], $values['$revision']);
        return ['id' => $id, 'values' => $values, 'created' => $created, 'updated' => $updated, 'files' => $files];
    }

    public static function recordUrl(string $host, string $app, string $id): string
    {
        return 'https://' . $host . '/k/' . rawurlencode($app) . '/show#record=' . rawurlencode($id);
    }

    public static function encodeCursor(string $updated, int $id): string
    {
        return strval(json_encode(['t' => $updated, 'id' => $id]));
    }

    /**
     * @return array{t: string, id: int}|null
     */
    public static function decodeCursor(?string $cursor): ?array
    {
        if (!isset($cursor) || $cursor === '') {
            return null;
        }
        $data = json_decode($cursor, true);
        if (!is_array($data) || !isset($data['t']) || preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d(:\d\d)?(\.\d+)?(Z|[+-]\d\d:\d\d)$/', strval($data['t'])) !== 1) {
            return null;
        }
        return ['t' => strval($data['t']), 'id' => intval($data['id'] ?? 0)];
    }

    /**
     * A string of a query: in double quotes, its quotes and backslashes escaped.
     */
    public static function quote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    /**
     * A field code of a query: as it is when it is plain, else refused (a code is letters,
     * digits, "_", "・" and the like; never a quote or a space).
     */
    public static function quoteCode(string $code): string
    {
        if (preg_match('/^[^\s"\'()=<>!,]+$/u', $code) !== 1) {
            throw new \InvalidArgumentException('Not a field code of kintone.');
        }
        return $code;
    }
}

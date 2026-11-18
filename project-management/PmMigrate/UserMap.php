<?php

namespace App\Plugins\PmMigrate;

/**
 * Rewrites the user ids a bundle carries.
 *
 * A user id here is the id of a row on the user table (the custom value), the
 * same number created_user_id, a user column and a revision hold. Nothing in
 * this class touches the database: the importer builds the id => id map and
 * hands it over as a callable, and the exporter passes a callable that only
 * records the ids it is shown.
 */
class UserMap
{
    /**
     * Parse the "old_user_code=new_user_code" lines of the mapping box.
     *
     * "*" (or "default") is the fallback for every user without a line of
     * their own. Blank lines and lines starting with # are ignored.
     *
     * @param string $text
     * @return array<string, string> old user_code => new user_code
     * @throws MigrateException
     */
    public static function parseSpec(string $text): array
    {
        $spec = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $no => $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                throw new MigrateException('ユーザー対応表 ' . ($no + 1) . ' 行目が「旧コード=新コード」の形式ではありません。');
            }

            $old = trim($parts[0]);
            $spec[strtolower($old) === 'default' ? '*' : $old] = trim($parts[1]);
        }

        return $spec;
    }

    /**
     * Map one user id, keeping its type: an int stays an int, a string a string.
     *
     * @param int|string $id
     * @param callable $map int|string => int|string
     * @return int|string
     */
    public static function mapId($id, callable $map)
    {
        $mapped = $map($id);

        return is_int($id) ? intval($mapped) : strval($mapped);
    }

    /**
     * Map the user columns of one custom value.
     *
     * A multiple column holds an array. When every user maps to the same
     * person the array would repeat them, so it is made unique (by value, in
     * first-seen order) and re-indexed.
     *
     * @param array<string, mixed> $value decoded value column
     * @param array<int, string> $userColumns column names that hold user ids
     * @param callable $map int|string => int|string
     * @return array<string, mixed>
     */
    public static function rewriteValue(array $value, array $userColumns, callable $map): array
    {
        foreach ($userColumns as $column) {
            if (!array_key_exists($column, $value)) {
                continue;
            }

            $current = $value[$column];
            if (is_array($current)) {
                $seen = [];
                $result = [];
                foreach ($current as $id) {
                    if (!is_int($id) && !is_string($id) || $id === '') {
                        $result[] = $id;
                        continue;
                    }
                    $mapped = static::mapId($id, $map);
                    if (isset($seen[strval($mapped)])) {
                        continue;
                    }
                    $seen[strval($mapped)] = true;
                    $result[] = $mapped;
                }
                $value[$column] = $result;
            } elseif ((is_int($current) || is_string($current)) && $current !== '') {
                $value[$column] = static::mapId($current, $map);
            }
        }

        return $value;
    }

    /**
     * rewriteValue() on the JSON text of a value column.
     *
     * Text that is not a JSON object is returned as it was. The output keeps
     * the escaping style of the input, so a revision that stored "山" does
     * not turn into a literal character and back-and-forth diffs stay quiet.
     *
     * @param string|null $json
     * @param array<int, string> $userColumns
     * @param callable $map
     * @return string|null
     */
    public static function rewriteValueJson(?string $json, array $userColumns, callable $map): ?string
    {
        if ($json === null || $json === '' || empty($userColumns)) {
            return $json;
        }

        $value = json_decode($json, true);
        // an empty object decodes to [] and would be written back as a list
        if (!is_array($value) || $value === []) {
            return $json;
        }

        $flags = 0;
        if (strpos($json, '\\u') === false) {
            $flags |= JSON_UNESCAPED_UNICODE;
        }
        if (strpos($json, '\\/') === false) {
            $flags |= JSON_UNESCAPED_SLASHES;
        }

        $encoded = json_encode(static::rewriteValue($value, $userColumns, $map), $flags);

        return $encoded === false ? $json : $encoded;
    }
}

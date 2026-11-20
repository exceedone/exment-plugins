<?php

/**
 * Optional: rewrite links to the old address of Exment PM that are stored in
 * the text of issues, comments and wiki pages.
 *
 * Since 1.4.0 the portal lives at {admin}/plugins/exment_pm (it was
 * {admin}/plugins/pm_portal). Exment does not redirect the old address, so a
 * link written into an issue body, a comment or a wiki page before the update
 * ends in "404 not found". This script replaces those links in the stored
 * text. Nothing needs it to run: skip it if broken old links are acceptable.
 *
 * It is a plain PHP script (PDO), it does not boot Exment. Run it on a machine
 * that can reach the database, after a database backup.
 *
 *   # 1. count only (changes nothing)
 *   DB_HOST=... DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=... \
 *     php rewrite-old-links.php --base=https://example.com/admin
 *
 *   # 2. rewrite; the old values are saved to the backup file first
 *   ... php rewrite-old-links.php --base=https://example.com/admin --apply --backup=old-links-backup.json
 *
 *   # 3. undo: put the saved values back
 *   ... php rewrite-old-links.php --rollback=old-links-backup.json
 *
 * --base is the address of the Exment admin, without a slash at the end: the
 * part before "/plugins/pm_portal" (on Exment SaaS there is no "/admin").
 * Only links of this Exment are rewritten, as an absolute URL
 * ({base}/plugins/pm_portal...) or as a path that starts with its path part
 * (/admin/plugins/pm_portal...). Links to another Exment are left alone.
 *
 * Other options:
 *   --from=pm_portal --to=exment_pm   the old and the new uri (these defaults)
 *   --tables=pm_issue,pm_wiki,comment the Exment tables to look in. By default
 *                                     the issue and wiki tables named in the
 *                                     plugin's settings, and "comment"
 *   --bells                           also the text of bell notifications
 *                                     (notify_navbars.notify_body)
 * Environment: DB_HOST, DB_PORT (3306), DB_DATABASE, DB_USERNAME, DB_PASSWORD,
 * DB_SSL_CA (path of a CA file; setting it turns TLS on).
 *
 * What it touches: the "value" JSON of the rows that contain an old link, one
 * key at a time (JSON_SET), and nothing else. updated_at, the revisions (edit
 * history) and the operation log stay as they are.
 */

const PM_UUID = '00da3e93-1a24-4f44-b993-d61367e43649';

/**
 * @param array<int, string> $argv
 * @return array<string, string|bool>
 */
function pm_links_options(array $argv): array
{
    $options = ['from' => 'pm_portal', 'to' => 'exment_pm', 'apply' => false, 'bells' => false];
    foreach (array_slice($argv, 1) as $arg) {
        if (!preg_match('/\A--([a-z]+)(?:=(.*))?\z/s', $arg, $m)) {
            fwrite(STDERR, "Unknown argument: $arg\n");
            exit(2);
        }
        $options[$m[1]] = isset($m[2]) ? $m[2] : true;
    }

    return $options;
}

function pm_links_connect(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_DATABASE') ?: '';
    if ($name === '') {
        fwrite(STDERR, "Set DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD.\n");
        exit(2);
    }
    $attributes = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    $ca = getenv('DB_SSL_CA');
    if (is_string($ca) && $ca !== '') {
        $attributes[PDO::MYSQL_ATTR_SSL_CA] = $ca;
    }

    // utf8mb4, or Japanese text comes back (and is written) broken.
    return new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", getenv('DB_USERNAME') ?: '', getenv('DB_PASSWORD') ?: '', $attributes);
}

/**
 * The pattern for a link of this Exment to the old address: the absolute URL,
 * or the bare path when it is not the tail of some other host's URL. The uri
 * must end there ("pm_portal_git_repos" is a table name, not a link).
 */
function pm_links_pattern(string $base, string $from): string
{
    $path = strval(parse_url($base, PHP_URL_PATH));
    $absolute = preg_quote($base, '~');
    $relative = '(?<![A-Za-z0-9_.:/\-])' . preg_quote($path, '~');

    return '~(' . $absolute . '|' . $relative . ')/plugins/' . preg_quote($from, '~') . '(?![A-Za-z0-9_\-])~';
}

/**
 * The physical tables (exm__...) to look in, keyed by Exment table name.
 *
 * @param array<string, string|bool> $options
 * @return array<string, string>
 */
function pm_links_tables(PDO $db, array $options): array
{
    if (isset($options['tables']) && is_string($options['tables'])) {
        $names = array_filter(array_map('trim', explode(',', $options['tables'])));
    } else {
        $names = ['comment'];
        $statement = $db->prepare('SELECT options FROM plugins WHERE uuid = ?');
        $statement->execute([PM_UUID]);
        $row = $statement->fetch();
        $plugin = is_array($row) ? json_decode(strval($row['options']), true) : null;
        $tables = is_array($plugin) && isset($plugin['portal_setup']['tables']) ? (array) $plugin['portal_setup']['tables'] : [];
        foreach (['issue_table' => 'pm_issue', 'wiki_table' => 'pm_wiki'] as $key => $default) {
            $names[] = isset($tables[$key]) && is_string($tables[$key]) && $tables[$key] !== '' ? $tables[$key] : $default;
        }
    }

    $found = [];
    $statement = $db->prepare('SELECT suuid FROM custom_tables WHERE table_name = ?');
    foreach ($names as $name) {
        $statement->execute([$name]);
        $suuid = $statement->fetchColumn();
        if (!is_string($suuid) || !preg_match('/\A[0-9a-f]+\z/', $suuid)) {
            fwrite(STDERR, "Exment table \"$name\" was not found; skipped.\n");
            continue;
        }
        $found[$name] = 'exm__' . $suuid;
    }

    return $found;
}

/**
 * @param array<string, string|bool> $options
 * @return array<int, array<string, mixed>> one entry per changed key
 */
function pm_links_find(PDO $db, array $options, string $pattern): array
{
    $from = strval($options['from']);
    $to = strval($options['to']);
    $like = '%/plugins/' . addcslashes($from, '%_\\') . '%';
    $changes = [];

    foreach (pm_links_tables($db, $options) as $name => $physical) {
        $statement = $db->prepare("SELECT id, value FROM `$physical` WHERE value LIKE ?");
        $statement->execute([$like]);
        foreach ($statement as $row) {
            $value = json_decode(strval($row['value']), true);
            foreach (is_array($value) ? $value : [] as $key => $text) {
                if (!is_string($text) || !preg_match('/\A[A-Za-z0-9_]+\z/', strval($key))) {
                    continue;
                }
                $new = preg_replace($pattern, '$1/plugins/' . $to, $text);
                if (is_string($new) && $new !== $text) {
                    $changes[] = ['table' => $physical, 'name' => $name, 'id' => intval($row['id']), 'key' => strval($key), 'old' => $text, 'new' => $new];
                }
            }
        }
    }

    if (!empty($options['bells'])) {
        $statement = $db->prepare('SELECT id, notify_body FROM notify_navbars WHERE notify_body LIKE ?');
        $statement->execute([$like]);
        foreach ($statement as $row) {
            $text = strval($row['notify_body']);
            $new = preg_replace($pattern, '$1/plugins/' . $to, $text);
            if (is_string($new) && $new !== $text) {
                $changes[] = ['table' => 'notify_navbars', 'name' => 'notify_navbars', 'id' => intval($row['id']), 'key' => 'notify_body', 'old' => $text, 'new' => $new];
            }
        }
    }

    return $changes;
}

/**
 * Write one text back. $current guards against a row somebody changed in the
 * meantime: such a row is left alone and reported.
 *
 * @param array<string, mixed> $change
 */
function pm_links_write(PDO $db, array $change, string $text, string $current): bool
{
    $table = strval($change['table']);
    if (!preg_match('/\A(exm__[0-9a-f]+|notify_navbars)\z/', $table)) {
        throw new RuntimeException("Unexpected table $table");
    }
    if ($table === 'notify_navbars') {
        $statement = $db->prepare('UPDATE notify_navbars SET notify_body = ? WHERE id = ? AND notify_body = ?');
        $statement->execute([$text, $change['id'], $current]);
    } else {
        $path = '$.' . strval($change['key']);
        $statement = $db->prepare("UPDATE `$table` SET value = JSON_SET(value, '$path', ?) WHERE id = ? AND JSON_UNQUOTE(JSON_EXTRACT(value, '$path')) = ?");
        $statement->execute([$text, $change['id'], $current]);
    }

    return $statement->rowCount() === 1;
}

/**
 * @param array<int, array<string, mixed>> $changes
 * @param string $to 'new' to rewrite, 'old' to roll back
 */
function pm_links_run(PDO $db, array $changes, string $to): int
{
    $from = $to === 'new' ? 'old' : 'new';
    $done = 0;
    $db->beginTransaction();
    try {
        foreach ($changes as $change) {
            if (pm_links_write($db, $change, strval($change[$to]), strval($change[$from]))) {
                $done++;
            } else {
                echo "  skipped (changed since): {$change['name']} id {$change['id']} {$change['key']}\n";
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    return $done;
}

$options = pm_links_options($argv);
$db = pm_links_connect();

if (isset($options['rollback']) && is_string($options['rollback'])) {
    $changes = json_decode(strval(file_get_contents($options['rollback'])), true);
    if (!is_array($changes)) {
        fwrite(STDERR, "Cannot read the backup file.\n");
        exit(1);
    }
    echo 'restored ' . pm_links_run($db, $changes, 'old') . ' of ' . count($changes) . " text(s)\n";
    exit(0);
}

$base = isset($options['base']) && is_string($options['base']) ? rtrim($options['base'], '/') : '';
if (!preg_match('~\Ahttps?://[^/\s]+(/[^\s?#]*)?\z~', $base)) {
    fwrite(STDERR, "Give the address of the Exment admin: --base=https://example.com/admin\n");
    exit(2);
}

$changes = pm_links_find($db, $options, pm_links_pattern($base, strval($options['from'])));
$summary = [];
foreach ($changes as $change) {
    $label = $change['name'] . '.' . $change['key'];
    $summary[$label] = ($summary[$label] ?? 0) + 1;
}
foreach ($summary as $label => $count) {
    echo "$label: $count text(s) with an old link\n";
}
echo 'total: ' . count($changes) . " text(s)\n";

if (empty($options['apply'])) {
    echo "Nothing was changed. Add --apply --backup=<file> to rewrite.\n";
    exit(0);
}
if (!isset($options['backup']) || !is_string($options['backup']) || $options['backup'] === '') {
    fwrite(STDERR, "--apply needs --backup=<file> (the old values are saved there for --rollback).\n");
    exit(2);
}
if (file_exists($options['backup'])) {
    fwrite(STDERR, "The backup file already exists; choose another name.\n");
    exit(2);
}
if (file_put_contents($options['backup'], json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) === false) {
    fwrite(STDERR, "Cannot write the backup file.\n");
    exit(1);
}
echo 'rewrote ' . pm_links_run($db, $changes, 'new') . ' of ' . count($changes) . " text(s); backup: {$options['backup']}\n";

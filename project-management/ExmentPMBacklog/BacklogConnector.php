<?php

namespace App\Plugins\ExmentPMBacklog;

use Carbon\Carbon;
use Exceedone\Exment\Model\SyncConnection;
use Exceedone\Exment\Model\SyncMapping;
use Exceedone\Exment\Services\Sync\ChangesPage;
use Exceedone\Exment\Services\Sync\RemoteItem;
use Exceedone\Exment\Services\Sync\RemoteObject;
use Exceedone\Exment\Services\Sync\SyncConnector;
use Exceedone\Exment\Services\Sync\SyncException;
use Exceedone\Exment\Services\Sync\SyncFinisher;
use Exceedone\Exment\Services\Sync\SyncMappingFields;
use Exceedone\Exment\Services\Sync\SyncWriteResult;
use Exceedone\Exment\Services\Sync\SyncWriter;
use Illuminate\Support\Facades\Cache;

/**
 * The Backlog connector of the data sync of Exment 7.3 (ISS00113): the issues of a Backlog
 * project into the project of ExmentPM, written by this plugin's Syncer as before (the issue,
 * its comments and attachments, the parent, the times and the people of Backlog, the images),
 * run by the data sync (the schedule, the lock, the runs, the dry run, the bell).
 *
 * A mapping of the data sync goes with a connection of this plugin (its mapping of the statuses,
 * the types and the users is made on the page of this plugin): SyncBridge. The links of the issues
 * (pmbl_links) are the ones of this plugin, so an issue taken before is not taken twice.
 *
 * Loaded only when Exment has the data sync (PluginPage::hasSync()).
 */
class BacklogConnector implements SyncConnector, SyncWriter, SyncMappingFields, SyncFinisher
{
    const KEY = 'backlog_pm';

    /** The parents an issue waits for, until the end of its run (the parts may be jobs apart). */
    const PENDING_KEY = 'pmbl_pending_parents_';

    /** @var array<int, Syncer> mapping id => the Syncer of the run, in this process */
    protected array $syncers = [];

    public function key(): string
    {
        return static::KEY;
    }

    public function label(): string
    {
        return Lang::t('sync_label');
    }

    public function settingFields(): array
    {
        return ['space_url' => Lang::t('sync_space_url')];
    }

    public function secretFields(): array
    {
        return ['api_key' => Lang::t('sync_api_key')];
    }

    public function test(SyncConnection $connection): ?string
    {
        try {
            $this->client($connection)->get('space');
            return null;
        } catch (\Throwable $e) {
            return $this->message($e, $connection);
        }
    }

    public function objects(SyncConnection $connection): array
    {
        try {
            $objects = [];
            foreach ((array)$this->client($connection)->get('projects') as $project) {
                $key = strval($project['projectKey'] ?? '');
                if ($key !== '') {
                    $objects[] = new RemoteObject($key, strval($project['name'] ?? $key) . ' (' . $key . ')');
                }
            }
            return $objects;
        } catch (\Throwable $e) {
            throw new SyncException($this->message($e, $connection));
        }
    }

    public function fields(SyncConnection $connection, string $object): array
    {
        // written by Syncer, with the mapping made on the page of this plugin
        return [];
    }

    public function mappingFields(SyncConnection $connection, string $object, SyncMapping $mapping): array
    {
        $options = [];
        foreach (Connections::all() as $conn) {
            $options[strval($conn->id)] = strval($conn->name) . ' (' . strval($conn->backlog_project_key) . ')';
        }
        return ['pmbl_connection' => ['label' => Lang::t('sync_pmbl'), 'type' => 'select', 'options' => $options, 'help' => Lang::t('sync_pmbl_help')]];
    }

    public function checkMappingFields(SyncConnection $connection, string $object, array $values): ?string
    {
        $conn = Connections::find(intval($values['pmbl_connection'] ?? 0));
        if (!$conn || empty(Connections::mapping($conn)['backlog_project_id'] ?? null) || strtoupper($object) !== strtoupper(strval($conn->backlog_project_key))) {
            return Lang::t('sync_no_pmbl');
        }
        $host = fn ($url) => strtolower(strval(parse_url(strval($url), PHP_URL_HOST)));
        if ($host($conn->space_url) !== $host($connection->setting('space_url'))) {
            return Lang::t('sync_other_space');
        }
        return null;
    }

    public function changes(SyncConnection $connection, string $object, ?string $cursor, int $limit): ChangesPage
    {
        $mapping = SyncBridge::mapping($connection, $object);
        if (!isset($mapping)) {
            throw new SyncException(Lang::t('sync_no_pmbl'));
        }
        try {
            $syncer = $this->syncer($mapping, $connection);
            [$issues, $next, $done] = $syncer->changed($cursor, $limit);
            $client = $this->client($connection);
            $items = [];
            foreach ($issues as $issue) {
                $items[] = new RemoteItem(
                    strval($issue['id']),
                    $issue,
                    isset($issue['updated']) ? Carbon::parse($issue['updated']) : null,
                    isset($issue['created']) ? Carbon::parse($issue['created']) : null,
                    $client->issueUrl(strval($issue['issueKey'] ?? ''))
                );
            }
            return new ChangesPage($items, $next, $done);
        } catch (SyncException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SyncException($this->message($e, $connection));
        }
    }

    public function write(SyncMapping $mapping, RemoteItem $item, bool $dry): SyncWriteResult
    {
        $connection = $mapping->connection;
        if (!isset($connection)) {
            throw new SyncException(Lang::t('sync_no_pmbl'));
        }
        try {
            $syncer = $this->syncer($mapping, $connection);
            if ($dry) {
                $link = Links::find($this->client($connection)->host(), 'issue', intval($item->id));
                return new SyncWriteResult($link ? SyncWriteResult::UPDATED : SyncWriteResult::CREATED);
            }
            $done = $syncer->writeOne($item->values);
            if ($done['parent'] > 0) {
                $pending = (array) Cache::get(static::PENDING_KEY . $mapping->id, []);
                $pending[intval($item->id)] = $done['parent'];
                Cache::put(static::PENDING_KEY . $mapping->id, $pending, now()->addDay());
            }
            return new SyncWriteResult(
                $done['kind'] === 'add' ? SyncWriteResult::CREATED : SyncWriteResult::UPDATED,
                $done['id'] > 0 ? $done['id'] : null,
                $syncer->takeProblems()
            );
        } catch (SyncException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SyncException(strval($item->values['issueKey'] ?? $item->id) . ': ' . $this->message($e, $connection));
        }
    }

    public function finished(SyncMapping $mapping, bool $dry, bool $complete): array
    {
        if ($dry) {
            return [];
        }
        $connection = $mapping->connection;
        if (!isset($connection)) {
            return [];
        }
        try {
            $syncer = $this->syncer($mapping, $connection);
            $syncer->resolveParents((array) Cache::pull(static::PENDING_KEY . $mapping->id, []));
            $syncer->dailyCheck($complete);
            unset($this->syncers[$mapping->id]);
            return $syncer->takeProblems();
        } catch (\Throwable $e) {
            throw new SyncException($this->message($e, $connection));
        }
    }

    public function allIds(SyncConnection $connection, string $object): ?array
    {
        // the deleted and moved issues: the daily check of Syncer (finished())
        return null;
    }

    public function attachments(SyncConnection $connection, string $object, RemoteItem $item): array
    {
        // written by Syncer with the issue
        return [];
    }

    public function capabilities(string $object): array
    {
        return ['import' => true, 'export' => false];
    }

    public function callsPerMinute(): int
    {
        return 150;
    }

    protected function syncer(SyncMapping $mapping, SyncConnection $connection): Syncer
    {
        if (!isset($this->syncers[$mapping->id])) {
            $conn = SyncBridge::conn($mapping, $connection);
            if (!$conn) {
                throw new SyncException(Lang::t('sync_no_pmbl'));
            }
            // a command or a worker has no middleware to bind parent_type to a class
            \Exceedone\Exment\Middleware\Morph::defineMorphMap();
            $syncer = new Syncer($conn);
            $syncer->prepare();
            $this->syncers[$mapping->id] = $syncer;
        }
        return $this->syncers[$mapping->id];
    }

    protected function client(SyncConnection $connection): Backlog
    {
        return new Backlog(strval($connection->setting('space_url')), strval($connection->secret('api_key')));
    }

    /**
     * What may be shown of an error: never the API key (a URL in an error has it in its query).
     */
    protected function message(\Throwable $e, SyncConnection $connection): string
    {
        $message = $e instanceof \RuntimeException ? $e->getMessage() : get_class($e);
        $key = strval($connection->secret('api_key'));
        if ($key !== '') {
            $message = str_replace([$key, rawurlencode($key)], '***', $message);
        }
        return mb_substr(preg_replace('/apiKey=[^&\s]+/', 'apiKey=***', $message) ?? '', 0, 500);
    }
}

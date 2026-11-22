<?php

namespace App\Plugins\ExmentPMBacklog;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\File;

/**
 * Backlog's ![image][name] (an image among the issue's attachments, by
 * name) -> Markdown pointing at the Exment document of that name on the
 * issue. The documents are read once, and only when the text has such a
 * reference. Found by name, so files taken by the internal tool before this
 * plugin are found too.
 */
class ImageLinks
{
    const PATTERN = '/!\[([^\]]*)\]\[([^\]]+)\]/u';

    /** @var CustomTable */
    protected $issueTable;

    /** @var int */
    protected $issueId;

    /** @var array<string, string>|null name => url */
    protected $urls;

    public function __construct(CustomTable $issueTable, int $issueId)
    {
        $this->issueTable = $issueTable;
        $this->issueId = $issueId;
    }

    public function replace(string $text): string
    {
        if ($text === '' || !preg_match(static::PATTERN, $text)) {
            return $text;
        }
        if ($this->urls === null) {
            $this->urls = [];
            $docs = CustomTable::getEloquent(SystemTableName::DOCUMENT)->getValueModel()->query()
                ->where('parent_type', $this->issueTable->table_name)
                ->where('parent_id', $this->issueId)
                ->get();
            foreach ($docs as $doc) {
                $name = strval(array_get($doc->value, 'document_name'));
                $url = File::getUrl(strval(array_get($doc->value, 'file_uuid')));
                if ($name !== '' && $url && !isset($this->urls[$name])) {
                    $this->urls[$name] = $url;
                }
            }
        }

        return preg_replace_callback(static::PATTERN, function ($m) {
            return isset($this->urls[$m[2]]) ? '![' . $m[1] . '](' . $this->urls[$m[2]] . ')' : $m[0];
        }, $text);
    }
}

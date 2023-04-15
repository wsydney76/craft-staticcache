<?php

namespace wsydney76\staticcache\jobs;

use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use wsydney76\staticcache\Plugin;

/**
 * Update Entry Job queue job
 */
class UpdateEntryCacheJob extends BaseJob
{
    public int $id;
    public string $site;

    function execute($queue): void
    {
        Plugin::getInstance()->cacheService->updateEntryCache($this->id, $this->site);
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('_staticcache', 'Updating entry cache');
    }
}

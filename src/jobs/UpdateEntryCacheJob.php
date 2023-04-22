<?php

namespace wsydney76\staticcache\jobs;

use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use wsydney76\staticcache\events\ProgressEvent;
use wsydney76\staticcache\Plugin;
use wsydney76\staticcache\services\CacheService;

/**
 * Update Entry Job queue job
 */
class UpdateEntryCacheJob extends BaseJob
{
    public int $id;
    public string $site;

    function execute($queue): void
    {
        $service =  Plugin::getInstance()->cacheService;

        $service->on(CacheService::EVENT_PROGRESS, function (ProgressEvent $event) use ($queue) {
            $this->setProgress($queue, $event->progress, $event->label);
        });

        $service->updateEntryCache($this->id, $this->site);

        $service->off(CacheService::EVENT_PROGRESS);
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('_staticcache', 'Updating entry cache');
    }
}

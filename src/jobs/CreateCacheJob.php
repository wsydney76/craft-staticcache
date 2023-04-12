<?php

namespace wsydney76\staticcache\jobs;

use craft\queue\BaseJob;
use wsydney76\staticcache\events\ProgressEvent;
use wsydney76\staticcache\Plugin;
use wsydney76\staticcache\services\CacheService;

/**
 * Create Cache queue job
 */
class CreateCacheJob extends BaseJob
{
    function execute($queue): void
    {
        $service = Plugin::getInstance()->cacheService;

        $service->on(CacheService::EVENT_PROGRESS, function (ProgressEvent $event) use ($queue) {
            $this->setProgress($queue, $event->progress);
        });

        $service->createCache();

        $service->off(CacheService::EVENT_PROGRESS);
    }

    protected function defaultDescription(): ?string
    {
        return 'Create Cache';
    }
}

<?php

namespace wsydney76\staticcache\jobs;

use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use wsydney76\staticcache\Plugin;

/**
 * Update Entry Job queue job
 */
class UpdateEntryJob extends BaseJob
{
    public int $id;

    function execute($queue): void
    {

        $service = Plugin::getInstance()->cacheService;

        $entries = Entry::find()
            ->id($this->id)
            ->site('*')
            ->all();

        foreach ($entries as $entry) {
            $service->createEntryTask($entry);
        }

        $service->createFiles();
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('_staticcache', 'Updating entry cache');
    }
}

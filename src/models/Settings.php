<?php

namespace wsydney76\staticcache\models;

use Craft;
use craft\base\Model;
use wsydney76\staticcache\Plugin;

/**
 * Settings model
 */
class Settings extends Model
{
    public bool $cachingEnabled = true;
    public string $cacheRoot = 'cache/blitz';

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            ['cacheRoot', 'required'],
        ]);
    }

    public function afterValidate()
    {

        if (!$this->cachingEnabled) {
            Plugin::getInstance()->cacheService->clearCache();
        }

        parent::afterValidate();
    }
}

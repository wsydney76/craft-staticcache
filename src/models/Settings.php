<?php

namespace wsydney76\staticcache\models;

use Craft;
use craft\base\Model;

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
}

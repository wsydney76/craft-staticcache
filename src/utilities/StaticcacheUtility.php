<?php

namespace wsydney76\staticcache\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\App;
use wsydney76\staticcache\Plugin;

/**
 * Staticcache Utility utility
 */
class StaticcacheUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('_staticcache', 'Static Cache');
    }

    static function id(): string
    {
        return 'staticcache-utility';
    }

    public static function iconPath(): ?string
    {
        return App::parseEnv('@wsydney76/staticcache/icon.svg');
    }

    static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('_staticcache/utility', [
            'settings' => Plugin::getInstance()->getSettings(),
        ]);
    }
}

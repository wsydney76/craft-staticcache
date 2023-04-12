<?php

namespace wsydney76\staticcache;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\Cp;
use craft\services\Utilities;
use wsydney76\staticcache\models\Settings;
use wsydney76\staticcache\services\CacheService;
use wsydney76\staticcache\utilities\StaticcacheUtility;
use yii\base\Event;

/**
 * Staticcache plugin
 *
 * @method static Plugin getInstance()
 * @property-read CacheService $cacheService
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => ['cacheService' => CacheService::class],
        ];
    }

    public function init()
    {
        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
            // ...
        });
    }

    private function attachEventHandlers(): void
    {
        // ...
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITY_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = StaticcacheUtility::class;
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return
            Cp::lightswitchFieldHtml([
                'label' => 'Caching enabled',
                'name' => 'cachingEnabled',
                'on' => $this->getSettings()->cachingEnabled,
                'instructions' => 'Enable or disable caching.',
                'errors' => $this->getSettings()->getErrors('cachingEnabled'),
            ]) .

            Cp::autosuggestFieldHtml([
                'first' => true,
                'label' => 'Cache root',
                'name' => 'cacheRoot',
                'value' => $this->getSettings()->cacheRoot,
                'suggestEnvVars' => true,
                'instructions' => 'Folder within the web directory where to store the cached html files. Without leading or trailing slashes.',
                'warning' => 'This must match your web server configuration.',
                'errors' => $this->getSettings()->getErrors('cacheRoot'),
            ]);
    }

}

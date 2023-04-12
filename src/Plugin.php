<?php

namespace wsydney76\staticcache;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Entry;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\Cp;
use craft\helpers\ElementHelper;
use craft\services\Utilities;
use wsydney76\staticcache\jobs\UpdateEntryJob;
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
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITY_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = StaticcacheUtility::class;
        });

        Event::on(Entry::class, Entry::EVENT_AFTER_SAVE, function(ModelEvent $event) {
            /** @var Entry $entry */

            if (!$this->getSettings()->cachingEnabled || !$this->getSettings()->updateCacheOnSave) {
                return;
            }

            $entry = $event->sender;

            if ($entry->scenario !== Element::SCENARIO_LIVE) {
                return;
            }

            Craft::$app->getQueue()->push(new UpdateEntryJob([
                'id' => $entry->canonicalId,
            ]));
        });
    }

    protected
    function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected
    function settingsHtml(): ?string
    {
        return
            Cp::lightswitchFieldHtml([
                'label' => Craft::t('_staticcache', 'Caching enabled'),
                'name' => 'cachingEnabled',
                'on' => $this->getSettings()->cachingEnabled,
                'instructions' => Craft::t('_staticcache', 'Enable or disable caching.'),
                'errors' => $this->getSettings()->getErrors('cachingEnabled'),
            ]) .

            Cp::lightswitchFieldHtml([
                'label' => Craft::t('_staticcache', 'Update cache on save'),
                'name' => 'updateCacheOnSave',
                'on' => $this->getSettings()->updateCacheOnSave,
                'instructions' => Craft::t('_staticcache', 'Update the cache when an entry is saved.'),
                'errors' => $this->getSettings()->getErrors('updateCacheOnSave'),
            ]) .

            Cp::autosuggestFieldHtml([
                'first' => true,
                'label' => Craft::t('_staticcache', 'Cache root'),
                'name' => 'cacheRoot',
                'value' => $this->getSettings()->cacheRoot,
                'suggestEnvVars' => true,
                'instructions' => Craft::t('_staticcache', 'Folder within the web directory where to store the cached html files. Without leading or trailing slashes.'),
                'warning' => Craft::t('_staticcache', 'This must match your web server configuration. You may have to manually delete existing files after changing this setting.'),
                'errors' => $this->getSettings()->getErrors('cacheRoot'),
            ]);
    }
}

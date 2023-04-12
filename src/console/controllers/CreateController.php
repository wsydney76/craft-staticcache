<?php

namespace wsydney76\staticcache\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\App;
use craft\helpers\Console;
use craft\models\Site;
use GuzzleHttp\Exception\GuzzleException;
use modules\main\helpers\FileHelper;
use wsydney76\staticcache\events\ProgressEvent;
use wsydney76\staticcache\Plugin;
use wsydney76\staticcache\services\CacheService;
use yii\base\Event;
use yii\console\ExitCode;
use function fnmatch;
use function is_dir;
use function preg_match;
use const PHP_URL_HOST;

/**
 * Create controller
 */
class CreateController extends Controller
{
    public $defaultAction = 'index';

    /**
     * @var bool If set to 1, the cache will not be created.
     */
    public $dryrun = 0;

    /**
     * @var bool If set to 1, the cache will be created with debug mode enabled (devMode only).
     */
    public $debug = false;


    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'index':
                $options[] = 'dryrun';
                $options[] = 'debug';
                break;
        }
        return $options;
    }

    /**
     * Creates a static cache for all published entries.
     *
     * @return int
     */
    public function actionIndex(): int
    {

        // exit if caching is disabled
        if (!Plugin::getInstance()->getSettings()->cachingEnabled) {
            Console::output('Caching is disabled.');
            return ExitCode::OK;
        }

        if ($this->interactive && !$this->confirm('Are you sure you want to create the static cache?')) {
            return ExitCode::OK;
        }

        $service = Plugin::getInstance()->cacheService;

        $service->on(CacheService::EVENT_PROGRESS, function (ProgressEvent $event) {
            Console::updateProgress($event->done,  $event->total, "Generating cache files ");
        });

        $service->createCache([
            'dryrun' => $this->dryrun,
            'debug' => $this->debug,
        ]);

        $service->off(CacheService::EVENT_PROGRESS);

        Console::endProgress();

        return ExitCode::OK;
    }


}

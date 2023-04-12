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
use wsydney76\staticcache\Plugin;
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
            $this->stdout('Caching is disabled.' . PHP_EOL);
            return ExitCode::OK;
        }

        if ($this->interactive && !$this->confirm('Are you sure you want to create the static cache?')) {
            return ExitCode::OK;
        }

        Plugin::getInstance()->cacheService->createCache([
            'dryrun' => $this->dryrun,
            'debug' => $this->debug,
        ]);

        Console::output('Done');

        return ExitCode::OK;
    }


}

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

    public $dryRun = 0;


    private $client;
    private $errors = 0;
    private $webRoot;

    private $config;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'index':
                $options[] = 'dryRun';
                break;
        }
        return $options;
    }

    /**
     * staticcache/create command
     * ddev craft _staticcache/create
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

        $errors = Plugin::getInstance()->cacheService->createCache($this->dryRun);

        // Output message with error count
        $this->stdout('Done, errors: ' . $errors . PHP_EOL);

        return ExitCode::OK;
    }


}

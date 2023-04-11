<?php

namespace wsydney76\staticcache\console\controllers;

/**
 * Clear controller
 */
use Craft;
use craft\console\Controller;
use craft\helpers\App;
use craft\helpers\Console;
use modules\main\helpers\FileHelper;
use wsydney76\staticcache\Plugin;
use yii\console\ExitCode;

/**
 * Clear controller
 */
class ClearController extends Controller
{
    public $defaultAction = 'index';

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'index':
                // $options[] = '...';
                break;
        }
        return $options;
    }

    /**
     * staticcache/clear command
     */
    public function actionIndex(): int
    {
        // confirm
        if ($this->interactive && !$this->confirm('Are you sure you want to clear the static cache?')) {
            return ExitCode::OK;
        }

        $cacheRoot = Craft::getAlias('@webroot') . '/' . App::parseEnv(Plugin::getInstance()->getSettings()->cacheRoot);

        FileHelper::removeDirectory($cacheRoot);
        return ExitCode::OK;
    }

    public function actionTest(): int
    {
        $siteUrl = 'http://194.0.68.14:57003';
        $siteHostPath = preg_replace('/^(http|https):\/\//i', '', $siteUrl);
        $siteHostPath = preg_replace('/:[0-9]*/i', '', $siteHostPath);
        $siteHostPath = str_replace(':', '', $siteHostPath);

       Console::output( $siteUrl . ' => ' .$siteHostPath);

        return ExitCode::OK;
    }
}

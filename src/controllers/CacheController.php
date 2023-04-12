<?php

namespace wsydney76\staticcache\controllers;

use Craft;
use craft\web\Controller;
use wsydney76\staticcache\jobs\CreateCacheJob;
use wsydney76\staticcache\Plugin;
use yii\web\Response;

/**
 * Cache controller
 */
class CacheController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * _staticcache/cache action
     */
    public function actionClear(): Response
    {
        $this->requirePermission('utility:staticcache-utility');

        Plugin::getInstance()->cacheService->clearCache();

        return $this->asSuccess('Cache cleared');
    }

    public function actionCreate(): Response
    {
        $this->requirePermission('utility:staticcache-utility');

        // put job in queue
        $job = new CreateCacheJob();
        Craft::$app->getQueue()->push($job);

        return $this->asSuccess('Job started');
    }
}

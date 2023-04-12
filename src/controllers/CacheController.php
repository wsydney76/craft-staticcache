<?php

namespace wsydney76\staticcache\controllers;

use Craft;
use craft\web\Controller;
use wsydney76\staticcache\jobs\CreateCacheJob;
use wsydney76\staticcache\Plugin;
use wsydney76\staticcache\utilities\StaticcacheUtility;
use yii\web\Response;

/**
 * Cache controller
 */
class CacheController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    public function beforeAction($action): bool
    {
        $this->requirePermission('utility:' . StaticcacheUtility::id());
        return parent::beforeAction($action);
    }


    /**
     * _staticcache/cache action
     */
    public function actionClear(): Response
    {
        Plugin::getInstance()->cacheService->clearCache();

        return $this->asSuccess(Craft::t('_staticcache', 'Cache cleared'));
    }

    public function actionCreate(): Response
    {
        // put job in queue
        $job = new CreateCacheJob();
        Craft::$app->getQueue()->push($job);

        return $this->asSuccess(Craft::t('_staticcache', 'Cache creation queued'));
    }
}

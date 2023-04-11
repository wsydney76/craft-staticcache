<?php

namespace wsydney76\staticcache\services;

use Craft;
use craft\elements\Entry;
use craft\helpers\App;
use craft\helpers\Console;
use craft\models\Site;
use GuzzleHttp\Exception\GuzzleException;
use modules\main\helpers\FileHelper;
use wsydney76\staticcache\Plugin;
use yii\base\Component;
use function ceil;
use function file_exists;
use function file_put_contents;
use function fnmatch;
use function is_dir;
use function parse_url;
use function str_replace;
use const PHP_URL_HOST;

/**
 * Cache Service service
 */
class CacheService extends Component
{
    private $client = null;
    private int $errors = 0;
    private $webRoot;

    private $config;

    public bool $dryRun = false;

    private bool $isInitialized = false;

    public function init($dryRun = false)
    {
        if ($this->isInitialized) {
            return;
        }

        $this->dryRun = $dryRun;
        $this->webRoot = App::parseEnv(Craft::getAlias('@webroot'));
        $this->config = Craft::$app->getConfig()->getConfigFromFile('staticcache-rules');
        $this->client = Craft::createGuzzleClient();

        $this->isInitialized = true;
    }

    public function createCache($dryRun = false): int
    {

        $this->init($dryRun);

        Craft::$app->runAction('_staticcache/clear', ['interactive' => false]);


        $entries = Entry::find()
            ->uri(':notempty:')
            ->collect();

        $count = $entries->count();
        $errors = 0;

        foreach ($entries as $i => $entry) {
            $this->cacheEntry($entry, $i, $count, $errors);
        }


        $this->createPagination();

        return $this->errors;
    }

    public function cacheEntry(mixed $entry, $i = null, int $count = 0)
    {
        $this->init();

        $excludes = $this->config['exclude'][$entry->site->handle] ?? [];

        // continue if entry url matches exclude pattern
        foreach ($excludes as $exclude) {
            if (fnmatch($exclude, $entry->uri)) {
                return;
            }
        }

        if ($count) {
            Console::updateProgress($i, $count, "Generating entries cache");
        }

        $this->createFile($entry->url, $entry->uri, $entry->site);
    }

    public function createPagination()
    {
        $this->init();

        $config = $this->config;

        if (!isset($config['paginate'])) {
            return;
        }

        foreach ($config['paginate'] as $paginatePages) {
            foreach ($paginatePages['uri'] as $siteHandle => $uri) {

                $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
                if (!$site) {
                    continue;
                }

                $baseUrl = $site->baseUrl;

                $query = Entry::find()->site($siteHandle);
                Craft::configure($query, $paginatePages['criteria']);

                $count = $query->count();

                $perPage = $paginatePages['perPage'] ?? Craft::$app->config->custom->entriesPerPage ?? 10;

                $additionalPages = ceil($count / $perPage) - 1;

                if ($additionalPages < 1) {
                    continue;
                }

                for ($i = 2; $i <= $additionalPages + 1; $i++) {

                    $pageUri = $uri . '/' . Craft::$app->config->general->pageTrigger . $i;
                    $pageUrl = $baseUrl . $pageUri;

                    $this->createFile($pageUrl, $pageUri, $site, "Creating pagination page: $siteHandle - $pageUri - $i");
                }
            }
        }
    }


    public function createFile(string $url, string $uri, Site $site, string $message = ''): void
    {

        $this->init();

        if ($message) {
            Console::output($message);
        }

        $cacheRoot = App::parseEnv(Plugin::getInstance()->getSettings()->cacheRoot);

        $cacheRoot = $this->webRoot . '/' . $cacheRoot . '/' . parse_url($url, PHP_URL_HOST);

        $path = $cacheRoot . '/' . $uri;

        // delete existing file
        if (file_exists($path . '/index.html')) {
            unlink($path . '/index.html');
        }

        try {
            $html = $this->client->get($url)->getBody()->getContents();
        } catch (GuzzleException $e) {
            // output error
            Console::error($e->getMessage());
            $this->errors++;
            // log error with url
            Craft::error($e->getMessage() . ' - ' . $url, __METHOD__);

            return;
        }

        // Output site name, section name, title to console
        if ($message) {
            Console::output(' Done.');
        }

        if ($this->dryRun) {
            return;
        }


        // remove __home__ from path
        $path = str_replace('__home__', '', $path);

        if (!is_dir($path)) {
            FileHelper::createDirectory($path, 0777, true);
        }

        // write html to file
        file_put_contents($path . '/index.html', $html);
    }
}

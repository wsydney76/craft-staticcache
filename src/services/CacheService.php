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
use function func_get_args;
use function is_dir;
use function parse_url;
use function preg_replace;
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

    public bool $dryrun = false;
    public bool $debug = false;

    private bool $isInitialized = false;

    private array $cacheTasks = [];

    private array $siteHostPaths = [];
    private string $cacheRootFolder = '';

    public function initService(array $options)
    {
        if ($this->isInitialized) {
            return;
        }

        $this->dryrun = $options['dryrun'] ?? false;
        $this->debug = $options['debug'] ?? false;
        $this->webRoot = App::parseEnv(Craft::getAlias('@webroot'));
        $this->config = Craft::$app->getConfig()->getConfigFromFile('staticcache-rules');
        $this->client = Craft::createGuzzleClient();

        // e.g. 'cache/blitz'
        $this->cacheRootFolder = App::parseEnv(Plugin::getInstance()->getSettings()->cacheRoot);

        foreach (Craft::$app->sites->getAllSites() as $site) {
            $siteUrl = App::parseEnv($site->baseUrl);

            // strip schema
            $siteHostPath = preg_replace('/^(http|https):\/\//i', '', $siteUrl);
            // strip port numbers
            $siteHostPath = preg_replace('/:[0-9]*/i', '', $siteHostPath);

            $this->siteHostPaths[$site->handle] = $siteHostPath;
        }



        $this->isInitialized = true;
    }

    public function createCache(array $options): int
    {

        $this->initService($options);

        Craft::$app->runAction('_staticcache/clear', ['interactive' => false]);


        $entries = Entry::find()
            ->uri(':notempty:')
            ->site('*')
            ->collect();

        $count = $entries->count();
        $errors = 0;

        foreach ($entries as $i => $entry) {
            $this->cacheEntry($entry, $i, $count, $errors);
        }

        $this->createPagination();

        $this->createFiles();

        return $this->errors;
    }

    public function cacheEntry(mixed $entry)
    {
        $excludes = $this->config['exclude'][$entry->site->handle] ?? [];

        // continue if entry url matches exclude pattern
        foreach ($excludes as $exclude) {
            if (fnmatch($exclude, $entry->uri)) {
                return;
            }
        }

        $this->addTask($entry->url, $entry->uri, Craft::getAlias($entry->site->handle));
    }

    public function createPagination()
    {
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

                    $this->addTask($pageUrl, $pageUri, $site->handle);
                }
            }
        }
    }

    public function addTask(string $url, string $uri, string $siteHandle): void
    {
        $this->cacheTasks[] = [
            'url' => $url,
            'uri' => $uri,
            'site' => $siteHandle,
        ];
    }

    public function createFiles()
    {
        $count = count($this->cacheTasks);
        foreach ($this->cacheTasks as $i => $task) {

            Console::updateProgress($i + 1, $count, "Generating cache files ");

            $this->createFile($task['url'], $task['uri'], $task['site']);
        }
    }

    public function createFile(string $url, string $uri, string $siteUrl): void
    {

        $this->init();

        try {
            $html = $this->client->get($url)->getBody()->getContents();
        } catch (GuzzleException $e) {
            $this->errors++;
            // log error with url
            Craft::error($e->getMessage() . ' - ' . $url, 'staticcache');

            return;
        }


        if ($this->dryrun) {
            return;
        }

        $cacheFilePath = $this->getCacheFilePath($uri, $siteUrl);


        if (!is_dir($cacheFilePath)) {
            FileHelper::createDirectory($cacheFilePath, 0777, true);
        }

        // write html to file
        file_put_contents($cacheFilePath . '/index.html', $html);

        if ($this->debug) {
            Craft::info('Caching ' . $url, 'staticcache');
        }
    }

    private function getCacheFilePath(string $uri, string $site): string
    {

        $siteHostPath = $this->siteHostPaths[$site];

        // Compose full path for cache file
        $cacheFilePath = $this->webRoot . '/' . $this->cacheRootFolder . '/' . $siteHostPath . '/' . $uri;

        // remove __home__ from path
        return str_replace('__home__', '', $cacheFilePath);
    }

}

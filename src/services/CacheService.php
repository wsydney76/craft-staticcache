<?php

namespace wsydney76\staticcache\services;

use Craft;
use craft\elements\Entry;
use craft\helpers\App;
use GuzzleHttp\Exception\GuzzleException;
use modules\main\helpers\FileHelper;
use wsydney76\staticcache\events\ProgressEvent;
use wsydney76\staticcache\Plugin;
use yii\base\Component;
use yii\base\ErrorException;
use function ceil;
use function file_put_contents;
use function fnmatch;
use function is_dir;
use function preg_replace;
use function str_replace;

/**
 * Cache Service service
 */
class CacheService extends Component
{

    const EVENT_PROGRESS = 'progress';

    // The Guzzle client
    private $client = null;

    // The webroot directory
    private $webRoot;

    // The root cache folder within the webroot
    private string $cacheRootFolder = '';

    // The site speficic cache folders within the root cache folder
    private array $siteHostPaths = [];

    // The site base urls
    private array $siteBaseUrls = [];

    // The cache rules, as defined in the config file
    private $config;

    // The list of excluded uri patterns
    private $excludes = [];

    // Whether to run in dryrun mode, i.e. not actually create the cache
    public bool $dryrun = false;

    // Whether to run in debug mode, i.e. create the cache with debug mode enabled (devMode only)
    public bool $debug = false;

    // The list of cache file creation tasks to be executed
    private array $cacheTasks = [];


    public function initService(array $options)
    {

        $this->dryrun = $options['dryrun'] ?? false;
        $this->debug = $options['debug'] ?? false;

        $this->webRoot = App::parseEnv(Craft::getAlias('@webroot'));
        // e.g. 'cache/blitz'
        $this->cacheRootFolder = App::parseEnv(Plugin::getInstance()->getSettings()->cacheRoot);

        foreach (Craft::$app->sites->getAllSites() as $site) {
            $siteUrl = App::parseEnv($site->baseUrl);
            $this->siteBaseUrls[$site->handle] = $siteUrl;

            // strip schema
            $siteHostPath = preg_replace('/^(http|https):\/\//i', '', $siteUrl);
            // strip port numbers
            $siteHostPath = preg_replace('/:[0-9]*/i', '', $siteHostPath);

            $this->siteHostPaths[$site->handle] = $siteHostPath;
        }


        $this->config = Craft::$app->getConfig()->getConfigFromFile('staticcache-rules');
        $this->excludes = $this->config['exclude'] ?? [];

        $this->client = Craft::createGuzzleClient();
    }

    public function createCache(array $options = []): void
    {

        $this->initService($options);

        $this->createEntryTasks();

        $this->createPaginationTasks();

        $this->createIncludedPagesTasks();

        $this->clearCache();

        $this->createFiles();
    }

    public function createEntryTasks(): void
    {
        $entries = Entry::find()
            ->uri(':notempty:')
            ->site('*')
            ->collect();

        foreach ($entries as $entry) {
            $this->createEntryTask($entry);
        }
    }

    public function createEntryTask(mixed $entry): void
    {
        $excludes = $this->excludes[$entry->site->handle] ?? [];

        // continue if entry url matches exclude pattern
        foreach ($excludes as $exclude) {
            if (fnmatch($exclude, $entry->uri)) {
                return;
            }
        }

        $this->addTask($entry->uri, $entry->site->handle);
    }

    public function createPaginationTasks(): void
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

                    $this->addTask($pageUri, $site->handle);
                }
            }
        }
    }

    private function createIncludedPagesTasks()
    {
        $includes = $this->config['include'] ?? [];
        foreach ($includes as $include) {
            $this->addTask($include['uri'], $include['site']);
        }
    }

    public function addTask(string $uri, string $siteHandle): void
    {
        $this->cacheTasks[] = [
            'uri' => str_replace('__home__', '', $uri),
            'site' => $siteHandle,
        ];
    }

    public function createFiles(): void
    {
        $count = count($this->cacheTasks);
        foreach ($this->cacheTasks as $i => $task) {


            if ($this->hasEventHandlers(self::EVENT_PROGRESS)) {
                $event = new ProgressEvent([
                    'done' => $i + 1,
                    'total' => $count,
                    'progress' => ($i + 1) / $count,
                ]);

                $this->trigger(self::EVENT_PROGRESS, $event);
            }

            $this->createFile($task['uri'], $task['site']);
        }
    }

    public function createFile(string $uri, string $site): void
    {

        $url = $this->siteBaseUrls[$site] . $uri;

        try {
            $html = $this->client->get($url)->getBody()->getContents();
        } catch (GuzzleException $e) {
            // log error with url
            Craft::error($e->getMessage() . ' - ' . $url, 'staticcache');

            return;
        }


        if ($this->dryrun) {
            return;
        }

        $cacheFilePath = $this->getCacheFilePath($uri, $site);


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

    /**
     * @throws ErrorException
     */
    public function clearCache(): void
    {
        $cacheRoot = Craft::getAlias('@webroot') . '/' . App::parseEnv(Plugin::getInstance()->getSettings()->cacheRoot);

        FileHelper::removeDirectory($cacheRoot);
    }

}

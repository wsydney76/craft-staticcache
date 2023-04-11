# Static Cache

Creates static cache of all entry pages in your Craft CMS website.

Caching in its simplest form, all or nothing. No cache invalidation, just rebuild the whole cache.

Can be used for sites with a small number of pages, that are not updated so often.

Otherwise, you should use a more sophisticated caching solution, like the Blitz plugin.

As a bonus, missing image transforms will be created. Use the `--dryRun=1` option to create transforms without creating cache files.

Beta quality, use at your own risk.

## Requirements

This plugin requires Craft CMS 4.4 or later, and PHP 8.0.2 or later.


## Installation

```bash
composer require wsydney76/craft-staticcache
craft install/plugin _staticcache
```

## Commands

### Create static cache

```bash
craft staticcache/create
craft staticcache/create --dryRun=1
```

### Clear static cache

```bash
craft staticcache/clear
```

## Configuration

### Web server configuration

Nginx server config:

```nginx
set $cache_path false;
if ($request_method = GET) {
    set $cache_path /cache/blitz/$host/$uri/index.html;
}
if ($args) {
    set $cache_path false;
}

location / {
    absolute_redirect off;
    try_files $cache_path $uri $uri/ /index.php?$query_string;
}
```

Blitz compatible, go to the plugins setting page if you want to use a different cache path than `cache/blitz`.

### Project configuration

`config/staticcache-rules.php`

```php
<?php

return [
    // Exclude pages from cache with site specific regex
    'exclude' => [
        'de' => [
            '*members*',
        ],
    ],

    // Create additional pages for pagination
    'paginate' => [
        [
            'criteria' => [
                'section' => 'news',
            ],
            'uri' => [
                'en' => 'news',
                'de' => 'news',
            ]
        ],
    ]
];
```



<?php

namespace wsydney76\staticcache\events;


use craft\elements\Entry;
use yii\base\Event;

class UpdateEntryCacheEvent extends Event
{
    public Entry $entry;
    public array $tasks = [];

    public array $paginateTasks = [];
}
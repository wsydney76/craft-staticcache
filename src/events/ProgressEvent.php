<?php

namespace wsydney76\staticcache\events;

use yii\base\Event;

class ProgressEvent extends Event
{
    public int $done;
    public int $total;
    public float $progress;

    public string $label;
}
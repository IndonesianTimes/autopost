<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Helpers\Config;
use App\Worker\QueueProcessor;

Config::load(__DIR__);

$worker = new QueueProcessor();
$worker->run();

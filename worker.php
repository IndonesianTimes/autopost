<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Helpers\Config;
use App\Helpers\Logger;
use App\Worker\QueueProcessor;

Config::load(__DIR__);

Logger::cli('SAE worker started');
$worker = new QueueProcessor();
$worker->run();

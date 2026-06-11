<?php

declare(strict_types=1);

require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/Security.php';
require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/ConfigStore.php';
require __DIR__ . '/lib/PretalxClient.php';
require __DIR__ . '/lib/ScheduleService.php';

return (new ConfigStore(__DIR__))->load();

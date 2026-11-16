<?php

declare(strict_types=1);

ini_set('display_errors', '0');
require_once dirname(__DIR__) . '/src/Bootstrap/compat.php';

use Bio\Api\Bootstrap\App;

require_once dirname(__DIR__) . '/src/Bootstrap/App.php';

App::run();

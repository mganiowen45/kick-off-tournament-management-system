<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Controllers\ResultController;
use App\Core\Api;
use App\Core\Request;

Api::run(fn(Request $request) => (new ResultController())->submit($request));

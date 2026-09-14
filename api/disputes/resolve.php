<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Controllers\DisputeController;
use App\Core\Api;
use App\Core\Request;

Api::run(fn(Request $request) => (new DisputeController())->resolve($request));

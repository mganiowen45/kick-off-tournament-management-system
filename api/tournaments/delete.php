<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Controllers\TournamentController;
use App\Core\Api;
use App\Core\Request;

Api::run(fn(Request $request) => (new TournamentController())->cancel($request));

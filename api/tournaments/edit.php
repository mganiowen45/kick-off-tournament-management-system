<?php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';
App\Core\Api::run(fn(App\Core\Request $request) => (new App\Controllers\TournamentController())->edit($request));


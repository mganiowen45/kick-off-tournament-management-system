<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

destroySession();
jsonSuccess(['redirect' => 'login.html'], 'Logged out successfully');

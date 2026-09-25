<?php
require 'bootstrap.php';
$secret = \App\Core\Totp::generateSecret();
echo "Secret: $secret\n";
echo "Code now: " . \App\Core\Totp::generateCode($secret) . "\n";

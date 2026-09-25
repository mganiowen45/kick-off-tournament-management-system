<?php
require 'config/config.php';
require 'app/Services/ResultService.php';
$rc = new ReflectionClass('App\Services\ResultService');
foreach($rc->getMethod('submit')->getParameters() as $p) {
    echo $p->getName() . ' ';
}
echo "\n";

<?php
require __DIR__ . '/bootstrap.php';
$r = \TypePhp\Resolver\Reflection::getFunctionParameter('array_push', 0);
var_dump($r->isPassedByReference());
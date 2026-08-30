<?php

use Fidry\CpuCoreCounter\CpuCoreCounter;

function main()
{
    require __DIR__ . '/../vendor/autoload.php';

    var_dump(__LINE__);
    var_dump('auto load class: ' . CpuCoreCounter::class);
    $o = new CpuCoreCounter();
    var_dump($o->getCount());
}

test();
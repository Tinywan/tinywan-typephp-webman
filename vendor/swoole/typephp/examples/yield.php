<?php

function main()
{
    require __DIR__ . '/fn.php';
    foreach (fn_test_yeild() as $value) {
        echo $value . "\n";
    }
}


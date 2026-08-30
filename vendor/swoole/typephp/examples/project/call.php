<?php
function fn1()
{
    echo "fn1\n";
    fn2();
}

function fn2()
{
    var_dump(__FILE__, __DIR__, __LINE__, __FUNCTION__);
    echo "\n";
}

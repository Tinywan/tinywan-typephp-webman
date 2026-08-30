<?php
function foo(): void
{
    var_dump(__FUNCTION__);
}

function main()
{
    $s = foo() . "\n";
    echo $s;
}
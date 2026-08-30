<?php
function test($a, $b, int...$args)
{
    var_dump($args);
}

function main()
{
    test(1, 2, 3, 4, 5);
}

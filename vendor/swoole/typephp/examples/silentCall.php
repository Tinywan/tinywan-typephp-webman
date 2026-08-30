<?php
function main()
{
    $cache = [];
    $key = 'hello';
    $hello = @$cache[$key];
    var_dump($hello);
    var_dump($cache["world"]);
}

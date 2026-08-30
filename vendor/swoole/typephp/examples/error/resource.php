<?php
function foo(resource $res)
{
    var_dump($res);

}
function main()
{
    $stream = fopen("php://stdin", "r");
    foo($stream);
}

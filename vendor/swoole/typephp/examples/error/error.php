<?php
function foo()
{
    global $argv;
    var_dump($argv);
    var_dump(STDOUT);
}
function main(int $argc, array $argv): void
{
    foo();
}
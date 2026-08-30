<?php
use function Swoole\Coroutine\run;

function main(): void
{
    run(function () {
        sleep(1);
    });
}
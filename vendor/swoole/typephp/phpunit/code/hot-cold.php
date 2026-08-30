<?php

namespace FunctionTemperature {

use \Cold;
use \Hot;

#[Hot]
function frequentlyUsed(int $value): int
{
    return $value + 1;
}

#[Cold]
function errorMessage(string $message): string
{
    return 'Error: ' . $message;
}

class Worker
{
    #[Hot]
    public function run(int $value): int
    {
        return frequentlyUsed($value);
    }

    #[Cold]
    public function fail(string $message): string
    {
        return errorMessage($message);
    }
}

}

namespace {

function main(): void
{
    $worker = new \FunctionTemperature\Worker();
    echo $worker->run(1);
    echo $worker->fail('test');
}

}

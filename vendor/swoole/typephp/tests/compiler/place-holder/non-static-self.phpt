--TEST--
First-class self callable retains the current object
--FILE--
<?php

class TestFirstClassSelfCallback
{
    private function value(): string
    {
        return 'ok';
    }

    public function callback(): Closure
    {
        return self::value(...);
    }
}

function main(): void
{
    $callback = (new TestFirstClassSelfCallback())->callback();
    var_dump($callback());
}

?>
--EXPECT--
string(2) "ok"

--TEST--
yield expression receives send value
--FILE--
<?php
function gen_send(): iterable
{
    $value = yield 1;
    yield $value;
}

function main(): void
{
    $gen = gen_send();
    var_dump($gen->current());
    var_dump($gen->send(42));
}
?>
--EXPECT--
int(1)
int(42)

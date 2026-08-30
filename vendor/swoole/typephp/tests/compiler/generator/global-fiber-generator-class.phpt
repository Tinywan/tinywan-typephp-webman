--TEST--
FiberGenerator is registered globally and accepted as an exact generator return type
--FILE--
<?php
function exact_fiber_generator(): \FiberGenerator
{
    yield 1;
    return 2;
}

function main(): void
{
    $generator = exact_fiber_generator();
    var_dump(get_class($generator));
    var_dump($generator instanceof \FiberGenerator);
    var_dump(class_exists('TypePHP\\FiberGenerator', false));
    var_dump($generator->current());
    $generator->next();
    var_dump($generator->getReturn());
}
?>
--EXPECT--
string(14) "FiberGenerator"
bool(true)
bool(false)
int(1)
int(2)

--TEST--
yield evaluates key and value side effects before suspension in PHP order
--FILE--
<?php
class YieldOrderBox
{
    public int $value = 0;
}

function mark(string $name): string
{
    echo $name, "\n";
    return $name;
}

function ordered_yield(YieldOrderBox $box): iterable
{
    yield mark('key') => match (mark('selector')) {
        'selector' => mark('value'),
    };
    yield $box->value++;
}

function main(): void
{
    $box = new YieldOrderBox();
    $generator = ordered_yield($box);
    var_dump($generator->current());
    var_dump($box->value);
    $generator->next();
    var_dump($generator->current());
    var_dump($box->value);
}
?>
--EXPECT--
key
selector
value
string(5) "value"
int(0)
int(0)
int(1)

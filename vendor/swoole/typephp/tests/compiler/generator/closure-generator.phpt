--TEST--
anonymous and arrow generator closures preserve lazy execution captures and return values
--FILE--
<?php
class GeneratorClosureBox
{
    public function make(int $base): Closure
    {
        return function (int $offset = 1) use ($base): iterable {
            yield 'method' => $this->value + $base + $offset;
            return 9;
        };
    }

    public int $value = 10;
}

function main(): void
{
    $state = 1;
    $factory = function (int $add) use (&$state): iterable {
        ++$state;
        $sent = yield 'closure' => $state + $add;
        return $sent;
    };

    $generator = $factory(3);
    var_dump($state);
    var_dump($generator->key(), $generator->current());
    var_dump($state);
    var_dump($generator->send(7));
    var_dump($generator->getReturn());

    $arrow = fn (): iterable => yield 'arrow' => $state;
    var_dump($arrow()->key(), $arrow()->current());

    $method = (new GeneratorClosureBox())->make(2)(3);
    var_dump($method->current());
    $method->next();
    var_dump($method->getReturn());
}
?>
--EXPECT--
int(1)
string(7) "closure"
int(5)
int(2)
NULL
int(7)
string(5) "arrow"
int(2)
int(15)
int(9)

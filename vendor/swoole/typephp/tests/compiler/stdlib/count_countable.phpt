--TEST--
count: Countable objects use Countable::count()
--FILE--
<?php

final class RouteBag implements Countable
{
    public function __construct(private array $routes)
    {
    }

    public function count(): int
    {
        echo "RouteBag::count\n";
        return count($this->routes);
    }
}

function count_mixed(mixed $value): int
{
    return count($value);
}

function main(): void
{
    $bag = new RouteBag(['home', 'about', 'contact']);
    var_dump(count($bag));
    var_dump(count($bag, COUNT_RECURSIVE));
    var_dump(count_mixed($bag));

    $anonymous = new class([1, 2, 3, 4]) implements Countable {
        public function __construct(private array $items)
        {
        }

        public function count(): int
        {
            echo "anonymous::count\n";
            return count($this->items);
        }
    };
    var_dump(count($anonymous));

    $arrayObject = new ArrayObject(['x', 'y']);
    var_dump(count($arrayObject));

    try {
        count(new stdClass());
    } catch (TypeError $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
RouteBag::count
int(3)
RouteBag::count
int(3)
RouteBag::count
int(3)
anonymous::count
int(4)
int(2)
count(): Argument #1 ($value) must be of type Countable|array

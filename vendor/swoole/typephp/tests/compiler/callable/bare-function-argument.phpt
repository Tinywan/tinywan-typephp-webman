--TEST--
Bare function names are resolved only for callable parameters
--FILE--
<?php
namespace BareCallable\Library {
    function triple(int $value): int
    {
        return $value * 3;
    }
}

namespace BareCallable\Application {
    use function BareCallable\Library\triple as imported_triple;

    function apply(callable $callback, int $value): int
    {
        return $callback($value);
    }

    function local_double(int $value): int
    {
        return $value * 2;
    }

    function run(): void
    {
        var_dump(array_map(local_double, [1, 2, 3]));
        var_dump(array_map(callback: local_double, array: [4]));
        var_dump(apply(imported_triple, 4));
        var_dump(array_map(\strlen, ['a', 'abcd']));

        try {
            $notACallableArgument = local_double;
        } catch (\Error $error) {
            echo "constant rules preserved\n";
        }
    }
}

namespace {
    const cube = 'square';

    function cube(int $value): int
    {
        return $value * $value * $value;
    }

    function square(int $value): int
    {
        return $value * $value;
    }

    function main(): void
    {
        BareCallable\Application\run();
        var_dump(array_map(cube, [2, 3]));
    }
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(2)
  [1]=>
  int(4)
  [2]=>
  int(6)
}
array(1) {
  [0]=>
  int(8)
}
int(12)
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(4)
}
constant rules preserved
array(2) {
  [0]=>
  int(4)
  [1]=>
  int(9)
}

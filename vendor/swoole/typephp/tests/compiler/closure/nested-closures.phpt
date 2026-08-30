--TEST--
Nested closures and closure scope
--FILE--
<?php

function makeMultiplier(int $factor): Closure {
    return function (int $value) use ($factor): int {
        return $value * $factor;
    };
}

function makeAdder(int $base): Closure {
    return function (int $value) use ($base): int {
        return $value + $base;
    };
}

function makeFormatter(string $prefix): Closure {
    return function (string $name) use ($prefix): string {
        return $prefix . ": " . $name;
    };
}

function makeChain(int $x): Closure {
    return function (int $y) use ($x): Closure {
        return function (int $z) use ($x, $y): int {
            return $x + $y + $z;
        };
    };
}

function main() {
    $double = makeMultiplier(2);
    $add5 = makeAdder(5);

    var_dump($double(10));
    var_dump($add5(10));

    $result = $add5($double(3));
    var_dump($result);

    $format = makeFormatter("User");
    var_dump($format("Alice"));

    $chain = makeChain(1);
    $chain2 = $chain(2);
    var_dump($chain2(3));
}

?>
--EXPECT--
int(20)
int(15)
int(11)
string(11) "User: Alice"
int(6)

--TEST--
Universal method call on expression results (FuncCall, MethodCall, StaticCall, PropertyFetch)
--FILE--
<?php

// --- Helpers: functions and classes ---

function getString(): string
{
    return "hello world";
}

function getInt(): int
{
    return 42;
}

function getFloat(): float
{
    return 3.14;
}

function getArray(): array
{
    return [1, 2, 3, 4, 5];
}

class Helper {
    public string $name = "hello";

    public function getName(): string
    {
        return "test";
    }

    public function getInt(): int
    {
        return 100;
    }

    public static function staticName(): string
    {
        return "STATIC";
    }

    public static function staticArray(): array
    {
        return ["a", "b", "c"];
    }
}

function main()
{
    require __DIR__ . '/../../../src/Assert.php';

    // --- Test 1: FuncCall -> universal method ---
    Assert::eq(getString()->length(), 11);
    Assert::eq(getString()->upper(), "HELLO WORLD");
    Assert::eq(getString()->contains("world"), true);
    Assert::eq(getInt()->toFloat(), 42.0);
    Assert::eq(getFloat()->toInt(), 3);
    Assert::eq(getArray()->count(), 5);
    Assert::eq(getArray()->isEmpty(), false);

    // --- Test 2: MethodCall on typed object -> universal method ---
    $obj = new Helper();
    Assert::eq($obj->getName()->length(), 4);
    Assert::eq($obj->getName()->upper(), "TEST");
    Assert::eq($obj->getInt()->toFloat(), 100.0);

    // --- Test 3: StaticCall -> universal method ---
    Assert::eq(Helper::staticName()->length(), 6);
    Assert::eq(Helper::staticName()->lower(), "static");
    Assert::eq(Helper::staticArray()->count(), 3);
    Assert::eq(Helper::staticArray()->isEmpty(), false);

    // --- Test 4: PropertyFetch on typed property -> universal method ---
    Assert::eq($obj->name->length(), 5);
    Assert::eq($obj->name->upper(), "HELLO");

    // --- Test 5: Nested chains ---
    Assert::eq(Helper::staticName()->upper()->length(), 6);

    // --- Test 6: Typed object property with string method chaining (partial assertion on contains) ---
    Assert::eq($obj->getName()->contains("es"), true);

    // --- Test 7: Internal function with reflection-based return type ---
    // random_bytes() returns string, so base64Encode() should work
    Assert::greaterThan(random_bytes(128)->base64Encode()->length(), 0);

    // --- Test 8: Array method chaining ---
    $arr = [3, 1, 2];
    // sort() is mutating, returns the array itself
    $arr->sort();
    Assert::eq($arr->get(0), 1);
    // reverse() returns new array, can chain; sorted=[1,2,3], reversed=[3,2,1]
    Assert::eq($arr->reverse()->get(0), 3);
    Assert::eq($arr->reverse()->count(), 3);
    // double chain: reverse()->reverse() returns original order [1,2,3]
    Assert::eq($arr->reverse()->reverse()->get(0), 1);
    // slice() returns new array, chain with count()
    Assert::eq($arr->slice(0, 2)->count(), 2);
    // values() returns new array
    Assert::eq($arr->values()->count(), 3);
    // keys() returns new array, chain further
    Assert::eq($arr->keys()->count(), 3);
    // merge() returns new array
    Assert::eq($arr->merge([4, 5])->count(), 5);

    echo "OK\n";
}
?>
--EXPECT--
OK

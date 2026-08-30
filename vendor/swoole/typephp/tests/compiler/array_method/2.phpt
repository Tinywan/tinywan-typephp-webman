--TEST--
Universal method: array_method/2 (shift/unshift on Ref)
--FILE--
<?php
function main()
{
    require __DIR__ . '/../../../src/Assert.php';
    $array = array("orange", "banana", "apple", "raspberry");

    $stack = $array;
    Assert::eq($stack->count(), 4);
    $fruit = $stack->shift();
    Assert::eq($fruit, "orange");
    Assert::eq($stack->count(), 3);
    Assert::eq($array->count(), 4);

    $stack->unshift("mango");
    Assert::eq($stack->count(), 4);

    $stack2 = array("orange", "banana", "apple", "raspberry");
    Assert::eq($stack2->count(), 4);
    $stack2->shift();
    Assert::eq($stack2->count(), 3);

    echo "done\n";
}
?>
--EXPECT--
done

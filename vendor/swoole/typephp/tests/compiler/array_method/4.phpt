--TEST--
array_method: 1
--FILE--
<?php

function main()
{
    require __DIR__ . '/../../../src/Assert.php';

    $array =  [1999, '2025'];
    Assert::true($array->contains(1999));
    Assert::true($array->contains(2025));
    $strict = true;
    Assert::false($array->contains(2025, $strict));
    echo "done\n";
}
?>
--EXPECT--
done

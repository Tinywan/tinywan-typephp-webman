--TEST--
array_method: 1
--FILE--
<?php

function main()
{
    require __DIR__ . '/../../../src/Assert.php';

    $array =  ["lemon", "orange", "banana", "apple"];
    $sorted_array = array("apple", "banana", "lemon", "orange",);
    $array->sort(SORT_NATURAL | SORT_FLAG_CASE);
    Assert::same($array, $sorted_array);
    echo "done\n";
}
?>
--EXPECT--
done

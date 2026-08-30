--TEST--
do-while condition can use a variable first defined in the loop body
--FILE--
<?php

function main(): void
{
    $page = 1;
    do {
        $results = [1, 2, 3];
        echo "page=$page count=", count($results), "\n";
        $page++;
    } while (count($results) === 3 && $page <= 2);

    echo "Done\n";
}
?>
--EXPECT--
page=1 count=3
page=2 count=3
Done

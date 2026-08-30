--TEST--
arrow function
--FILE--
<?php
function foo($obj) {
    $fn = fn (string $add) => $obj->add(new DateInterval($add));
    $newDate = $fn('P10D');
    echo $newDate->format('Y-m-d') . "\n";
}

function main()
{
    $date = new DateTimeImmutable('2000-01-01');
    foo($date);
}
?>
--EXPECT--
2000-01-11
--TEST--
foreach uses one loop body for dynamically typed arrays and objects
--FILE--
<?php

function flatten($groups): array
{
    $result = [];
    foreach ($groups as $groupName => $group) {
        foreach ($group as $itemName => $value) {
            $result[] = "$groupName:$itemName=$value";
        }
    }
    return $result;
}

function main(): void
{
    var_dump(flatten([
        'array' => ['first' => 1, 'second' => 2],
    ]));

    var_dump(flatten(new ArrayIterator([
        'iterator' => new ArrayIterator(['third' => 3]),
    ])));

    $plain = new stdClass();
    $plain->object = (object) ['fourth' => 4];
    var_dump(flatten($plain));
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(13) "array:first=1"
  [1]=>
  string(14) "array:second=2"
}
array(1) {
  [0]=>
  string(16) "iterator:third=3"
}
array(1) {
  [0]=>
  string(15) "object:fourth=4"
}

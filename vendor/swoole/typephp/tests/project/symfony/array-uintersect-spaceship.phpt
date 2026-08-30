--TEST--
Symfony pattern: array_uintersect with spaceship comparator for nested arrays
--FILE--
<?php

class SymfonyLikeListenerPass
{
    public function intersectListeners(array $expected, array $actual): array
    {
        return array_uintersect($expected, $actual, static fn (array $a, array $b) => $a <=> $b);
    }
}

function main(): void
{
    $pass = new SymfonyLikeListenerPass();

    var_dump(array_values($pass->intersectListeners(
        [
            ['event' => 'request', 'method' => 'onRequest'],
            ['event' => 'response', 'method' => 'onResponse'],
        ],
        [
            ['event' => 'response', 'method' => 'onResponse'],
            ['event' => 'terminate', 'method' => 'onTerminate'],
        ]
    )));
}
?>
--EXPECT--
array(1) {
  [0]=>
  array(2) {
    ["event"]=>
    string(8) "response"
    ["method"]=>
    string(10) "onResponse"
  }
}

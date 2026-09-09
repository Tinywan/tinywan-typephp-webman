--TEST--
std::any explicitly enables reference mutation before conversion to fixed types
--FILE--
<?php
function main(): void
{
    $stringValue = std::any('start');
    $replaceWithArray = static function () use (&$stringValue): void {
        $stringValue = ['value' => 42];
    };
    $replaceWithArray();
    $arrayValue = $stringValue->toArray();
    var_dump($arrayValue);

    $arraySource = std::any(['old']);
    $replaceWithString = static function () use (&$arraySource): void {
        $arraySource = 'done';
    };
    $replaceWithString();
    $finalString = $arraySource->toString();
    var_dump($finalString);
}
?>
--EXPECT--
array(1) {
  ["value"]=>
  int(42)
}
string(4) "done"

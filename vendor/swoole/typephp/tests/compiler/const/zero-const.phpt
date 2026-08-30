--TEST--
class const with value 0 used as array key
--FILE--
<?php
class Test
{
    const CV = 0;

    public function aaa(): array
    {
        $list = [
            self::CV => 'a',
        ];
        return $list;
    }
}

function main(): void
{
    $test = new Test;
    var_dump('test', $test->aaa());
}
?>
--EXPECT--
string(4) "test"
array(1) {
  [0]=>
  string(1) "a"
}

--TEST--
class const default value from self::CV
--FILE--
<?php
class Test
{
    const CV = 1;

    public static function aaa(int $v = self::CV): void
    {
        var_dump($v);
    }

    public function bbb(int $v = self::CV): void
    {
        var_dump($v);
    }
}

function main(): void
{
    Test::aaa();
    Test::aaa(999);
    $t = new Test();
    $t->bbb();
    $t->bbb(888);
}
?>
--EXPECT--
int(1)
int(999)
int(1)
int(888)

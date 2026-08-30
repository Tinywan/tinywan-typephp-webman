--TEST--
Method conflict in traits
--FILE--
<?php
trait THello1
{
    public function hello()
    {
        var_dump(self::class);
        var_dump($this->prop);
        var_dump(self::HELLO);
        var_dump(self::$staticProp);
    }
}

class TraitsTest
{
    use THello1;
    protected string $prop = 'Hello 1';
    const HELLO = 'Hello 2';
    static int $staticProp = 2025;
}

function main()
{
    $o = new TraitsTest;
    $o->hello();
}
?>
--EXPECT--
string(10) "TraitsTest"
string(7) "Hello 1"
string(7) "Hello 2"
int(2025)
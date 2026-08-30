--TEST--
Method conflict in traits
--FILE--
<?php
trait THello1
{
    public function hello()
    {
        echo 'Hello 1', PHP_EOL;
    }
}

trait THello2
{
    public function hello2()
    {
        echo 'Hello 2', PHP_EOL;
    }
}

class TraitsTest
{
    use THello1, THello2 {
        hello as hello3;
    }
}

function main()
{
    $o = new TraitsTest;
    $o->hello3();
}
?>
--EXPECT--
Hello 1
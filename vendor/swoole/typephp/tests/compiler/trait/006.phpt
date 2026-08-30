--TEST--
Method conflict in traits
--FILE--
<?php
trait THello1
{
    static public function hello()
    {
        echo 'Hello 2', PHP_EOL;
    }
}

class TraitsTest
{
    use THello1;
}

function main()
{
    TraitsTest::hello();
}
?>
--EXPECT--
Hello 2
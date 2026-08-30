--TEST--
Method conflict in traits
--FILE--
<?php
trait THello1
{
    public function hello()
    {
        echo 'Hello 2', PHP_EOL;
    }
}

trait THello2
{
    public function hello()
    {
        echo 'Hello 3', PHP_EOL;
    }
}

class TraitsTest
{
    use THello1;
    use THello2 {
        THello2::hello insteadof THello1;
    }
}

function main()
{
    $o = new TraitsTest;
    $o->hello();
}

?>
--EXPECT--
Hello 3
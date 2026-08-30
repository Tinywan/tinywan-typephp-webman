--TEST--
Method conflict in traits
--FILE--
<?php
trait THello1
{
    private function hello()
    {
        echo 'Hello 2', PHP_EOL;
    }
}

class TraitsTest
{
    use THello1 {
        THello1::hello as public hello2;
    }

    public function hello()
    {
        echo 'Hello 1' , PHP_EOL;
    }
}

function main()
{
    $o = new TraitsTest;
    $o->hello2();
    $o->hello();
}

?>
--EXPECT--
Hello 2
Hello 1
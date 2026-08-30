<?php

trait THello1
{
    public function hello()
    {
        echo 'Hello 2';
    }
}

trait THello2
{
    public function hello()
    {
        echo 'Hello 3';
    }
}

class TraitsTest
{
    use THello1 {
        THello1::hello as hello2;
    }
//    use THello2 {
//        THello2::hello insteadof THello1;
//    }
//    public function hello()
//    {
//        echo 'Hello 1' . PHP_EOL;
//    }
}

function main()
{
    $o = new TraitsTest;
    $o->hello2();
}

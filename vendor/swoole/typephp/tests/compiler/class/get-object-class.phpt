--TEST--
get_class() and $object::class should return original class name (case-sensitive with namespace)
--FILE--
<?php

namespace NS {
    class Test1
    {

    }
}

namespace {
    use NS\Test1;

    class Test2
    {

    }

    function main() {
        $test1 = new NS\Test1;
        var_dump(get_class($test1));
        var_dump($test1::class);

        $test1 = new Test1;
        var_dump(get_class($test1));
        var_dump($test1::class);

        $test2 = new Test2;
        var_dump(get_class($test2));
        var_dump($test2::class);
    }
}
?>
--EXPECT--
string(8) "NS\Test1"
string(8) "NS\Test1"
string(8) "NS\Test1"
string(8) "NS\Test1"
string(5) "Test2"
string(5) "Test2"

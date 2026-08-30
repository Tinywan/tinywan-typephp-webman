--TEST--
'Static' binding for private variables
--SKIPIF--
<?php die("skip");?>
--FILE--
<?php

class par {
    private $id="foo";

    function displayMe()
    {
        $this->displayChild();
    }
};

class chld extends par {
    private $id = "bar";

    function displayChild()
    {
        print $this->id;
    }
};


$obj = new chld();
$obj->displayMe();

?>
--EXPECT--
bar

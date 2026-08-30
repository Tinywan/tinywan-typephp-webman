--TEST--
type hits
--FILE--
<?php
function foo(Stringable $data) {
    var_dump(strval($data));
}

class A implements Stringable {
    public function __toString() : string {
        return "Hello World";
    }
}

function main()
{
    $obj = new A();
    foo($obj);
}
?>
--EXPECT--
string(11) "Hello World"
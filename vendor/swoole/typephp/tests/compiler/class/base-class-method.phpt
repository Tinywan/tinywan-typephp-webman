--TEST--
class extends
--FILE--
<?php

class BaseObject
{
    public function foo()
    {
        var_dump(__METHOD__);
    }
}

class ChildObject extends BaseObject
{
    public function bar() {
        var_dump(__METHOD__);
    }
}

function foo_test(BaseObject $o) {
    $o->foo();
    $o->toObject(ChildObject::class)->bar();
}

function main()
{
    $o1 = new ChildObject;
    foo_test($o1);
}
?>
--EXPECT--
string(15) "BaseObject::foo"
string(16) "ChildObject::bar"
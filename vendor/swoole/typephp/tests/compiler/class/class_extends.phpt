--TEST--
class extends
--FILE--
<?php
class Foo extends ArrayObject {

}

class A extends Foo
{
    public function __construct()
    {
        echo "A::__construct()\n";
    }
}

class B extends A
{
    public function __construct()
    {
        echo "B::__construct()\n";
        parent::__construct();
    }


    public function foo()
    {
        $this->bar2();
    }

    function bar2()
    {
        var_dump(__METHOD__);
    }
}

class C extends B {
    public function __construct()
    {
        echo "C::__construct()\n";
        parent::__construct();
    }
}

function foo_test($a, $b, $c) {
    return 1;
}

function foo2(): void
{
    var_dump(__FUNCTION__);
    return;
    var_dump(__FUNCTION__);
}

function main()
{
    var_dump(foo_test(1, 2, 3));
    $o = new B;
    $o->foo();

    $c = new C;
    $c->offsetSet(0, 1);
    $c->offsetSet(1, 2);
    var_dump($c);
    var_dump($c->foo());

    foo2();
}
?>
--EXPECT--
int(1)
B::__construct()
A::__construct()
string(7) "B::bar2"
C::__construct()
B::__construct()
A::__construct()
object(C)#2 (1) {
  ["storage":"ArrayObject":private]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(2)
  }
}
string(7) "B::bar2"
NULL
string(4) "foo2"


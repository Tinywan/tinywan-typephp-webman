--TEST--
bool nullable type
--SKIPIF--
--FILE--
<?php

class TestBoolNullableType
{
    public function test(?bool $value = true)
    {
        var_dump($value);
    }
}

function main()
{
    $o = new TestBoolNullableType();
    $o->test(null);
    $o->test(true);
    $o->test(false);
    $o->test();

    eval("class TestBoolNullableTypeDym {
              public function test(?bool \$value = true)
              {
                  var_dump(\$value);
              }
        }");

    $o2 = new TestBoolNullableTypeDym();
    $o2->test(null);
    $o2->test(true);
    $o2->test(false);
    $o2->test();
}
?>
--EXPECT--
NULL
bool(true)
bool(false)
bool(true)
NULL
bool(true)
bool(false)
bool(true)
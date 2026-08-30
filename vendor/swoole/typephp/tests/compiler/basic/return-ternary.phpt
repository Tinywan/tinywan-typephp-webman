--TEST--
return ternary
--SKIPIF--
--FILE--
<?php

class TestReturnTernary
{
    protected function isA(): bool {
        return false;
    }

    protected function isB(): bool {
        return random_int(1, 10000) % 100 < 50;
    }

    public function test()
    {
        return ($this->isA() && $this->isB()) ? 'str' : false;
    }
}

function main()
{
    $obj = new TestReturnTernary;
    var_dump($obj->test());
}
?>
--EXPECT--
bool(false)
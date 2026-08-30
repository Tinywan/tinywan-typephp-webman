--TEST--
return assign array dim
--SKIPIF--
--FILE--
<?php
class TestReturnArrayDim
{
    public function test()
    {
        $array['hello']  = [33];
        return $array['world'] = 999;
    }
}

function main()
{
    $obj = new TestReturnArrayDim;
    var_dump($obj->test());
}
?>
--EXPECT--
int(999)
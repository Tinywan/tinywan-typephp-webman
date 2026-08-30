--TEST--
Trait method with `parent` return type flattened into a subclass
--FILE--
<?php

class Base
{
}

trait TestTrait
{
    public function who(): parent
    {
        return $this;
    }
}

class Child extends Base
{
    use TestTrait;
}

function main()
{
    $c = new Child;
    // The trait method's `parent` resolves to the consuming class's parent (Base).
    $r = $c->who();
    var_dump($r instanceof Child);
}
?>
--EXPECT--
bool(true)

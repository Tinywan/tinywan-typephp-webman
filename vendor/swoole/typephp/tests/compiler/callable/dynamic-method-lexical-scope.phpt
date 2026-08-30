--TEST--
Dynamic method calls use the declaring class scope
--FILE--
<?php

class DynamicScopeBase
{
    private function privateValue(mixed $_unused = null): string
    {
        return 'base-private';
    }

    public function callPrivate(): string
    {
        $method = 'privateValue';
        return $this->$method();
    }

    public function callSubclassHook(): string
    {
        return $this->subclassHook();
    }

    public function callPrivateCallback(): array
    {
        return array_map([$this, 'privateValue'], [null]);
    }
}

class DynamicScopeChild extends DynamicScopeBase
{
    protected function subclassHook(): string
    {
        return 'child-protected';
    }
}

function main(): void
{
    $object = new DynamicScopeChild();
    var_dump($object->callPrivate());
    var_dump($object->callSubclassHook());
    var_dump($object->callPrivateCallback());
}

?>
--EXPECT--
string(12) "base-private"
string(15) "child-protected"
array(1) {
  [0]=>
  string(12) "base-private"
}

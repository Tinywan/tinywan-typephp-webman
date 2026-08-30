--TEST--
Scoped callbacks preserve relative, inherited, delayed and nested call contexts
--FILE--
<?php

class ComplexScopeBase
{
    private static function privateMap(int $value): string
    {
        return static::class . ':private:' . $value;
    }

    protected static function protectedMap(int $value): string
    {
        return static::class . ':protected:' . $value;
    }

    public function dynamicRelativeCallbacks(): void
    {
        $self = 'self';
        $static = 'static';
        var_dump(array_map([$self, 'privateMap'], [1]));
        var_dump(array_map([$static, 'protectedMap'], [2]));
    }

    public function delayedUnpackedCallback(): Closure
    {
        return function (int $value): string {
            $arguments = [['self', 'privateMap'], $value];
            return call_user_func(...$arguments);
        };
    }

    public function unpackedCallbackArray(int $value): string
    {
        $static = 'static';
        $arguments = [[$static, 'protectedMap'], [$value]];
        return call_user_func_array(...$arguments);
    }

    public function nestedDynamicClosure(int $value): string
    {
        $callback = function (int $innerValue): string {
            $arguments = [['self', 'privateMap'], $innerValue];
            return call_user_func(...$arguments);
        };
        return call_user_func($callback, $value);
    }
}

class ComplexScopeChild extends ComplexScopeBase
{
    public function parentCallback(): void
    {
        $parent = 'parent';
        var_dump(array_map([$parent, 'protectedMap'], [3]));
    }
}

class NestedScopeOuter
{
    private function invokeInner(int $value): string
    {
        return (new NestedScopeInner())->run($value) . ':outer';
    }

    public function callback(): array
    {
        return [$this, 'invokeInner'];
    }

    public function run(int $value): string
    {
        $arguments = [[$this, 'invokeInner'], $value];
        return call_user_func(...$arguments);
    }
}

class NestedScopeInner
{
    private function value(int $value): string
    {
        return 'inner:' . $value;
    }

    public function run(int $value): string
    {
        $arguments = [[$this, 'value'], $value];
        return call_user_func(...$arguments);
    }
}

function main(): void
{
    $object = new ComplexScopeChild();
    $object->dynamicRelativeCallbacks();
    $object->parentCallback();

    $delayed = $object->delayedUnpackedCallback();
    var_dump($delayed(4));
    var_dump($object->unpackedCallbackArray(5));
    var_dump($object->nestedDynamicClosure(6));

    $nested = new NestedScopeOuter();
    $privateCallback = $nested->callback();
    var_dump($nested->run(7));
    var_dump(is_callable($privateCallback));
}

?>
--EXPECT--
array(1) {
  [0]=>
  string(27) "ComplexScopeChild:private:1"
}
array(1) {
  [0]=>
  string(29) "ComplexScopeChild:protected:2"
}
array(1) {
  [0]=>
  string(29) "ComplexScopeChild:protected:3"
}
string(26) "ComplexScopeBase:private:4"
string(29) "ComplexScopeChild:protected:5"
string(26) "ComplexScopeBase:private:6"
string(13) "inner:7:outer"
bool(false)

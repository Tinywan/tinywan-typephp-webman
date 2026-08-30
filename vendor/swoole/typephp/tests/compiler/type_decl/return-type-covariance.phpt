--TEST--
Return type covariance: union narrowing and object subtype
--FILE--
<?php

interface UnionReturnContract
{
    public function make(): int|string;
}

class UnionReturnImpl implements UnionReturnContract
{
    // Covariant: narrowing a union return type (int|string -> int) is allowed.
    public function make(): int
    {
        return 42;
    }
}

class BaseType {}
class ChildType extends BaseType {}

interface ObjectReturnContract
{
    public function build(): BaseType;
}

class ObjectReturnImpl implements ObjectReturnContract
{
    // Covariant: returning a subtype (ChildType) for a BaseType return is allowed.
    public function build(): ChildType
    {
        return new ChildType();
    }
}

class StaticBase
{
    public function copy(): ?self
    {
        return $this;
    }
}

class StaticChild extends StaticBase
{
    public function copy(): ?static
    {
        return $this;
    }
}

interface IterableContract
{
    public function values(): iterable;
}

class IterableImpl implements IterableContract
{
    public function values(): array
    {
        return [1, 2];
    }
}

interface BoolContract
{
    public function enabled(): bool;
}

class LiteralBoolImpl implements BoolContract
{
    public function enabled(): true
    {
        return true;
    }
}

abstract class VoidContract
{
    abstract public function stop(): void;
}

abstract class NeverImpl extends VoidContract
{
    public function stop(): never
    {
        throw new RuntimeException('stop');
    }
}

function main()
{
    $impl = new UnionReturnImpl();
    var_dump($impl->make());

    $obj = new ObjectReturnImpl();
    $built = $obj->build();
    var_dump($built instanceof BaseType);
    var_dump($built instanceof ChildType);

    $static = new StaticChild();
    var_dump($static->copy() instanceof StaticChild);

    var_dump((new IterableImpl())->values());
    var_dump((new LiteralBoolImpl())->enabled());
}
?>
--EXPECT--
int(42)
bool(true)
bool(true)
bool(true)
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
bool(true)

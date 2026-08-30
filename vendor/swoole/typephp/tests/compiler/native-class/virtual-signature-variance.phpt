--TEST--
Native class: virtual dispatch preserves PHP parameter contravariance and return covariance
--FILE--
<?php

#[Native]
class NativeAnimal {}

#[Native]
class NativeDog extends NativeAnimal {}

#[Native]
class NativeTransformer
{
    public int $calls = 0;

    public function transform(NativeDog $value): NativeAnimal
    {
        return $value;
    }
}

#[Native]
class NativeDogTransformer extends NativeTransformer
{
    public function transform(NativeAnimal $value): NativeDog
    {
        $this->calls++;
        return new NativeDog();
    }
}

#[Native]
class NativeGrandDogTransformer extends NativeDogTransformer
{
    public function transform(NativeAnimal $value): NativeDog
    {
        $this->calls += 10;
        return new NativeDog();
    }
}

function transformThroughBase(NativeTransformer $transformer, NativeDog $value): NativeAnimal
{
    return $transformer->transform($value);
}

function transformThroughChild(NativeDogTransformer $transformer, NativeAnimal $value): NativeDog
{
    return $transformer->transform($value);
}

function main(): void
{
    $transformer = new NativeDogTransformer();
    $dog = new NativeDog();
    $animal = new NativeAnimal();
    $baseResult = transformThroughBase($transformer, $dog);
    $childResult = transformThroughChild($transformer, $animal);
    var_dump($transformer->calls);
    var_dump($baseResult instanceof NativeAnimal);
    var_dump($childResult instanceof NativeDog);

    $grand = new NativeGrandDogTransformer();
    transformThroughBase($grand, $dog);
    transformThroughChild($grand, $animal);
    var_dump($grand->calls);
}
?>
--EXPECT--
int(2)
bool(true)
bool(true)
int(20)

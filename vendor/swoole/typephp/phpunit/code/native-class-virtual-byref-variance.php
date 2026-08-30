<?php

#[Native]
class NativeByRefAnimal {}

#[Native]
class NativeByRefDog extends NativeByRefAnimal {}

#[Native]
abstract class NativeByRefTransformer
{
    abstract public function transform(NativeByRefDog &$value): void;
}

#[Native]
class NativeByRefAnimalTransformer extends NativeByRefTransformer
{
    public function transform(NativeByRefAnimal &$value): void {}
}

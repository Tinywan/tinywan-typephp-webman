<?php

class BaseAnimal
{
    public static function identify(): string
    {
        return 'base';
    }
}

class DogAnimal extends BaseAnimal
{
    public static function identify(): string
    {
        return 'dog';
    }
}

function makeAnimal(): BaseAnimal
{
    return new DogAnimal();
}

function getExactClass(): string
{
    $dog = new DogAnimal();
    return get_class($dog);
}

function getPolymorphicClass(): string
{
    $animal = makeAnimal();
    return get_class($animal);
}

function callExactStatic(): string
{
    $dog = new DogAnimal();
    return $dog::identify();
}

function callPolymorphicStatic(): string
{
    $animal = makeAnimal();
    return $animal::identify();
}

class BaseScopedCaller
{
    protected static function identifyProtected(): string
    {
        return static::class;
    }

    public static function exercise(BaseScopedCaller $obj): string
    {
        return $obj::identifyProtected();
    }
}

class ChildScopedCaller extends BaseScopedCaller
{
}

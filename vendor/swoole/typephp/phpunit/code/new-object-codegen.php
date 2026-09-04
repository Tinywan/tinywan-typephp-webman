<?php

class KnownNewObjectCodegen
{
    public int $value = 0;
}

class EmptyArrayDefaultCodegen
{
    public array $values = [];
}

class RuntimeArrayDefaultCodegen
{
    public int $scalar = 7;
    public array $values = [1];
    public array $labels = ['default'];
}

class ScalarExpressionDefaultCodegen
{
    public int $integer = 20 + 3;
    public string $string = 'type' . 'php';
}

class ScalarConstantDefaultCodegen
{
    private const VALUE = 23;

    public int $integer = self::VALUE;
}

enum EnumPropertyDefaultCaseCodegen
{
    case First;
}

class EnumPropertyDefaultCodegen
{
    public EnumPropertyDefaultCaseCodegen $value = EnumPropertyDefaultCaseCodegen::First;
}

class HookOnlyDefaultCodegen
{
    public string $value {
        get => $this->value;
        set => $value;
    }
}

class GetterOnlyDefaultCodegen
{
    public string $value {
        get => 'computed';
    }
}

class AsymmetricOnlyDefaultCodegen
{
    public private(set) int $value = 0;
}

function createKnownObjects(int $count): void
{
    for ($i = 0; $i < $count; ++$i) {
        $object = new KnownNewObjectCodegen();
    }
}

function createRuntimeObject(): object
{
    return new RuntimeProvidedObjectCodegen();
}

function main(): void
{
}

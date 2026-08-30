<?php

#[MethodsFor('*')]
class HierarchyKeywordMethods
{
    public static function keywordWins(any $value): string
    {
        return 'keyword';
    }
}

class HierarchyBase
{
    public function keywordWins(): string
    {
        return 'member';
    }

    public function realWins(): string
    {
        return 'member';
    }
}

class HierarchyChild extends HierarchyBase
{
}

#[MethodsFor(HierarchyBase::class)]
class HierarchyBaseMethods
{
    public static function inheritedExtension(HierarchyBase $value): string
    {
        return 'base';
    }

    public static function nearestExtension(HierarchyBase $value): string
    {
        return 'base';
    }
}

#[MethodsFor(HierarchyChild::class)]
class HierarchyChildMethods
{
    public static function nearestExtension(HierarchyChild $value): string
    {
        return 'child';
    }
}

#[MethodsFor(Type::Object)]
class HierarchyObjectMethods
{
    public static function objectFallback(object $value): string
    {
        return 'object';
    }

    public static function realWins(object $value): string
    {
        return 'extension';
    }

    public static function declaredMethod(object $value): string
    {
        return 'extension';
    }
}

interface HierarchyContract
{
    public function declaredMethod(): string;
}

class HierarchyImplementation implements HierarchyContract
{
    public function declaredMethod(): string
    {
        return 'declared';
    }
}

function hierarchy_calls(
    HierarchyChild $child,
    HierarchyBase $base,
    HierarchyContract $contract,
    object $object,
): void {
    echo $child->keywordWins();
    echo $child->realWins();
    echo $child->inheritedExtension();
    echo $child->nearestExtension();
    echo $base->nearestExtension();
    echo $child->objectFallback();
    echo $contract->declaredMethod();
    echo $contract->objectFallback();
    echo $object->objectFallback();
}

function hierarchy_nullable_call(?HierarchyChild $child): void
{
    // A nullable receiver is not statically guaranteed to be an object, so it
    // must not use the Type::Object fallback.
    echo $child->objectFallback();
}

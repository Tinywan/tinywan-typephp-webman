<?php

namespace LocalClassConstantInitializer;

class BaseValues
{
    public const LIMIT = 128;
}

class Values extends BaseValues
{
    public const LABEL = 'typephp';

    public function initialize(): void
    {
        $selfValue = self::LABEL;
        $parentValue = parent::LIMIT;
        $concreteValue = Values::LABEL;
        $selfClass = self::class;
        $parentClass = parent::class;
        $unknownClass = MissingClass::class;

        $lateStatic = static::LABEL;
        $external = \DateTimeInterface::ATOM;
        $runtimeClassConstant = RuntimeProvider::VALUE;
        $class = Values::class;
        $dynamicClass = $class::LABEL;

        var_dump(
            $selfValue,
            $parentValue,
            $concreteValue,
            $selfClass,
            $parentClass,
            $unknownClass,
            $lateStatic,
            $external,
            $runtimeClassConstant,
            $dynamicClass,
        );
    }
}

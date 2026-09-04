<?php

class ExactMagicHandler
{
    public function __call(string $name, array $arguments): mixed
    {
        return [$name, $arguments];
    }
}

class RuntimeMagicMethod extends ExactMagicHandler
{
    public function missing(): string
    {
        return 'real';
    }
}

final class ExactInternalMethod extends ArrayObject
{
}

function exactMagicCall(): mixed
{
    $handler = new ExactMagicHandler();
    return $handler->missing(1, named: 2);
}

function runtimeMagicCall(ExactMagicHandler $handler): mixed
{
    return $handler->missing();
}

function exactInternalMethod(): mixed
{
    $object = new ExactInternalMethod();
    $object->append('value');
    return $object->count();
}

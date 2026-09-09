<?php

function splRuntimeDirectCalls(Iterator $iterator, object $object, string $constantName): array
{
    return [
        iterator_count($iterator),
        iterator_to_array($iterator),
        iterator_to_array($iterator, false),
        spl_object_hash($object),
        spl_object_id($object),
        constant($constantName),
    ];
}

function splRuntimeDynamicConstant(mixed $constantName): mixed
{
    return constant($constantName);
}

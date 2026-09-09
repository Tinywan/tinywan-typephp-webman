<?php

final class TypedReferenceObjectClosureParamValue {}

function typedReferenceObjectClosureParam(): void
{
    $closure = function (TypedReferenceObjectClosureParamValue &$value): void {};
}

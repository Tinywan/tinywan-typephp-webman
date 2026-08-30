<?php

interface InterfaceParamUnionNarrowsMixed
{
    public function value(mixed $v);
}

class InterfaceParamUnionNarrowsMixedImpl implements InterfaceParamUnionNarrowsMixed
{
    public function value(string|int $v)
    {
    }
}

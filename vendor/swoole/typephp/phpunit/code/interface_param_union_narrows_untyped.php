<?php

interface InterfaceParamUnionNarrowsUntyped
{
    public function value($v);
}

class InterfaceParamUnionNarrowsUntypedImpl implements InterfaceParamUnionNarrowsUntyped
{
    public function value(string|int $v)
    {
    }
}

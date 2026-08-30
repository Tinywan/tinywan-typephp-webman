<?php
interface InterfaceArrayConstantContract
{
    public const ITEMS = [1, 2, 3];
}

class InterfaceArrayConstantImpl implements InterfaceArrayConstantContract
{
}

function main()
{
    var_dump(InterfaceArrayConstantImpl::ITEMS);
}

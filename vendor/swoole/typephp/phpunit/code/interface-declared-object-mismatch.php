<?php

interface InterfaceDeclaredAssignContract
{
}

class InterfaceDeclaredAssignImpl implements InterfaceDeclaredAssignContract
{
}

class InterfaceDeclaredAssignOther
{
}

function test_interface_declared_assign(InterfaceDeclaredAssignContract $object): void
{
    $object = new InterfaceDeclaredAssignImpl();
    $object = new InterfaceDeclaredAssignOther();
}

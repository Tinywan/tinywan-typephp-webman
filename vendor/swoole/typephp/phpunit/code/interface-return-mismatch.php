<?php

interface InterfaceReturnMismatchContract
{
}

class InterfaceReturnMismatchOther
{
}

function interface_return_mismatch(): InterfaceReturnMismatchContract
{
    return new InterfaceReturnMismatchOther();
}

<?php

interface InterfaceParamMismatchContract
{
}

class InterfaceParamMismatchOther
{
}

function interface_param_mismatch(InterfaceParamMismatchContract $object): void
{
}

function main(): void
{
    interface_param_mismatch(new InterfaceParamMismatchOther());
}

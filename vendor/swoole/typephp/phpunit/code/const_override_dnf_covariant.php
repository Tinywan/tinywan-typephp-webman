<?php

interface DnfLeft
{
}

interface DnfRight
{
}

enum DnfBoth implements DnfLeft, DnfRight
{
    case Value;
}

class DnfConstantParent
{
    const (DnfLeft&DnfRight)|stdClass VALUE = DnfBoth::Value;
}

class DnfConstantChild extends DnfConstantParent
{
    const DnfLeft&DnfRight VALUE = DnfBoth::Value;
}

function main(): void
{
}

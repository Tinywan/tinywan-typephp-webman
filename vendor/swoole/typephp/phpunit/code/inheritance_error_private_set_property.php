<?php

class PrivateSetPropertyParent
{
    public private(set) string $value = 'parent';
}

class PrivateSetPropertyChild extends PrivateSetPropertyParent
{
    public string $value = 'child';
}

<?php

class FinalPropertyParent
{
    final public string $value = 'parent';
}

class FinalPropertyChild extends FinalPropertyParent
{
    public string $value = 'child';
}

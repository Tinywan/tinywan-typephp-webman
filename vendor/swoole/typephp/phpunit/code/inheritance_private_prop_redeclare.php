<?php

class InheritancePrivatePropRedeclareParent
{
    private int $value = 1;
}

class InheritancePrivatePropRedeclareChild extends InheritancePrivatePropRedeclareParent
{
    private string $value = 'child';
}

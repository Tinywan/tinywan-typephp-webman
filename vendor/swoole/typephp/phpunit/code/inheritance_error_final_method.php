<?php

class InheritanceFinalMethodParent
{
    final public function run(): void
    {
    }
}

class InheritanceFinalMethodChild extends InheritanceFinalMethodParent
{
    public function run(): void
    {
    }
}

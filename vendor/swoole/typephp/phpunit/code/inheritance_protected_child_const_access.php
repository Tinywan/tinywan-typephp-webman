<?php

class InheritanceProtectedChildConstAccessParent
{
    public function readChild(): int
    {
        return InheritanceProtectedChildConstAccessChild::VALUE;
    }
}

class InheritanceProtectedChildConstAccessChild extends InheritanceProtectedChildConstAccessParent
{
    protected const VALUE = 1;
}

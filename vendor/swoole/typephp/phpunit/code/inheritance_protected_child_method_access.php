<?php

class InheritanceProtectedChildMethodAccessParent
{
    public function callChild(): void
    {
        InheritanceProtectedChildMethodAccessChild::run();
    }
}

class InheritanceProtectedChildMethodAccessChild extends InheritanceProtectedChildMethodAccessParent
{
    protected static function run(): void
    {
    }
}

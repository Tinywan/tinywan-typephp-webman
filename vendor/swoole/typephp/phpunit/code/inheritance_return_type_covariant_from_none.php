<?php

class InheritanceReturnNoneParent
{
    public function run()
    {
        return 1;
    }
}

class InheritanceReturnNoneChild extends InheritanceReturnNoneParent
{
    public function run(): int
    {
        return 1;
    }
}

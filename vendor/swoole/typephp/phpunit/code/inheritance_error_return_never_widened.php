<?php

abstract class NeverReturnParent
{
    abstract public function stop(): never;
}

abstract class NeverReturnChild extends NeverReturnParent
{
    public function stop(): void
    {
    }
}

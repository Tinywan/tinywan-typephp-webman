<?php

interface DnfReturnLeft {}
interface DnfReturnRight {}
final class DnfReturnFallback {}

interface DnfReturnParent
{
    public function create(): (DnfReturnLeft&DnfReturnRight)|DnfReturnFallback;
}

final class DnfReturnChild implements DnfReturnParent
{
    public function create(): DnfReturnLeft|DnfReturnFallback
    {
        return new DnfReturnFallback();
    }
}

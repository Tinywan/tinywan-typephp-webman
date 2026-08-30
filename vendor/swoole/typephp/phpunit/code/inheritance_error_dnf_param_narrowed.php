<?php

interface DnfParamLeft {}
interface DnfParamRight {}
final class DnfParamFallback {}

interface DnfParamParent
{
    public function consume(DnfParamLeft|DnfParamFallback $value): void;
}

final class DnfParamChild implements DnfParamParent
{
    public function consume((DnfParamLeft&DnfParamRight)|DnfParamFallback $value): void {}
}

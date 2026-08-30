<?php

interface InterfaceStaticContract
{
    public static function run(): void;
}

class InterfaceStaticImpl implements InterfaceStaticContract
{
    public function run(): void
    {
    }
}

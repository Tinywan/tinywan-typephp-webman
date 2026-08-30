<?php

interface MutableName
{
    public string $name { get; set; }
}

final class ReadOnlyName implements MutableName
{
    public string $name {
        get => 'name';
    }
}

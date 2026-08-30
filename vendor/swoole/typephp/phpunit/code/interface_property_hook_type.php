<?php

interface ReadableName
{
    public string $name { get; }
}

final class IntegerName implements ReadableName
{
    public int $name = 1;
}

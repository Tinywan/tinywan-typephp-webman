<?php

interface ReadableName
{
    public string $name { get; }
}

final class MissingName implements ReadableName
{
}

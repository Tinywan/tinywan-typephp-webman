<?php

interface ReadableName
{
    public string $name { get; }
}

final class PrivateName implements ReadableName
{
    private string $name = '';
}

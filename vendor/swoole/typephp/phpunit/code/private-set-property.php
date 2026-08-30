<?php

class PrivateSetRecord
{
    public private(set) string $name = 'default';
}

function main(): void
{
    $record = new PrivateSetRecord();
    $record->name = 'outside';
}

<?php

class ProtectedSetRecord
{
    public protected(set) int $score = 0;
}

function main(): void
{
    $record = new ProtectedSetRecord();
    $record->score = 1;
}

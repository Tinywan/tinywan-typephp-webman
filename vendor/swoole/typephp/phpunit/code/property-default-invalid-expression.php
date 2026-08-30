<?php

function loadPropertyDefaultValue(): int
{
    return 1;
}

class PropertyDefaultInvalidExpression
{
    public int $value = loadPropertyDefaultValue();
}

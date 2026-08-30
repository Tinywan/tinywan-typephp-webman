<?php

interface ExplicitSetter
{
    public string $value { set(string|int $value); }
}

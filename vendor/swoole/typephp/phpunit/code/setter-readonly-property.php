<?php

class SetterReadonlyProperty
{
    #[Setter]
    private readonly int $value;
}

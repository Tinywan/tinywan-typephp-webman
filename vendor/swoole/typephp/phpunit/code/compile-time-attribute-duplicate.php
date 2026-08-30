<?php

use \Getter as ReadProperty;

class CompileTimeAttributeDuplicate
{
    #[Getter]
    #[ReadProperty]
    private string $name = 'TypePHP';
}

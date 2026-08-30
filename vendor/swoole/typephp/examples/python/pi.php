<?php

use const python\math\pi;
use function python\platform\python_version;

function main()
{
    echo pi, "\n";
    var_dump(get_class(pi));
    var_dump(pi->toValue()->toFloat());
    var_dump(pi->toValue()->toInt());
    echo python_version(), "\n";
}
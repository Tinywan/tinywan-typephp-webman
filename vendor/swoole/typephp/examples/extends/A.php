<?php


class A extends Foo
{
    const FOO = PHP_OS;
    const FOO2 = PHP_INT_SIZE;
    const BAZ = self::INIT_STATE;

    public function __construct()
    {
        var_dump(self::BAZ);
        echo "A::__construct()\n";
    }
}

<?php

class ThrowTest extends \BaseTest
{
    public function testThrowScalarStaticError()
    {
        $this->exec('Can only throw objects', 'throw-scalar.php');
    }
}

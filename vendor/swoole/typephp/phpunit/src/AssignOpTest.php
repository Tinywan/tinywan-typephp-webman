<?php

class AssignOpTest extends \BaseTest
{
    public function testAssignOpUndefinedVar()
    {
        $this->exec('Cannot assign to undefined variable', 'assign-op-undefined-var.php');
    }

    public function testConcatToArray()
    {
        $this->exec('Cannot concat string to array', 'assign-op-concat-array.php');
    }

    public function testBigIntPreInc()
    {
        $this->exec('Cannot use ++ on php::BigInt', 'bigint-pre-inc.php');
    }

    public function testBigIntPostInc()
    {
        $this->exec('Cannot use ++ on php::BigInt', 'bigint-post-inc.php');
    }

    public function testDecimalPreDec()
    {
        $this->exec('Cannot use -- on php::Decimal', 'decimal-pre-dec.php');
    }

    public function testPreIncrementUndefinedVar(): void
    {
        $this->exec('The variable `$value` is undefined', 'undefined-pre-inc.php');
    }

    public function testPreDecrementUndefinedVar(): void
    {
        $this->exec('The variable `$value` is undefined', 'undefined-pre-dec.php');
    }
}

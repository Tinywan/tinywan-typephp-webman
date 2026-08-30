<?php

class UniversalMethodCallTest extends \BaseTest
{
    public function testUnknownMethodOnInt()
    {
        $this->exec("Cannot call method `unknownMethod()` on variable of type php::Int", 'universal-method-int-undefined.php');
    }

    public function testAddWithWrongArgCount()
    {
        $this->exec('Method `add()` expects exactly 1 argument(s), 0 given', 'universal-method-int-wrong-args.php');
    }

    public function testMutatingMethodOnNonVarExpr()
    {
        $this->exec('Cannot call mutating method `push()` on a non-variable expression', 'universal-method-mutating-expr.php');
    }

    public function testVoidMethodCall()
    {
        $this->compile('void-method-call.php');
    }

}

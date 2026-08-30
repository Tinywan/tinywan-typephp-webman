<?php

class UndefineTest extends \BaseTest
{
    public function testUnset()
    {
        $this->exec('The variable `$u1` is undefined', 'undefined-vars-01.php');
        $this->exec('Unsupported unset type `Expr_FuncCall`', 'unset-01.php');
    }

    public function testUnsetEmptyArrayDimFetch()
    {
        $this->exec('Cannot use [] for array unset', 'unset-array-dim-fetch-empty.php');
    }

    public function testUnsetStaticProp(): void
    {
        $this->exec('Attempt to unset static property', 'unset-static-prop.php');
    }

    public function testUnsetReadonlyPropertyIsRejected(): void
    {
        $this->exec('Cannot unset readonly property `ReadonlyPropertyUnset::$value`', 'unset-readonly-property.php');
    }

    public function testUnsetReadonlyClassPropertyIsRejected(): void
    {
        $this->exec(
            'Cannot unset readonly property `ReadonlyClassPropertyUnset::$value`',
            'unset-readonly-class-property.php'
        );
    }

    public function testPropertyAccessOnUndefinedVar(): void
    {
        $this->compile('undefined-prop-access.php');
    }

    public function testMethodCallOnUndefinedVar(): void
    {
        $this->exec('The variable `$obj` is undefined', 'undefined-method-call.php');
    }
}

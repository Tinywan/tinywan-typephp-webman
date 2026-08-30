<?php

class CompositeStaticTypeTest extends \BaseTest
{
    public function testLiteralFalseDoesNotMatchTrueUnion(): void
    {
        $this->exec(
            'Cannot assign bool to property assignment of type `true|null`',
            'composite-true-false-mismatch.php'
        );
    }

    public function testExplicitEmptyReturnIsRejectedStatically(): void
    {
        $this->exec(
            'Cannot assign null to return value of type `int|string`',
            'composite-empty-return-mismatch.php'
        );
    }

    public function testClosureReturnIsCheckedStatically(): void
    {
        $this->exec(
            'Cannot assign array to closure return value of type `int|string`',
            'composite-closure-return-mismatch.php'
        );
    }

    public function testArrowFunctionReturnIsCheckedStatically(): void
    {
        $this->exec(
            'Cannot assign array to closure return value of type `int|string`',
            'composite-arrow-return-mismatch.php'
        );
    }

    public function testExternalActualClassAgainstKnownInterfaceRemainsRuntimeUnknown(): void
    {
        $this->compile('composite-external-actual-unknown.php');
    }
}

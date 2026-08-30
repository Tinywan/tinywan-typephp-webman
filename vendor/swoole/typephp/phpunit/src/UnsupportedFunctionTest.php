<?php

class UnsupportedFunctionTest extends BaseTest
{
    public function testExtractIsRejectedAtCompileTime(): void
    {
        $this->exec('Unsupported function: `extract`', 'unsupported-function-extract.php');
    }

    public function testFullyQualifiedExtractIsRejectedAtCompileTime(): void
    {
        $this->exec('Unsupported function: `extract`', 'unsupported-function-extract-qualified.php');
    }
}

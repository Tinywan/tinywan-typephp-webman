<?php

final class WasmExportAttributeTest extends BaseTest
{
    public function testAcceptsNamedFunctionWithConstantExportName(): void
    {
        $this->compile('wasm-export-valid.php');
    }

    public function testRejectsMethods(): void
    {
        $this->expectException(\TypePhp\Exception\SyntaxError::class);
        $this->expectExceptionMessage('WasmExport can only be applied to named functions');
        $this->compile('wasm-export-invalid-method.php');
    }

    public function testRejectsNonStringName(): void
    {
        $this->exec('WasmExport name must be a constant string', 'wasm-export-invalid-name.php');
    }
}

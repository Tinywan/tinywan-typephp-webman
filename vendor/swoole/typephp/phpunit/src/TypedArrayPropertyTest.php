<?php

use TypePhp\CompilerTest;

final class TypedArrayPropertyTest extends \BaseTest
{
    public function testPropertyDeclarationAndDirectWriteDiagnostics(): void
    {
        $this->exec('unless it is array', 'typed-array-property-non-array-property.php');
        $this->exec('expects 1 type argument', 'typed-array-property-no-arguments.php');
        $this->exec('expects 2 type argument', 'typed-array-property-too-many-arguments.php');
        $this->exec('key only supports Type::Int or Type::String', 'typed-array-property-invalid-map-key.php');
        $this->exec('key only supports Type::Int or Type::String', 'typed-array-property-class-map-key.php');
        $this->exec('StdDict properties do not support append writes', 'typed-array-property-map-append.php');
        $this->exec('Typed array key must have type', 'typed-array-property-static-key-mismatch.php');
        $this->exec('Typed array value must have type', 'typed-array-property-static-value-mismatch.php');
        $this->exec('Typed array value must be an instance of TypedArrayPropertyExpectedUser', 'typed-array-property-static-class-mismatch.php');
        $this->exec('Typed PHP arrays cannot hold Native objects', 'typed-array-property-native-class-value.php');
        $this->exec('Typed array value must have type', 'typed-array-property-std-container-value.php');
    }

    public function testListIndexAllowsSparseAndNegativeKeysWithoutBoundsChecks(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/typed-array-property-inclusive-upper-bound.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertStringNotContainsString('php::safeArrayIndex(', $code);
        self::assertStringNotContainsString('.length() + 1', $code);
        self::assertStringNotContainsString('.newItem()', $code);
    }
}

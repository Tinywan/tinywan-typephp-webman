<?php

use TypePhp\CompilerTest;

final class ArrayDefTest extends \BaseTest
{
    public function testArrayDefDeclarationAndDirectWriteDiagnostics(): void
    {
        $this->exec('ArrayDef can only be applied to properties declared as array', 'array-def-non-array-property.php');
        $this->exec('ArrayDef expects one or two type arguments', 'array-def-no-arguments.php');
        $this->exec('ArrayDef expects one or two type arguments', 'array-def-too-many-arguments.php');
        $this->exec('ArrayDef map keys must use Type::Int or Type::String', 'array-def-invalid-map-key.php');
        $this->exec('ArrayDef map keys must use Type::Int or Type::String', 'array-def-class-map-key.php');
        $this->exec('ArrayDef map properties do not support append writes', 'array-def-map-append.php');
        $this->exec('expects key of type int, string given', 'array-def-static-key-mismatch.php');
        $this->exec('expects value of type string, int given', 'array-def-static-value-mismatch.php');
        $this->exec('expects value of type ArrayDefExpectedUser, ArrayDefOtherUser given', 'array-def-static-class-mismatch.php');
        $this->exec('Native class types cannot be used in ArrayDef', 'array-def-native-class-value.php');
        $this->exec('Std Container values cannot be stored in ArrayDef properties', 'array-def-std-container-value.php');
    }

    public function testListIndexUsesPhpAppendBoundaryWithoutAstSpecialCase(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/array-def-inclusive-upper-bound.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertSame(2, substr_count($code, 'php::safeArrayIndex('));
        self::assertStringNotContainsString('.length() + 1', $code);
        self::assertStringNotContainsString('.newItem()', $code);
    }
}

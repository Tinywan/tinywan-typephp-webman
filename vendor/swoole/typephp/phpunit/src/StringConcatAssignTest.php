<?php

use TypePhp\CompilerTest;

final class StringConcatAssignTest extends \BaseTest
{
    public function testTypedStringConcatAssignmentUsesInPlaceAppend(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/string-concat-assign.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertGreaterThanOrEqual(3, substr_count($code, 'value.append('));
        self::assertStringContainsString('value.append(php::concat(', $code);
        self::assertStringNotContainsString('value = php::concat({value,', $code);
    }
}

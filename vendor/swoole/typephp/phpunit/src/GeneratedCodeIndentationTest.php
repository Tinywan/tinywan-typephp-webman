<?php

use TypePhp\CompilerTest;

class GeneratedCodeIndentationTest extends \PHPUnit\Framework\TestCase
{
    public function testNestedStatementsAndZendWrappersAreConsistentlyIndented(): void
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/generated-code-indentation.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $cppFile = $compiler->convertFile($source);

        $this->assertNotNull($cppFile);
        $code = file_get_contents($cppFile);
        $this->assertIsString($code);
        $this->assertStringContainsString(
            "\t\twhile (tmp_var_0.next()) {\n\t\t\titem = tmp_var_0.value();",
            $code,
        );
        $this->assertStringContainsString(
            "\t\t\ttry {\n\t\t\t\tif (",
            $code,
        );
        $this->assertStringContainsString(
            "\t\t\t\t} else {\n\t\t\t\t\tif (",
            $code,
        );
        $this->assertStringContainsString(
            "\ttry {\n\t\tphp::checkCallArgCount(1, 1, false);",
            $code,
        );
        $this->assertStringNotContainsString("\ntry {", $code);
        $this->assertStringNotContainsString("\ncatch (zend_object", $code);
        $this->assertDoesNotMatchRegularExpression('/}[ \\t]+}/', $code);
        $this->assertStringContainsString(
            "php::Var php_generated_empty_function() {\n\treturn php::null;\n}",
            $code,
        );
        $this->assertStringContainsString(
            "\tphp::Var value = 1L;\n\n\treturn php::null;\n}",
            $code,
        );
        $this->assertStringNotContainsString('return php::null;}', $code);
    }
}

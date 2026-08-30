<?php

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;

final class MultiReturnTest extends TestCase
{
    public function testGeneratesTupleFastPathAndArrayCompatibilityAdapter(): void
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $file = __DIR__ . '/../code/multi-return-tuple.php';
        $compiler->addFiles([$file]);
        $compiler->prepareFile($file);
        $cppFile = $compiler->convertFile($file);
        $code = file_get_contents($cppFile);

        $this->assertStringContainsString(
            'std::tuple<php::Var, php::Var> typephp::detail::php_phpunit_multi_values()',
            $code,
        );
        $this->assertStringContainsString(
            'std::tie(first, second) = typephp::detail::php_phpunit_multi_values()',
            $code,
        );
        $this->assertMatchesRegularExpression(
            '/std::tuple<php::Var, php::Var> (tmp_var_\\d+);\\s*'
            . 'std::get<0>\\(\\1\\) = std::move\\(first\\);\\s*'
            . 'std::get<1>\\(\\1\\) = std::move\\(second\\);\\s*'
            . 'return \\1;/',
            $code,
        );
        $this->assertMatchesRegularExpression(
            '/std::tuple<php::Var, php::Var, php::Var> (tmp_var_\\d+);\\s*'
            . 'std::get<0>\\(\\1\\) = repeated;\\s*'
            . 'std::get<1>\\(\\1\\) = std::move\\(repeated\\);\\s*'
            . 'std::get<2>\\(\\1\\) = std::move\\(tail\\);/',
            $code,
        );
        $this->assertStringContainsString(
            'php::Array php_phpunit_multi_values()',
            $code,
        );
        $this->assertStringContainsString(
            'array = php_phpunit_multi_values()',
            $code,
        );
        $this->assertStringContainsString(
            'return php::Array(typephp::detail::php_phpunit_multi_forward_args('
            . 'php::takeValue(value), php::takeValue(text), php::takeValue(items), '
            . 'php::takeValue(object), count, reference, php::takeValue(rest)));',
            $code,
        );
        $this->assertStringContainsString(
            'return php::Array(typephp::detail::php_phpunit_multi_forward_defaults('
            . 'php::takeValue(text), php::takeValue(items), php::takeValue(rest)));',
            $code,
        );
        $this->assertStringNotContainsString('php::takeValue(reference)', $code);
        $this->assertStringNotContainsString('php::takeValue(count)', $code);
        $this->assertStringContainsString(
            'std::tie(partialFirst, partialSecond, std::ignore) = typephp::detail::php_phpunit_multi_three_values()',
            $code,
        );
        $this->assertStringNotContainsString(
            'std::tie(overflowFirst, overflowSecond, overflowThird)',
            $code,
        );
        $this->assertStringNotContainsString(
            'typephp::detail::php_phpunit_multi_side_effect',
            $code,
        );
    }
}

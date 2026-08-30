<?php
/**
 * This file is part of Swoole-Compiler(AOT).
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

use TypePhp\CompilerTest;

/**
 * @internal
 * @coversNothing
 */
final class CloneWithCodegenTest extends BaseTest
{
    public function testMethodCloneWithPassesLexicalScopeToZend(): void
    {
        $code = $this->compileFixture();
        $method = $this->functionBody($code, 'php_clonewithcodegen__copy');

        self::assertMatchesRegularExpression(
            '/php::call\(get_persistent_class\([^;]+?, get_(?:persistent_)?func\(/',
            $method,
        );
    }

    public function testGlobalCloneWithDoesNotInventClassScope(): void
    {
        $code = $this->compileFixture();
        $function = $this->functionBody($code, 'php_clone_with_global');

        self::assertMatchesRegularExpression('/php::call\(get_(?:persistent_)?func\(/', $function);
        self::assertStringNotContainsString('php::call(get_persistent_class(', $function);
    }

    private function compileFixture(): string
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/clone-with-codegen.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        return $code;
    }

    private function functionBody(string $code, string $function): string
    {
        $start = strpos($code, $function . '(');
        self::assertIsInt($start);
        $end = strpos($code, "\n}\n", $start);
        self::assertIsInt($end);

        return substr($code, $start, $end - $start + 3);
    }
}

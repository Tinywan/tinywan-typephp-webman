<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;

/**
 * @internal
 * @coversNothing
 */
class ClassExistsTraitFoldTest extends TestCase
{
    public function testTraitNameFoldsToFalse(): void
    {
        $cpp = $this->compileToCpp('class-exists-trait.php');

        // The trait is known at compile time, so the call is still folded -
        // just to the answer PHP gives, which is false for a trait.
        self::assertStringNotContainsString('php::fn::class_exists(', $cpp);
        self::assertStringContainsString('= false;', $cpp);
    }

    public function testClassAndEnumNamesStillFoldToTrue(): void
    {
        $cpp = $this->compileToCpp('class-exists-class-and-enum.php');

        self::assertStringNotContainsString('php::fn::class_exists(', $cpp);
        self::assertStringNotContainsString('= false;', $cpp);
    }

    public function testExplicitAutoloadArgumentUsesNormalCallPath(): void
    {
        $cpp = $this->compileToCpp('class-exists-autoload-argument.php');

        self::assertStringContainsString('php::fn::class_exists(', $cpp);
        self::assertStringContainsString('php_autoloadflag()', $cpp);
    }

    private function compileToCpp(string $file): string
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/' . $file;
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);

        return file_get_contents($compiler->convertFile($source));
    }
}

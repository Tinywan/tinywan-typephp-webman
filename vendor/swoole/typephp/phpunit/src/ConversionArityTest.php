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
class ConversionArityTest extends TestCase
{
    public function testIntvalWithABaseReachesTheRuntimeFunction(): void
    {
        $cpp = $this->compileToCpp('intval-with-base.php');

        // The Native cast carries no base, so both calls must keep the second
        // argument by going through the dynamic path.
        self::assertStringNotContainsString('php::toInt(', $cpp);
        self::assertSame(2, substr_count($cpp, '16L'));
    }

    public function testUnpackedAndNamedArgumentsStayOnTheDynamicPath(): void
    {
        $cpp = $this->compileToCpp('intval-unpacked-argument.php');

        // An unpacked argument is one Node\Arg whatever its runtime arity is,
        // so the array itself must never be handed to a Native cast.
        self::assertStringNotContainsString('php::toInt(', $cpp);
        self::assertStringNotContainsString('php::toString(', $cpp);
        self::assertStringNotContainsString('php::toFloat(', $cpp);
        self::assertStringNotContainsString('php::toBool(', $cpp);

        // Five full unpacks plus the partial intval('ff', ...[16]).
        self::assertSame(6, substr_count($cpp, 'appendUnpacked('));
    }

    public function testSingleArgumentConversionsStillLowerToNativeCasts(): void
    {
        $cpp = $this->compileToCpp('intval-single-argument.php');

        self::assertStringContainsString('php::toInt(', $cpp);
        self::assertStringContainsString('php::toString(', $cpp);
        self::assertStringContainsString('php::toFloat(', $cpp);
        self::assertStringContainsString('php::toBool(', $cpp);
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

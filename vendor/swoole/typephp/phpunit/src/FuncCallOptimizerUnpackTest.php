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
class FuncCallOptimizerUnpackTest extends TestCase
{
    public function testOptimizedFunctionsLeaveArgumentUnpackingToTheRuntime(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/func-call-optimizer-unpack.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $cpp = file_get_contents($compiler->convertFile($source));

        self::assertSame(5, substr_count($cpp, '.appendUnpacked('));
        self::assertStringNotContainsString('php::toInt(', $cpp);
        self::assertStringNotContainsString('php::fn::round(', $cpp);
        self::assertStringNotContainsString('php::fn::array_keys(', $cpp);
    }
}

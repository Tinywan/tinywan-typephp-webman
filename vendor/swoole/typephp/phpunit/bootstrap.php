<?php

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

require __DIR__ . '/../bin/bootstrap.php';

// The vendor directory may be shared between checkouts (e.g. a git worktree
// with a symlinked vendor/). Composer's autoloader resolves TypePhp\ against
// the checkout that owns vendor/, which would silently test another tree's
// sources. Prepend a loader anchored to THIS checkout so the test suite always
// exercises the code it ships with.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'TypePhp\\')) {
        $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen('TypePhp\\'))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
}, true, true);

require_once __DIR__ . '/../src/polyfills.php';
require __DIR__ . '/../src/gen_stub.php';

class BaseTest extends TestCase
{
    protected function compile(string $file): void
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $testFile = __DIR__ . '/code/' . $file;
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $compiler->convertFile($testFile);
        $this->addToAssertionCount(1);
    }

    protected function exec(string $expected, string $file): void
    {
        try {
            $this->compile($file);
        } catch (TestError $exception) {
            $this->assertStringContainsString($expected, $exception->getMessage());
            return;
        }
        $this->fail();
    }
}

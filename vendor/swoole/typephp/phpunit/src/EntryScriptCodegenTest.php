<?php

use TypePhp\CompilerBase;
use TypePhp\CompilerTest;

final class EntryScriptCodegenTest extends BaseTest
{
    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectDir = sys_get_temp_dir() . '/typephp_entry_script_' . bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
        parent::tearDown();
    }

    public function testMainLinePaddingDoesNotBloatGeneratedExtension(): void
    {
        $source = $this->projectDir . '/main.php';
        file_put_contents(
            $source,
            "<?php\n" . str_repeat("\n", 256) . <<<'PHP'
function main(): void
{
}
PHP,
        );

        global $translator;
        $compiler = CompilerTest::create($this->projectDir);
        $translator = $compiler;
        $compiler->setBuildMode(CompilerBase::BUILD_MODE_BIN);
        $compiler->setTargetName('entry_script');
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $compiler->convertFile($source);

        $extension = file_get_contents($compiler->genExtension());

        self::assertStringContainsString(
            'php::eval(std::string(257, \'\\n\') + "main();",',
            $extension,
        );
        self::assertStringNotContainsString('php::eval("\\n', $extension);
        self::assertStringNotContainsString('zend_disable_functions(', $extension);
    }

    public function testNanoEntrypointForwardsArgcAndArgvWithTheSharedContract(): void
    {
        $source = $this->projectDir . '/main.php';
        file_put_contents($source, <<<'PHP'
<?php
function main(int $argc, array $argv): void
{
}
PHP);

        global $translator;
        $compiler = CompilerTest::create($this->projectDir);
        $translator = $compiler;
        $files = $compiler->prepareNanoSources(
            [$source],
            'nano_args',
            $this->projectDir . '/build',
            false,
        );
        $compiler->convert($files);

        $entrypoint = file_get_contents($this->projectDir . '/build/nano-entry-nano_args.cc');
        self::assertStringContainsString(
            'php_main(php::global("argc").toInt(), php::global("argv").toArray())',
            $entrypoint,
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (array_diff(scandir($directory), ['.', '..']) as $entry) {
            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}

<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TypePhp\CompilerBase;
use TypePhp\CompilerTest;
use TypePhp\Translator;

class ServerEnvironmentTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . '/server_environment_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->testDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testGeneratedServerEnvironmentMatchesCliAndEscapesScriptPath(): void
    {
        $compiler = CompilerTest::create($this->testDir);
        $method = new ReflectionMethod(Translator::class, 'registerServerEnvironment');
        $code = $method->invoke($compiler, 'C:\\project\\"quoted"\\main.php');

        $this->assertStringContainsString(
            'const char *value = "' . $compiler->escapeString('C:\\project\\"quoted"\\main.php') . '";',
            $code
        );
        $this->assertStringContainsString('php::Var &_SERVER = _global_var__SERVER;', $code);
        $this->assertStringContainsString('_SERVER.item("PHP_SELF", true) = value;', $code);
        $this->assertStringContainsString('_SERVER.item("SCRIPT_NAME", true) = value;', $code);
        $this->assertStringContainsString('_SERVER.item("SCRIPT_FILENAME", true) = value;', $code);
        $this->assertStringContainsString('_SERVER.item("PATH_TRANSLATED", true) = value;', $code);
        $this->assertStringContainsString('_SERVER.item("DOCUMENT_ROOT", true) = "";', $code);
        $this->assertStringNotContainsString('php::Str', $code);
    }

    public function testCaseInsensitiveMainWrapperInitializesServerEnvironment(): void
    {
        global $translator;
        $previousTranslator = $translator ?? null;
        $file = $this->testDir . '/uppercase-main.php';
        file_put_contents($file, "<?php\nfunction MAIN(): void {}\n");

        try {
            $compiler = CompilerTest::create($this->testDir);
            $translator = $compiler;
            $compiler->addFiles([$file]);
            $compiler->prepareFile($file);
            $cppFile = $compiler->convertFile($file);
            $code = file_get_contents($cppFile);
        } finally {
            $translator = $previousTranslator;
        }

        $this->assertStringContainsString(
            'const char *value = "' . $compiler->escapeString(realpath($file)) . '";',
            $code
        );
        $this->assertStringContainsString('_SERVER.item("PHP_SELF", true) = value;', $code);
    }

    public function testServerGlobalIsForcedOnlyForBinaryBuilds(): void
    {
        $binaryCompiler = CompilerTest::create($this->testDir);
        $binaryFile = $this->testDir . '/binary.h';
        $binaryCompiler->genDataDeclarations($binaryFile);
        $this->assertStringContainsString('_global_var__SERVER', file_get_contents($binaryFile));

        $extensionCompiler = CompilerTest::create($this->testDir);
        $extensionCompiler->setBuildMode(CompilerBase::BUILD_MODE_EXT);
        $extensionFile = $this->testDir . '/extension.h';
        $extensionCompiler->genDataDeclarations($extensionFile);
        $this->assertStringNotContainsString('_global_var__SERVER', file_get_contents($extensionFile));
    }
}

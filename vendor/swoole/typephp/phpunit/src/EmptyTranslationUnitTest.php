<?php

use TypePhp\CompilerTest;

final class EmptyTranslationUnitTest extends \BaseTest
{
    private string $projectDir;
    private CompilerTest $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectDir = sys_get_temp_dir() . '/typephp_empty_translation_' . bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0777, true);

        global $translator;
        $this->compiler = CompilerTest::create($this->projectDir);
        $translator = $this->compiler;
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
        parent::tearDown();
    }

    public function testTraitOnlySourceIsNotAddedToCompilation(): void
    {
        $trait = $this->writeSource('CompileTimeOnlyTrait.php', <<<'PHP'
<?php

trait CompileTimeOnlyTrait
{
    public function answer(): int
    {
        return 42;
    }
}
PHP);
        $program = $this->writeSource('program.php', <<<'PHP'
<?php

function main(): void
{
}
PHP);

        $files = [$trait, $program];
        $this->compiler->addFiles($files);
        foreach ($files as $file) {
            $this->compiler->prepareFile($file);
        }

        $traitCpp = $this->compiler->getCppFile($trait);
        $traitObject = $this->compiler->getObjectFile($traitCpp);
        if (!is_dir(dirname($traitCpp))) {
            mkdir(dirname($traitCpp), 0777, true);
        }
        file_put_contents($traitCpp, 'stale translation unit');
        file_put_contents($traitObject, 'stale object');
        file_put_contents($traitObject . '.typephp-cache', 'stale metadata');

        $sources = $this->compiler->convert($files);

        self::assertNotContains($traitCpp, $sources);
        self::assertContains($this->compiler->getCppFile($program), $sources);
        self::assertFileDoesNotExist($traitCpp);
        self::assertFileDoesNotExist($traitObject);
        self::assertFileDoesNotExist($traitObject . '.typephp-cache');
        self::assertFileExists($this->compiler->getArgInfoHeaderFile($trait));
    }

    public function testFileContainingTraitAndFunctionStillEmitsTranslationUnit(): void
    {
        $source = $this->writeSource('mixed.php', <<<'PHP'
<?php

trait MixedDeclarationTrait
{
    public function value(): int
    {
        return 1;
    }
}

function mixedDeclarationFunction(): int
{
    return 7;
}
PHP);

        $this->compiler->addFiles([$source]);
        $this->compiler->prepareFile($source);
        $cppFile = $this->compiler->convertFile($source);

        self::assertNotNull($cppFile);
        self::assertFileExists($cppFile);
        self::assertStringContainsString('return php::toInt(7L);', file_get_contents($cppFile));
    }

    public function testCompileTimeOnlyLibraryStillEmitsExtensionSource(): void
    {
        $source = $this->writeSource('LibraryTrait.php', <<<'PHP'
<?php

trait LibraryTrait
{
    public function value(): int
    {
        return 1;
    }
}
PHP);

        $this->compiler->setBuildMode('lib');
        $this->compiler->setOutputPath($this->projectDir . '/app');
        $this->compiler->addFiles([$source]);
        $this->compiler->prepareFile($source);
        $sources = $this->compiler->convert([$source]);

        self::assertCount(1, $sources);
        self::assertStringContainsString('extension-app.cc', $sources[0]);
        self::assertFileExists($sources[0]);
        self::assertFileDoesNotExist($this->compiler->getCppFile($source));
    }

    private function writeSource(string $name, string $source): string
    {
        $file = $this->projectDir . '/' . $name;
        file_put_contents($file, $source);
        return $file;
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

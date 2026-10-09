<?php

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;

final class TranslationUnitSplitTest extends TestCase
{
    private string $directory;
    private CompilerTest $compiler;
    private string $source;

    protected function setUp(): void
    {
        global $translator;
        $this->directory = sys_get_temp_dir() . '/typephp_split_' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->source = $this->directory . '/source.php';
        file_put_contents($this->source, '<?php function main(): void {}');
        $this->compiler = CompilerTest::create($this->directory);
        $translator = $this->compiler;
        $this->compiler->setTargetName('split');
        $this->compiler->prepareFile($this->source);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->directory);
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        return (new \ReflectionMethod($this->compiler, $method))->invoke($this->compiler, ...$arguments);
    }

    private function set(string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($this->compiler);
        while (!$reflection->hasProperty($property)) {
            $reflection = $reflection->getParentClass();
        }
        $reflection->getProperty($property)->setValue($this->compiler, $value);
    }

    public function testCompleteEntitiesKeepTheirLocalDataAndStalePartsAreRemoved(): void
    {
        $primary = $this->invoke('getCppFile', $this->source);
        $body = "void large_method() {\n" . str_repeat("// body\n", 2000) . "use(blob);\n}\n";
        $small = "void small_helper() {}\n";
        $code = str_repeat("// padding\n", 200000) . $body . $small;
        $this->set('splitTranslationUnitsEnabled', true);
        $this->set('generatedMethodBodies', [$body, $small]);
        $this->set('constData', ['blob' => '1,2,3', 'unused_blob' => '4,5,6']);
        $this->set('globalVarsInFile', [$this->source => ['_GET' => 'php::Array']]);
        $remaining = $this->invoke('splitLargeTranslationUnit', $code, $primary, false);
        $parts = $this->invoke('getSplitTranslationUnits', $this->source);
        self::assertCount(1, $parts);
        self::assertStringNotContainsString($body, $remaining);
        self::assertStringContainsString($small, $remaining);
        $part = file_get_contents($parts[0]);
        self::assertStringContainsString($body, $part);
        self::assertStringContainsString('extern THREAD_LOCAL php::Var _global_var__GET;', $part);
        self::assertStringContainsString('static const unsigned char blob[]', $part);
        self::assertStringNotContainsString('unused_blob', $part);
        self::assertStringNotContainsString('_arginfo.h', $part);
        $this->invoke('splitLargeTranslationUnit', $small, $primary, false);
        self::assertSame([], $this->invoke('getSplitTranslationUnits', $this->source));
        self::assertFileDoesNotExist($parts[0]);
    }

    public function testStandaloneConversionIsNotSilentlySplit(): void
    {
        $primary = $this->invoke('getCppFile', $this->source);
        $body = str_repeat('// body', 2000);
        $code = str_repeat('// prefix', 250000) . $body;
        $this->set('generatedMethodBodies', [$body]);
        self::assertSame($code, $this->invoke('splitLargeTranslationUnit', $code, $primary, false));
        self::assertSame([], $this->invoke('getSplitTranslationUnits', $this->source));
    }

    public function testSwitchingBackToStandaloneConversionRemovesOldParts(): void
    {
        $primary = $this->invoke('getCppFile', $this->source);
        $body = "void large_method() {\n" . str_repeat("// body\n", 2000) . "}\n";
        $code = str_repeat("// padding\n", 200000) . $body;
        $this->set('splitTranslationUnitsEnabled', true);
        $this->set('generatedMethodBodies', [$body]);
        $this->invoke('splitLargeTranslationUnit', $code, $primary, false);
        $parts = $this->invoke('getSplitTranslationUnits', $this->source);
        self::assertCount(1, $parts);
        $this->set('splitTranslationUnitsEnabled', false);
        self::assertSame($code, $this->invoke('splitLargeTranslationUnit', $code, $primary, false));
        self::assertSame([], $this->invoke('getSplitTranslationUnits', $this->source));
        self::assertFileDoesNotExist($parts[0]);
    }

    public function testManifestCannotAuthorizeAnUnrelatedPath(): void
    {
        $primary = $this->invoke('getCppFile', $this->source);
        if (!is_dir(dirname($primary))) {
            mkdir(dirname($primary), 0777, true);
        }
        file_put_contents($primary . '.parts.json', json_encode([$this->source, $primary . '/../other.cc']));
        self::assertSame([], $this->invoke('getSplitTranslationUnits', $this->source));
    }
}

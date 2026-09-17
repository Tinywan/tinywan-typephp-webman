<?php

use TypePhp\CompilerBase;
use TypePhp\CompilerTest;

final class ClassConstantLifecycleSplitTest extends BaseTest
{
    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectDir = sys_get_temp_dir() . '/typephp_class_constant_split_'
            . bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
        parent::tearDown();
    }

    public function testArrayConstantLifecycleIsSplitWithoutMovingArginfoRegistration(): void
    {
        $parent = $this->writeSource('parent.php', <<<'PHP'
<?php

class LifecycleParent
{
    public const array ITEMS = ['ready'];
}
PHP);
        $child = $this->writeSource('child.php', <<<'PHP'
<?php

class LifecycleChild extends LifecycleParent
{
}
PHP);

        global $translator;
        $compiler = CompilerTest::create($this->projectDir);
        $translator = $compiler;
        $compiler->setBuildMode(CompilerBase::BUILD_MODE_EXT);
        $compiler->setTargetName('lifecycle_split');
        $compiler->addFiles([$parent, $child]);
        foreach ([$parent, $child] as $file) {
            $compiler->prepareFile($file);
        }

        $sources = $compiler->convert($compiler->getSortedFiles([$parent, $child]));
        $lifecycleSources = array_values(array_filter(
            $sources,
            static fn (string $source): bool => str_contains($source, '/init/')
                && str_ends_with($source, '_class_constants.cc'),
        ));

        self::assertCount(2, $lifecycleSources);
        $lifecycleCode = implode(PHP_EOL, array_map('file_get_contents', $lifecycleSources));
        self::assertStringContainsString(
            'zend_never_inline void typephp_request_init_class_constant_',
            $lifecycleCode,
        );
        self::assertStringContainsString(
            'php::updateConstant("LifecycleParent", "ITEMS"',
            $lifecycleCode,
        );
        self::assertStringContainsString(
            'php::updateConstant("LifecycleChild", "ITEMS"',
            $lifecycleCode,
        );
        self::assertStringNotContainsString('_arginfo.h>', $lifecycleCode);

        $extension = (string) file_get_contents(
            $this->projectDir . '/build/extension-lifecycle_split.cc',
        );
        self::assertStringContainsString(
            $compiler->getArgInfoHeaderFile($parent, true) . '>',
            $extension,
        );
        self::assertStringContainsString(
            $compiler->getArgInfoHeaderFile($child, true) . '>',
            $extension,
        );
        self::assertStringContainsString(
            'php_class_entry_LifecycleParent = php_register_class_LifecycleParent();',
            $extension,
        );
        self::assertStringContainsString(
            'zend_never_inline void typephp_request_init_class_constant_',
            $extension,
        );

        $moduleInitStart = strpos($extension, 'static void module_init()');
        $moduleCleanStart = strpos($extension, 'static void module_clean()');
        self::assertIsInt($moduleInitStart);
        self::assertIsInt($moduleCleanStart);
        $moduleInit = substr($extension, $moduleInitStart, $moduleCleanStart - $moduleInitStart);
        self::assertStringContainsString('typephp_request_init_class_constant_', $moduleInit);
        self::assertStringNotContainsString('php::updateConstant("LifecycleParent"', $moduleInit);

        // Removing the last array-valued constant must also remove generated
        // lifecycle sources from the previous incremental graph.
        $this->writeSource('parent.php', <<<'PHP'
<?php

class LifecycleParent
{
    public const int ITEMS = 1;
}
PHP);
        $nextCompiler = CompilerTest::create($this->projectDir);
        $translator = $nextCompiler;
        $nextCompiler->setBuildMode(CompilerBase::BUILD_MODE_EXT);
        $nextCompiler->setTargetName('lifecycle_split');
        $nextCompiler->addFiles([$parent, $child]);
        foreach ([$parent, $child] as $file) {
            $nextCompiler->prepareFile($file);
        }
        $nextSources = $nextCompiler->convert($nextCompiler->getSortedFiles([$parent, $child]));
        self::assertSame(
            [],
            array_values(array_filter(
                $nextSources,
                static fn (string $source): bool => str_contains($source, '/init/')
                    && str_ends_with($source, '_class_constants.cc'),
            )),
        );
        self::assertSame([], glob($this->projectDir . '/build/init/*_class_constants.cc'));
    }

    private function writeSource(string $name, string $code): string
    {
        $file = $this->projectDir . '/' . $name;
        file_put_contents($file, $code);
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

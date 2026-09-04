<?php

use TypePhp\CompilerBase;
use TypePhp\CompilerTest;

final class InternalParentExtensionCodegenTest extends BaseTest
{
    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectDir = sys_get_temp_dir() . '/typephp_internal_parent_' . bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
        parent::tearDown();
    }

    public function testExtensionResolvesInternalParentFromCompilerClassTable(): void
    {
        $source = $this->projectDir . '/internal-parent.php';
        file_put_contents($source, <<<'PHP'
<?php

class InternalParentChild extends ArrayObject
{
}
PHP);

        global $translator;
        $compiler = CompilerTest::create($this->projectDir);
        $translator = $compiler;
        $compiler->setBuildMode(CompilerBase::BUILD_MODE_EXT);
        $compiler->setTargetName('internal_parent');
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $compiler->convertFile($source);

        $extension = file_get_contents($compiler->genExtension());

        self::assertStringContainsString(
            'php_class_entry_ArrayObject = php::getInternalClassEntrySafe("ArrayObject");',
            $extension,
        );
        self::assertStringNotContainsString(
            'php_class_entry_ArrayObject = php::getClassEntrySafe("ArrayObject");',
            $extension,
        );
        self::assertStringNotContainsString(
            "PHP_MINIT_FUNCTION(typephp_internal_parent) {\nzend_try {",
            $extension,
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

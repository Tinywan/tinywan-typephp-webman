<?php

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

final class InterfacePropertyHookTest extends TestCase
{
    private function compileFixture(string $file): void
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $path = __DIR__ . '/../code/' . $file;
        $compiler->addFiles([$path]);
        $compiler->prepareFile($path);
        $compiler->convertFile($path);
    }

    private function assertCompileError(string $file, string $message): void
    {
        try {
            $this->compileFixture($file);
        } catch (TestError $error) {
            self::assertStringContainsString($message, $error->getMessage());
            return;
        }
        self::fail('Expected compilation to fail');
    }

    public function testMissingPropertyIsRejected(): void
    {
        $this->assertCompileError(
            'interface_property_hook_missing.php',
            'must implement property `ReadableName::$name`',
        );
    }

    public function testNonPublicPropertyIsRejected(): void
    {
        $this->assertCompileError(
            'interface_property_hook_private.php',
            'must be public to satisfy `ReadableName::$name`',
        );
    }

    public function testIncompatiblePropertyTypeIsRejected(): void
    {
        $this->assertCompileError(
            'interface_property_hook_type.php',
            'must be compatible with `ReadableName::$name`',
        );
    }

    public function testMissingSetterIsRejected(): void
    {
        $this->assertCompileError(
            'interface_property_hook_setter.php',
            'does not satisfy the required hooks of `MutableName::$name`',
        );
    }

    public function testPlainInterfacePropertyIsRejected(): void
    {
        $this->assertCompileError(
            'interface_property_hook_plain.php',
            'Interfaces may only include hooked properties',
        );
    }

    public function testInterfaceHookBodyIsRejected(): void
    {
        $this->assertCompileError(
            'interface_property_hook_body.php',
            'Abstract property hook cannot have body',
        );
    }

    public function testFinalInterfacePropertyIsRejected(): void
    {
        $this->assertCompileError(
            'interface_property_hook_final.php',
            'Property in interface cannot be final',
        );
    }

    public function testExplicitSetterParameterIsRejectedUntilItsIndependentTypeIsModeled(): void
    {
        $this->assertCompileError(
            'interface_property_hook_explicit_setter.php',
            'Explicit setter parameters in interface property hooks are not supported yet',
        );
    }

    public function testDirectionalPropertyVarianceIsAccepted(): void
    {
        $this->compileFixture('interface_property_hook_variance.php');
        $this->addToAssertionCount(1);
    }
}

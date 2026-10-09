<?php

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionClass;
use TypePhp\CompilerBase;
use TypePhp\CompilerTest;
use TypePhp\Build\CompilerRuntime;
use TypePhp\Entity\ClassDef;
use TypePhp\Entity\FunctionDef;
use TypePhp\Type;

final class CompilerGeneratorFingerprintTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testEmbeddedSnapshotIsUsedOnlyByNativeEntry(): void
    {
        define('TYPEPHP_COMPILER_BUILD_FINGERPRINT', str_repeat('a', 64));
        $compiler = CompilerTest::create(
            TYPEPHP_ROOT_PATH,
            CompilerRuntime::nativeAt(TYPEPHP_ROOT_PATH, PHP_BINARY),
        );
        $method = (new ReflectionClass(\TypePhp\Translator::class))->getMethod('getIncrementalGeneratorFingerprint');
        $native = $method->invoke($compiler);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $native);
        $reflection = new ReflectionClass($compiler);
        while (!$reflection->hasProperty('opcodeBuildChecked')) {
            $reflection = $reflection->getParentClass();
        }
        self::assertFalse($reflection->getProperty('opcodeBuildChecked')->getValue($compiler));
        self::assertSame($native, $method->invoke($compiler));
        $sourceCompiler = CompilerTest::create(
            TYPEPHP_ROOT_PATH,
            CompilerRuntime::source(TYPEPHP_ROOT_PATH, PHP_BINARY),
        );
        self::assertNotSame($native, $method->invoke($sourceCompiler));
    }

    public function testCompilerSnapshotIsStableAndChangesWithSourceOrOptions(): void
    {
        $compiler = $this->compiler();
        $registration = $this->registration($compiler);
        self::assertMatchesRegularExpression('/php::fn::define\("TYPEPHP_COMPILER_BUILD_FINGERPRINT", php::Str\("[a-f0-9]{64}"\)\);/', $registration);
        self::assertSame($registration, $this->registration($compiler));

        $this->set($compiler, 'incrementalGeneratorFingerprint', 'a different predecessor');
        self::assertSame($registration, $this->registration($compiler));
        $this->set($compiler, 'incrementalSourceHashes', ['compiler.php' => 'changed']);
        self::assertNotSame($registration, $this->registration($compiler));
        $sourceRegistration = $this->registration($compiler);
        $this->set($compiler, 'optimizeLevel', 1);
        self::assertNotSame($sourceRegistration, $this->registration($compiler));
    }

    public function testOrdinaryProgramsAndExtensionsDoNotRegisterCompilerSnapshot(): void
    {
        $compiler = $this->compiler();
        $compiler->setBuildMode(CompilerBase::BUILD_MODE_EXT);
        self::assertSame('', $this->registration($compiler));
        $compiler->setBuildMode(CompilerBase::BUILD_MODE_BIN);
        $this->set($compiler, 'incrementalPlanInitialized', false);
        self::assertSame('', $this->registration($compiler));
        $this->set($compiler, 'incrementalPlanInitialized', true);
        $symbols = (new ReflectionClass($compiler))->getProperty('symbols')->getValue($compiler);
        $entry = $symbols->function((new ReflectionClass($compiler))->getMethod('escapeFunction')->invoke($compiler, 'main'));
        $entry->sourceFile = TYPEPHP_ROOT_PATH . '/src/CompilerBase.php';
        self::assertSame('', $this->registration($compiler));
    }

    private function compiler(): CompilerTest
    {
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $compiler->setBuildMode(CompilerBase::BUILD_MODE_BIN);
        $reflection = new ReflectionClass($compiler);
        $symbols = $reflection->getProperty('symbols')->getValue($compiler);
        $entry = new FunctionDef('main', Type::VOID, '');
        $entry->sourceFile = TYPEPHP_ROOT_PATH . '/src/compiler.php';
        $symbols->putFunction($reflection->getMethod('escapeFunction')->invoke($compiler, 'main'), $entry);
        $class = new ClassDef('Translator', 0, 'TypePhp');
        $class->sourceFile = TYPEPHP_ROOT_PATH . '/src/Translator.php';
        $symbols->putClass($reflection->getMethod('escapeClass')->invoke($compiler, 'TypePhp\\Translator'), $class);
        $this->set($compiler, 'incrementalPlanInitialized', true);
        $this->set($compiler, 'incrementalSourceHashes', ['compiler.php' => 'original']);
        return $compiler;
    }

    private function registration(CompilerTest $compiler): string
    {
        return (new ReflectionClass($compiler))->getMethod('genCompiledGeneratorFingerprintRegistration')->invoke($compiler);
    }

    private function set(CompilerTest $compiler, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($compiler);
        while (!$reflection->hasProperty($property)) {
            $reflection = $reflection->getParentClass();
        }
        $reflection->getProperty($property)->setValue($compiler, $value);
    }
}

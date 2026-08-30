<?php

use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

class CompilerPhaseProbe extends CompilerTest
{
    public static function createProbe(string $rootPath): self
    {
        $compiler = new self($rootPath);
        $compiler->forTest = true;
        return $compiler;
    }

    public function enterPreparePhase(): void
    {
        $this->enterCompilerPhase(self::PHASE_PREPARE);
    }

    public function enterConvertPhase(): void
    {
        $this->enterCompilerPhase(self::PHASE_CONVERT);
    }

    public function probePropertyAccessResolver(): bool
    {
        $method = new ReflectionMethod(\TypePhp\CompilerBase::class, 'createPropertyAccessResolver');
        $resolver = $method->invoke($this);
        return $resolver->canAccessProtectedProperty('ProbeClass', 'ProbeClass');
    }
}

class CompilerPhaseTest extends \PHPUnit\Framework\TestCase
{
    public function testPropertyAccessResolverCannotBeUsedOutsideConvertPhase(): void
    {
        $compiler = CompilerPhaseProbe::createProbe(TYPEPHP_ROOT_PATH);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('PropertyAccessResolver can only be used during convert phase');

        $compiler->probePropertyAccessResolver();
    }

    public function testPropertyAccessResolverCannotBeUsedDuringPreparePhase(): void
    {
        $compiler = CompilerPhaseProbe::createProbe(TYPEPHP_ROOT_PATH);
        $compiler->enterPreparePhase();

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('PropertyAccessResolver can only be used during convert phase');

        $compiler->probePropertyAccessResolver();
    }

    public function testPropertyAccessResolverCanBeUsedDuringConvertPhase(): void
    {
        $compiler = CompilerPhaseProbe::createProbe(TYPEPHP_ROOT_PATH);
        $compiler->enterConvertPhase();

        $this->assertTrue($compiler->probePropertyAccessResolver());
    }
}

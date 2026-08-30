<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use TypePhp\Exception\DynamicCall;
use TypePhp\Exception\PlaceHolder;
use TypePhp\Exception\Redo;
use TypePhp\Exception\Skip;
use TypePhp\Exception\SyntaxError;
use TypePhp\Exception\TestError;
use TypePhp\Exception\Unsupported;
use TypePhp\Metadata\Constants;

class ExceptionTest extends TestCase
{
    public function testDynamicCall(): void
    {
        $e = new DynamicCall('Dynamic call error');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertEquals('Dynamic call error', $e->getMessage());
    }

    public function testPlaceHolder(): void
    {
        $e = new PlaceHolder('Placeholder error');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertEquals('Placeholder error', $e->getMessage());
    }

    public function testRedo(): void
    {
        $e = new Redo('Redo compilation');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertStringContainsString('Redo', $e->getMessage());
    }

    public function testSkip(): void
    {
        $e = new Skip('Skip this file');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertStringContainsString('Skip', $e->getMessage());
    }

    public function testSyntaxError(): void
    {
        $e = new SyntaxError('Unexpected token');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertStringContainsString('Unexpected', $e->getMessage());
    }

    public function testTestError(): void
    {
        $e = new TestError('Test assertion failed');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertStringContainsString('Test assertion', $e->getMessage());
    }

    public function testUnsupported(): void
    {
        $e = new Unsupported('Unsupported syntax');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertStringContainsString('Unsupported', $e->getMessage());
    }

    public function testCustomErrorCodes(): void
    {
        $e = new TestError('Error 42', 42);
        $this->assertEquals(42, $e->getCode());
    }

    public function testCatchByBaseRuntimeException(): void
    {
        // All exceptions can be caught by their parent RuntimeException
        $errors = [
            new DynamicCall('test'),
            new PlaceHolder('test'),
            new Redo('test'),
            new Skip('test'),
            new SyntaxError('test'),
            new TestError('test'),
            new Unsupported('test'),
        ];

        foreach ($errors as $e) {
            try {
                throw $e;
            } catch (\RuntimeException $caught) {
                $this->assertSame($e, $caught);
            }
        }
    }

    public function testConstantsReservedNames(): void
    {
        $this->assertIsArray(Constants::CPP_RESERVED_NAMES);
        $this->assertContains('class', Constants::CPP_RESERVED_NAMES);
        $this->assertContains('namespace', Constants::CPP_RESERVED_NAMES);
        $this->assertContains('int', Constants::CPP_RESERVED_NAMES);
        $this->assertContains('void', Constants::CPP_RESERVED_NAMES);
    }

    public function testConstantsUnsupportedFunctions(): void
    {
        $this->assertIsArray(Constants::UNSUPPORTED_FUNCTIONS);
        $this->assertContains('extract', Constants::UNSUPPORTED_FUNCTIONS);
    }

    public function testConstantsCompilerOptions(): void
    {
        $options = Constants::COMPILER_OPTIONS;
        $this->assertIsArray($options);
        $this->assertArrayHasKey('optimize', $options);
        $this->assertArrayHasKey('output', $options);
        $this->assertArrayHasKey('help', $options);
        $this->assertArrayHasKey('debug', $options);
        $this->assertArrayHasKey('job', $options);
        $this->assertArrayHasKey('mode', $options);
    }

    public function testCompilerOptionOptimizeDefaults(): void
    {
        $opt = Constants::COMPILER_OPTIONS['optimize'];
        $this->assertEquals('O', $opt['prefix']);
        $this->assertIsInt($opt['defaultValue']);
        $this->assertEquals(0, $opt['defaultValue']);
    }

    public function testCompilerOptionModeDefaults(): void
    {
        $opt = Constants::COMPILER_OPTIONS['mode'];
        $this->assertEquals('bin', $opt['defaultValue']);
    }

    public function testCompilerOptionNoValueFlags(): void
    {
        $noValueFlags = ['help', 'version', 'no-literal-strings', 'force', 'debug', 'no-console'];
        foreach ($noValueFlags as $flag) {
            $this->assertArrayHasKey($flag, Constants::COMPILER_OPTIONS);
            $this->assertTrue(Constants::COMPILER_OPTIONS[$flag]['noValue'] ?? false);
        }
    }

    public function testMsvcSuppressedWarnings(): void
    {
        $warnings = Constants::MSVC_SUPPRESSED_WARNINGS;
        $this->assertIsArray($warnings);
        $this->assertArrayHasKey('4244', $warnings);
        $this->assertArrayHasKey('4127', $warnings);
    }
}

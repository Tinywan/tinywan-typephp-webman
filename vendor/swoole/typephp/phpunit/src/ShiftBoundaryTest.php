<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use PhpParser\Node;
use TypePhp\CompilerTest;
use TypePhp\Diagnostics\DiagnosticReporter;
use TypePhp\Exception\TestError;

/**
 * Constant bit shift boundaries emit compile-time warnings and keep PHP
 * semantics in non-native mode.
 */
class ShiftBoundaryTest extends TestCase
{
    public function testShiftCountAtLeastWordSizeEmitsWarning(): void
    {
        $reporter = $this->compileWithReporter();

        $overflowWarnings = array_values(array_filter(
            $reporter->warnings,
            fn (string $message): bool => str_contains($message, 'Bit shift count 64 is >= 64')
        ));
        $this->assertCount(3, $overflowWarnings);
        foreach ($overflowWarnings as $message) {
            $this->assertStringContainsString('folding with PHP semantics', $message);
        }
    }

    public function testNegativeShiftCountEmitsWarning(): void
    {
        $reporter = $this->compileWithReporter();

        $negativeWarnings = array_values(array_filter(
            $reporter->warnings,
            fn (string $message): bool => str_contains($message, 'Bit shift by a negative number')
        ));
        $this->assertCount(1, $negativeWarnings);
        $this->assertStringContainsString('ArithmeticError', $negativeWarnings[0]);
    }

    public function testInRangeShiftDoesNotWarn(): void
    {
        $reporter = $this->compileWithReporter();

        foreach ($reporter->warnings as $message) {
            $this->assertStringNotContainsString('Bit shift count 2 is >=', $message);
            $this->assertStringNotContainsString('Bit shift count 1 is >=', $message);
        }
    }

    public function testNativeModeRejectsShiftCountAtLeastWordSize(): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Bit shift count 64 is >= 64 and is not supported in native mode');
        $this->compileNativeWithReporter('shift-boundary-native-overflow.php');
    }

    public function testNativeModeRejectsNestedShiftCountAtLeastWordSize(): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Bit shift count 64 is >= 64 and is not supported in native mode');
        $this->compileNativeWithReporter('shift-boundary-native-nested-overflow.php');
    }

    public function testNativeModeRejectsNegativeShiftCount(): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Bit shift by a negative number is not supported in native mode');
        $this->compileNativeWithReporter('shift-boundary-native-negative.php');
    }

    public function testNativeModeRejectsNegativeRightShift(): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Right shift of a negative value is implementation-defined in C++');
        $this->compileNativeWithReporter('shift-boundary-native-neg-right.php');
    }

    public function testNativeModeRejectsNestedNegativeRightShift(): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Right shift of a negative value is implementation-defined in C++');
        $this->compileNativeWithReporter('shift-boundary-native-nested-neg-right.php');
    }

    public function testNativeModeRejectsLeftShiftChangingSignBit(): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Left shift that changes the sign bit is undefined behavior in C++');
        $this->compileNativeWithReporter('shift-boundary-native-sign-bit.php');
    }

    public function testNativeModeRejectsLeftShiftThatWrapsPastSignBit(): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Left shift that changes the sign bit is undefined behavior in C++');
        $this->compileNativeWithReporter('shift-boundary-native-wrapped-overflow.php');
    }

    public function testNativeModeRejectsNegativeLeftShift(): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Left shift of a negative value is undefined behavior in C++');
        $this->compileNativeWithReporter('shift-boundary-native-neg-left.php');
    }

    public function testNativeModeAllowsInRangeShift(): void
    {
        $compiler = $this->compileNativeWithReporter('shift-boundary-native-ok.php');
        $this->assertNotNull($compiler);
    }

    /**
     * @return object{warnings: list<string>}
     */
    private function compileWithReporter(): object
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $reporter = new class implements DiagnosticReporter {
            /** @var list<string> */
            public array $warnings = [];

            public function fatal(string $message): never
            {
                throw new TestError($message);
            }

            public function warning(Node $node, string $file, string $message): void
            {
                $this->warnings[] = $message;
            }
        };
        $compiler->setDiagnosticReporter($reporter);

        $testFile = __DIR__ . '/../code/shift-boundary-warning.php';
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $compiler->convertFile($testFile);

        return $reporter;
    }

    private function compileNativeWithReporter(string $file): CompilerTest
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $compiler->setDiagnosticReporter(new class implements DiagnosticReporter {
            public function fatal(string $message): never
            {
                throw new TestError($message);
            }

            public function warning(Node $node, string $file, string $message): void
            {
            }
        });

        $testFile = __DIR__ . '/../code/' . $file;
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $compiler->convertFile($testFile);

        return $compiler;
    }
}

<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use PhpParser\Node;
use TypePhp\CompilerTest;
use TypePhp\Diagnostics\DiagnosticReporter;
use TypePhp\Exception\TestError;

/**
 * Constant integer arithmetic overflow emits compile-time warnings and folds
 * to the PHP float result in non-native mode.
 */
class ConstantArithmeticOverflowTest extends TestCase
{
    public function testOverflowingConstantArithmeticEmitsWarning(): void
    {
        $reporter = $this->compileWithReporter();

        $overflowWarnings = array_values(array_filter(
            $reporter->warnings,
            fn (string $message): bool => str_contains($message, 'Constant integer arithmetic overflows int64')
        ));
        $this->assertCount(3, $overflowWarnings);
        $this->assertStringContainsString('9223372036854775807 + 1', $overflowWarnings[0]);
        $this->assertStringContainsString('folding to PHP float result', $overflowWarnings[0]);
    }

    public function testNonOverflowingConstantArithmeticDoesNotWarn(): void
    {
        $reporter = $this->compileWithReporter();

        foreach ($reporter->warnings as $message) {
            $this->assertStringNotContainsString('1 + 2', $message);
        }
    }

    public function testNativeModeRejectsConstantUndefinedBehavior(): void
    {
        $cases = [
            'constant-overflow-native-add.php' => '9223372036854775807 + 1',
            'constant-overflow-native-sub.php' => '-9223372036854775808 - 1',
            'constant-overflow-native-mul.php' => '9223372036854775807 * 2',
            'constant-overflow-native-div.php' => '-9223372036854775808 / -1',
            'constant-overflow-native-mod.php' => '-9223372036854775808 % -1',
            'constant-overflow-native-neg.php' => 'Negating PHP_INT_MIN',
            'constant-overflow-native-nested-add.php' => '9223372036854775807 + 1',
            'constant-overflow-native-nested-div.php' => '-9223372036854775808 / -1',
            'constant-overflow-native-nested-mod.php' => '-9223372036854775808 % -1',
            'constant-overflow-native-nested-zero.php' => 'Constant division or modulo by zero',
            'constant-overflow-native-div-subtree.php' => '9223372036854775807 + 2',
        ];

        foreach ($cases as $file => $expectedMessage) {
            try {
                $this->compileNativeFile($file);
                $this->fail("Expected native constant overflow in {$file} to be rejected");
            } catch (TestError $e) {
                $this->assertStringContainsString($expectedMessage, $e->getMessage());
            }
        }

        $this->compileNativeFile('constant-overflow-native-ok.php');
        $this->compileNativeFile('constant-overflow-native-nonconstant.php');
        $this->addToAssertionCount(1);
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

        $testFile = __DIR__ . '/../code/constant-overflow-warning.php';
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $compiler->convertFile($testFile);

        return $reporter;
    }

    private function compileNativeFile(string $file): void
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
    }
}

<?php

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

final class NanoCapabilityPolicyCompiler extends CompilerTest
{
    public function enableNanoForTest(): void
    {
        $this->nanoMode = true;
        $this->forTest = true;
        $this->file = 'nano-policy.php';
    }

    public function enableWasiForTest(): void
    {
        $this->nanoMode = true;
        $this->nanoPolicyMode = true;
        $this->targetPlatform = 'wasm32-wasip2';
        $this->forTest = true;
        $this->file = 'nano-policy.php';
    }

    public function enableNanoPolicyWithoutRuntimeForTest(): void
    {
        $this->nanoMode = false;
        $this->nanoPolicyMode = true;
        $this->forTest = true;
        $this->file = 'nano-policy.php';
    }

    public function validateNanoFunction(string $name): void
    {
        $this->assertNanoFunctionSupported(new FuncCall(new Name($name)), $name);
    }

    public function validateWasiFunction(string $name): void
    {
        $this->assertWasiFunctionSupported(new FuncCall(new Name($name)), $name);
    }

    public function forgetBuildTimeFunction(string $name): void
    {
        unset($this->internalFunctions[$name]);
    }
}

final class NanoCapabilityPolicyTest extends BaseTest
{
    public function testFunctionImportCannotBypassNanoPolicy(): void
    {
        $this->assertImportedFunctionRejected(false);
    }

    public function testFunctionImportCannotBypassWasiPolicy(): void
    {
        $this->assertImportedFunctionRejected(true);
    }

    private function assertImportedFunctionRejected(bool $wasi): void
    {
        global $translator;
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        if ($wasi) {
            $compiler->enableWasiForTest();
        } else {
            $compiler->enableNanoForTest();
        }
        $translator = $compiler;
        $source = __DIR__ . '/../code/function-import-policy.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Function `exec` is not supported');
        $compiler->convertFile($source);
    }

    public function testRejectsForbiddenDirectCallMissingFromBuildTimePhp(): void
    {
        global $translator;
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableNanoForTest();
        $compiler->forgetBuildTimeFunction('pcntl_setns');
        $translator = $compiler;
        $source = __DIR__ . '/../code/nano-unavailable-host-function.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Function `pcntl_setns` is not supported in nano mode');
        $compiler->convertFile($source);
    }

    public function testRejectsHostCapabilityFunctions(): void
    {
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableNanoForTest();

        foreach (['shell_exec', 'stream_socket_client', 'stream_select', 'parse_str'] as $name) {
            try {
                $compiler->validateNanoFunction($name);
                self::fail("{$name} was accepted");
            } catch (TestError $error) {
                self::assertStringContainsString("Function `{$name}` is not supported in nano mode", $error->getMessage());
            }
        }
    }

    public function testNanoPolicyAlwaysUsesSourceRuntimeCapabilitySet(): void
    {
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableNanoPolicyWithoutRuntimeForTest();

        foreach (['exec', 'getenv', 'parse_str', 'stream_socket_client'] as $name) {
            try {
                $compiler->validateNanoFunction($name);
                self::fail("{$name} was accepted");
            } catch (TestError $error) {
                self::assertStringContainsString("Function `{$name}` is not supported in nano mode", $error->getMessage());
            }
        }

    }

    public function testKeepsFileStreamsAndFileHashesAvailable(): void
    {
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableNanoForTest();

        foreach (['fopen', 'file_get_contents', 'file_put_contents', 'is_file', 'realpath', 'hash_file', 'stream_get_contents', 'flock', 'umask', 'chown'] as $name) {
            $compiler->validateNanoFunction($name);
        }
        self::addToAssertionCount(10);
    }

    public function testWasiOnlyRejectsItsMissingFileCapabilities(): void
    {
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableWasiForTest();

        foreach (['flock', 'umask', 'chown', 'stream_socket_client', 'gethostbyname', 'dns_get_record'] as $name) {
            try {
                $compiler->validateWasiFunction($name);
                self::fail("{$name} was accepted");
            } catch (TestError $error) {
                self::assertStringContainsString("Function `{$name}` is not supported by the WASI target", $error->getMessage());
            }
        }

        foreach (['fopen', 'file_get_contents', 'hash_file'] as $name) {
            $compiler->validateWasiFunction($name);
        }
        self::addToAssertionCount(3);
    }

    public function testWasiUnsupportedDirectCallFailsBeforeCodeGeneration(): void
    {
        global $translator;
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableWasiForTest();
        $translator = $compiler;
        $source = __DIR__ . '/../code/wasi-unavailable-host-function.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage(
            'Function `stream_socket_client` is not supported by the WASI target',
        );
        $compiler->convertFile($source);
    }

    public function testNanoRejectsNamespacedRestrictedBuiltinFallback(): void
    {
        global $translator;
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableNanoForTest();
        $translator = $compiler;
        $source = __DIR__ . '/../code/namespaced-restricted-functions.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Function `shell_exec` is not supported in nano mode');
        $compiler->convertFile($source);
    }

    public function testWasiRejectsNamespacedRestrictedBuiltinFallback(): void
    {
        global $translator;
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableWasiForTest();
        $translator = $compiler;
        $source = __DIR__ . '/../code/namespaced-wasi-restricted-function.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Function `stream_socket_client` is not supported by the WASI target');
        $compiler->convertFile($source);
    }

    public function testNanoKeepsTypedFileStreamMethods(): void
    {
        global $translator;
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableNanoForTest();
        $translator = $compiler;
        $source = __DIR__ . '/../code/nano-file-stream-method.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);

        $code = file_get_contents($compiler->convertFile($source));
        self::assertIsString($code);
        self::assertStringContainsString('php::toStream(', $code);
        self::assertStringContainsString('php::call(', $code);
    }

    public function testRejectsUnavailableStringMethodAtCompileTime(): void
    {
        global $translator;
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableNanoForTest();
        $translator = $compiler;
        $source = __DIR__ . '/../code/nano-unavailable-string-method.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Function `parse_str` is not supported in nano mode');
        $compiler->convertFile($source);
    }

    public function testKeepsZendIniFunctionsAvailable(): void
    {
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableNanoForTest();
        $compiler->validateNanoFunction('ini_get');
        $compiler->validateNanoFunction('ini_set');
        self::addToAssertionCount(2);
    }

    public function testKeepsCompilerCtypeIntrinsicAvailable(): void
    {
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableNanoForTest();
        $compiler->validateNanoFunction('ctype_digit');
        $this->addToAssertionCount(1);
    }

    public function testKeepsStandardCppTimeFunctionsAvailable(): void
    {
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableNanoForTest();

        foreach (['sleep', 'usleep', 'time_nanosleep', 'time_sleep_until', 'hrtime', 'microtime'] as $name) {
            $compiler->validateNanoFunction($name);
        }
        $this->addToAssertionCount(6);
    }

    public function testKeepsStandardCppRandomFunctionsAvailable(): void
    {
        $compiler = new NanoCapabilityPolicyCompiler(TYPEPHP_ROOT_PATH);
        $compiler->enableNanoForTest();

        $compiler->validateNanoFunction('random_bytes');
        $compiler->validateNanoFunction('random_int');
        $this->addToAssertionCount(2);
    }
}

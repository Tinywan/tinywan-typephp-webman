<?php

namespace TypePhpTest\Build;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypePhp\Build\WasiProjectConfig;

final class WasiProjectConfigTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/typephp-wasi-project-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/src', 0777, true);
        file_put_contents($this->directory . '/src/main.php', '<?php function main(): void {}');
    }

    protected function tearDown(): void
    {
        @unlink($this->directory . '/src/main.php');
        @unlink($this->directory . '/project.yml');
        @rmdir($this->directory . '/src');
        @rmdir($this->directory);
    }

    public function testProjectPathsAreResolvedRelativeToYaml(): void
    {
        file_put_contents($this->directory . '/project.yml', <<<'YAML'
name: demo
mode: bin
target-platform: wasm32-wasip2
build-dir: cache/build
output: dist/demo.wasm
sources:
  - src
wasm: browser
wasm-browser-dir: web/generated
YAML);

        $config = WasiProjectConfig::load(
            'project.yml',
            null,
            $this->directory,
            '/default-build',
        );

        self::assertSame(realpath($this->directory . '/project.yml'), $config->input);
        self::assertSame($this->directory . '/cache/build', $config->buildDir);
        self::assertSame($this->directory . '/dist/demo.wasm', $config->output);
        self::assertSame($this->directory . '/web/generated', $config->browserDir);
        self::assertSame('browser', $config->profile);
        self::assertSame('command', $config->mode);
        self::assertSame('typephp:demo@1.0.0', $config->package);
        self::assertSame('demo', $config->world);
        self::assertTrue(WasiProjectConfig::isWasmEnabled($this->directory . '/project.yml'));
    }

    public function testSingleFileKeepsBuilderOutputDefaults(): void
    {
        $config = WasiProjectConfig::load(
            'src/main.php',
            'custom-build',
            $this->directory,
            '/default-build',
        );

        self::assertSame($this->directory . '/custom-build', $config->buildDir);
        self::assertNull($config->output);
        self::assertNull($config->browserDir);
        self::assertSame('component', $config->profile);
        self::assertSame('command', $config->mode);
    }

    public function testLibraryModeAndWitIdentityAreAccepted(): void
    {
        file_put_contents($this->directory . '/project.yml', <<<'YAML'
name: calculator
mode: library
wasm: component
wasm-package: acme:calculator@2.1.0
wasm-world: calculator-api
sources:
  - src
YAML);

        $config = WasiProjectConfig::load('project.yml', null, $this->directory, '/default-build');

        self::assertSame('library', $config->mode);
        self::assertSame('acme:calculator@2.1.0', $config->package);
        self::assertSame('calculator-api', $config->world);
    }

    public function testComponentDoesNotRequireTargetPlatform(): void
    {
        file_put_contents($this->directory . '/project.yml', <<<'YAML'
name: demo
wasm: component
wasm-browser-dir: generated
sources:
  - src
YAML);

        $config = WasiProjectConfig::load(
            'project.yml',
            null,
            $this->directory,
            '/default-build',
        );

        self::assertSame('component', $config->profile);
        self::assertNull($config->browserDir);
    }

    public function testCliCanSelectBrowserOutput(): void
    {
        file_put_contents($this->directory . '/project.yml', <<<'YAML'
name: demo
wasm: component
wasm-browser-dir: generated
sources:
  - src
YAML);

        $config = WasiProjectConfig::load(
            'project.yml',
            null,
            $this->directory,
            '/default-build',
            'browser',
        );

        self::assertSame('browser', $config->profile);
        self::assertSame($this->directory . '/generated', $config->browserDir);
    }

    public function testPreviewOneProjectIsRejected(): void
    {
        file_put_contents($this->directory . '/project.yml', <<<'YAML'
target-platform: wasm32-wasi
sources:
  - src
YAML);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must target wasm32-wasip2');
        WasiProjectConfig::load('project.yml', null, $this->directory, '/default-build');
    }

    public function testBooleanWasmProfileIsRejected(): void
    {
        file_put_contents($this->directory . '/project.yml', <<<'YAML'
wasm: true
sources:
  - src
YAML);

        self::assertTrue(WasiProjectConfig::isWasmEnabled($this->directory . '/project.yml'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be `component` or `browser`');
        WasiProjectConfig::load('project.yml', null, $this->directory, '/default-build');
    }

    public function testUnsupportedWasmProfileAliasIsRejected(): void
    {
        file_put_contents($this->directory . '/project.yml', <<<'YAML'
wasm: web
sources:
  - src
YAML);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expected browser or component');
        WasiProjectConfig::load('project.yml', null, $this->directory, '/default-build');
    }
}

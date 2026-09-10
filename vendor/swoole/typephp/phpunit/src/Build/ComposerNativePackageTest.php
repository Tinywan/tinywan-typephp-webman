<?php

namespace TypePhpTest\Build;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypePhp\Build\ComposerNativePackage;

final class ComposerNativePackageTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $installedVersions;
    private string $directory;

    protected function setUp(): void
    {
        $this->installedVersions = InstalledVersions::getRawData();
        $this->directory = sys_get_temp_dir() . '/typephp-native-package-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/src', 0777, true);
        file_put_contents($this->directory . '/src/example.c', 'int typephp_example(void) { return 1; }');
    }

    protected function tearDown(): void
    {
        InstalledVersions::reload($this->installedVersions);
        @unlink($this->directory . '/src/example.c');
        @unlink($this->directory . '/composer.json');
        @rmdir($this->directory . '/src');
        @rmdir($this->directory);
    }

    public function testLoadsStaticExtensionMetadata(): void
    {
        $this->installFixture(true);

        $package = ComposerNativePackage::load('swoole/php-ext-example');

        self::assertSame('extension', $package->kind);
        self::assertSame('example', $package->extensionName);
        self::assertSame('example_module_entry', $package->extensionModuleEntry);
        self::assertSame([realpath($this->directory . '/src/example.c')], $package->sources);
    }

    public function testStaticExtensionMustRequireNanoRuntime(): void
    {
        $this->installFixture(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must require `swoole/php-nano`');
        ComposerNativePackage::load('swoole/php-ext-example');
    }

    public function testObsoleteExternalStandardPackageIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('standard extension is built into');
        ComposerNativePackage::load('swoole/php-ext-standard');
    }

    private function installFixture(bool $requireRuntime): void
    {
        $manifest = [
            'name' => 'swoole/php-ext-example',
            'require' => $requireRuntime ? ['swoole/php-nano' => '^8.6@dev'] : [],
            'extra' => [
                'typephp-native' => [
                    'kind' => 'extension',
                    'abi' => 80600,
                    'c-standard' => 11,
                    'cxx-standard' => 17,
                    'include-dirs' => ['src'],
                    'sources' => ['src/example.c'],
                    'extension' => [
                        'name' => 'example',
                        'module-entry' => 'example_module_entry',
                    ],
                ],
            ],
        ];
        file_put_contents(
            $this->directory . '/composer.json',
            json_encode($manifest, JSON_THROW_ON_ERROR),
        );
        InstalledVersions::reload([
            'root' => [
                'name' => 'typephp/test',
                'pretty_version' => 'dev-main',
                'version' => 'dev-main',
                'reference' => null,
                'type' => 'project',
                'install_path' => $this->directory,
                'aliases' => [],
                'dev' => true,
            ],
            'versions' => [
                'swoole/php-ext-example' => [
                    'pretty_version' => '0.1.0',
                    'version' => '0.1.0.0',
                    'reference' => null,
                    'type' => 'library',
                    'install_path' => $this->directory,
                    'aliases' => [],
                    'dev_requirement' => false,
                ],
            ],
        ]);
    }
}

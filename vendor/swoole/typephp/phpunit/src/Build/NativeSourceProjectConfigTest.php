<?php

namespace TypePhpTest\Build;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypePhp\Build\NativeSourceProjectConfig;

final class NativeSourceProjectConfigTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/typephp-native-project-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/generated', 0777, true);
        file_put_contents($this->directory . '/generated/runtime.c', 'int native_c(void) { return 1; }');
        file_put_contents($this->directory . '/generated/main.cpp', 'int main() { return 0; }');
        file_put_contents($this->directory . '/generated/main.php', '<?php function main(): void {}');
    }

    protected function tearDown(): void
    {
        @unlink($this->directory . '/generated/runtime.c');
        @unlink($this->directory . '/generated/main.cpp');
        @unlink($this->directory . '/generated/main.php');
        @unlink($this->directory . '/generated/invalid.txt');
        @unlink($this->directory . '/project.xml');
        @rmdir($this->directory . '/generated');
        @rmdir($this->directory);
    }

    public function testPhpCAndCppSourcesAreAccepted(): void
    {
        file_put_contents($this->directory . '/project.xml', <<<'XML'
<?xml version="1.0"?>
<project mode="native" name="mixed-source">
  <sources>
    <source>generated/main.php</source>
    <source>generated/runtime.c</source>
    <source>generated/main.cpp</source>
  </sources>
</project>
XML);

        $config = NativeSourceProjectConfig::load($this->directory . '/project.xml');

        self::assertSame('cc', $config->cCompiler);
        self::assertSame('c++', $config->compiler);
        self::assertSame([
            realpath($this->directory . '/generated/main.php'),
        ], $config->phpSources);
        self::assertSame([
            realpath($this->directory . '/generated/runtime.c'),
            realpath($this->directory . '/generated/main.cpp'),
        ], $config->sources);
    }

    public function testNonNativeSourceIsRejected(): void
    {
        file_put_contents($this->directory . '/generated/invalid.txt', 'invalid');
        file_put_contents($this->directory . '/project.xml', <<<'XML'
<project mode="native">
  <sources><source>generated/invalid.txt</source></sources>
</project>
XML);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be PHP, C, or C++');
        NativeSourceProjectConfig::load($this->directory . '/project.xml');
    }
}

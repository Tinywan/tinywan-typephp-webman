<?php

use PHPUnit\Framework\Attributes\DataProvider;
use TypePhp\CompilerTest;
use TypePhp\Exception\SyntaxError;
use TypePhp\Exception\TestError;

final class StdContainerValueInitializerTest extends BaseTest
{
    private function translate(string $source): string
    {
        global $translator;
        $directory = sys_get_temp_dir() . '/std-value-initializer-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $file = $directory . '/initializer.php';
        file_put_contents($file, '<?php function main(): void { ' . $source . ' }');
        try {
            $translator = CompilerTest::create(TYPEPHP_ROOT_PATH);
            $translator->addFiles([$file]);
            $translator->prepareFile($file);
            return file_get_contents($translator->convertFile($file));
        } finally {
            unlink($file);
            rmdir($directory);
        }
    }

    public function testEveryContainerAcceptsAnInferredArrayInitializer(): void
    {
        $code = $this->translate(<<<'PHP'
$n = 2;
$a = std::array([[1, $n], [3, 4]]);
$v = std::vector([1, $n]);
$m = std::map(['a' => 1, 'b' => $n]);
$o = std::orderedMap([1 => 'a', 2 => 'b']);
$l = std::list([1, $n]);
$d = std::dict(['a' => 1, 'b' => $n]);
PHP);
        self::assertStringContainsString('php::StdArray<php::StdArray<php::Int, 2>, 2>', $code);
        self::assertStringContainsString('a_ref.offsetGet(0).offsetSet(1, php::toInt(n))', $code);
        self::assertStringContainsString('v_ref.push_back(php::toInt(n))', $code);
        self::assertStringContainsString('m_ref.offsetSet(', $code);
        self::assertStringContainsString('o_ref.offsetSet(', $code);
        self::assertStringContainsString('l = php::Array{', $code);
        self::assertStringContainsString('d = php::Array{', $code);
    }

    #[DataProvider('invalidInitializers')]
    public function testInvalidInitializers(string $source, string $message): void
    {
        try {
            $this->translate($source);
            self::fail('Invalid std value initializer was accepted');
        } catch (TestError|SyntaxError $error) {
            self::assertStringContainsString($message, $error->getMessage());
        }
    }

    public static function invalidInitializers(): iterable
    {
        yield 'empty' => ['$v = std::vector([]);', 'cannot infer types from an empty array'];
        yield 'mixed values' => ['$v = std::vector([1, "2"]);', 'values must all have exactly the same type'];
        yield 'mixed keys' => ['$m = std::map([1 => 1, "2" => 2]);', 'keys must all have exactly the same type'];
        yield 'float key' => ['$m = std::map([1.5 => 1]);', 'statically known int or string type'];
        yield 'bool key' => ['$m = std::map([true => 1]);', 'statically known int or string type'];
        yield 'null key' => ['$m = std::map([null => 1]);', 'statically known int or string type'];
        yield 'map missing key' => ['$m = std::map([1, 2]);', 'requires an explicit key'];
        yield 'ragged array' => ['$a = std::array([[1], [2, 3]]);', 'uniform rectangular shape'];
        yield 'list string key' => ['$l = std::list(["a" => 1]);', 'keys must have type int'];
        yield 'dict missing key' => ['$d = std::dict([1, 2]);', 'requires an explicit key'];
        yield 'dynamic key' => ['$key = std::any("a"); $d = std::dict([$key => 1]);', 'statically known int or string type'];
        yield 'dynamic value' => ['$x = std::any(1); $l = std::list([$x]);', 'cannot infer an element type from var/any'];
    }
}

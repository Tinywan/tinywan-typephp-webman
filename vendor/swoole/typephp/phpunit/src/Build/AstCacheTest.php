<?php

namespace TypePhp\Tests\Build;

use PhpParser\ErrorHandler;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TypePhp\Build\AstCache;
use TypePhp\CompilerTest;

final class CountingParser implements Parser
{
    public int $parseCount = 0;

    public function __construct(private readonly Parser $delegate)
    {
    }

    public function parse(string $code, ?ErrorHandler $errorHandler = null): ?array
    {
        ++$this->parseCount;
        return $this->delegate->parse($code, $errorHandler);
    }

    public function getTokens(): array
    {
        return $this->delegate->getTokens();
    }
}

final class AstCacheTest extends TestCase
{
    private string $directory;
    private string $sourceFile;
    private string $buildDirectory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/typephp_ast_cache_' . bin2hex(random_bytes(8));
        $this->buildDirectory = $this->directory . '/custom-build';
        mkdir($this->buildDirectory, 0777, true);
        $this->sourceFile = $this->directory . '/program.php';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testCacheHitSkipsParserAndReturnsAnIndependentAst(): void
    {
        $source = "<?php function main(): void { echo 'ok'; }\n";
        file_put_contents($this->sourceFile, $source);
        $parser = $this->createCountingParser();
        $cache = new AstCache($parser, $this->buildDirectory, '8.4.0', 'test-parser');

        $prepareAst = $cache->load($this->sourceFile, $source);
        $prepareAst[0]->setAttribute('typephpPhaseMutation', true);
        $convertAst = $cache->load($this->sourceFile, $source);

        self::assertSame(1, $parser->parseCount);
        self::assertFalse($convertAst[0]->getAttribute('typephpPhaseMutation', false));
        self::assertDirectoryExists($this->buildDirectory . '/cache/ast');
        self::assertCount(1, glob($this->buildDirectory . '/cache/ast/*.ast'));

    }

    public function testPersistentCacheIsReusedByAnotherCompilerProcess(): void
    {
        $source = "<?php function cached(): int { return 42; }\n";
        file_put_contents($this->sourceFile, $source);
        $firstParser = $this->createCountingParser();
        (new AstCache($firstParser, $this->buildDirectory, '8.4.0', 'test-parser'))
            ->load($this->sourceFile, $source);

        $secondParser = $this->createCountingParser();
        $ast = (new AstCache($secondParser, $this->buildDirectory, '8.4.0', 'test-parser'))
            ->load($this->sourceFile, $source);

        self::assertSame(1, $firstParser->parseCount);
        self::assertSame(0, $secondParser->parseCount);
        self::assertNotEmpty($ast);
    }

    public function testNewerOrChangedSourceInvalidatesCache(): void
    {
        $source = "<?php function value(): int { return 1; }\n";
        file_put_contents($this->sourceFile, $source);
        $parser = $this->createCountingParser();
        $cache = new AstCache($parser, $this->buildDirectory, '8.4.0', 'test-parser');
        $cache->load($this->sourceFile, $source);
        $cacheFile = glob($this->buildDirectory . '/cache/ast/*.ast')[0];

        $changed = "<?php function value(): int { return 2; }\n";
        file_put_contents($this->sourceFile, $changed);
        touch($this->sourceFile, filemtime($cacheFile) + 2);
        $cache->load($this->sourceFile, $changed);

        self::assertSame(2, $parser->parseCount);
    }

    public function testContentHashAndMetadataRejectStaleOrCorruptCache(): void
    {
        $source = "<?php function first(): int { return 1; }\n";
        file_put_contents($this->sourceFile, $source);
        $parser = $this->createCountingParser();
        $cache = new AstCache($parser, $this->buildDirectory, '8.4.0', 'test-parser');
        $cache->load($this->sourceFile, $source);
        $cacheFile = glob($this->buildDirectory . '/cache/ast/*.ast')[0];
        $cacheMtime = filemtime($cacheFile);

        $sameSizeChange = "<?php function other(): int { return 2; }\n";
        self::assertSame(strlen($source), strlen($sameSizeChange));
        file_put_contents($this->sourceFile, $sameSizeChange);
        touch($this->sourceFile, $cacheMtime);
        $cache->load($this->sourceFile, $sameSizeChange);
        self::assertSame(2, $parser->parseCount);

        file_put_contents($cacheFile, 'broken-cache');
        touch($cacheFile, time() + 2);
        $cache->load($this->sourceFile, $sameSizeChange);
        self::assertSame(3, $parser->parseCount);
    }

    public function testParserVersionChangeInvalidatesCache(): void
    {
        $source = "<?php function cached(): int { return 42; }\n";
        file_put_contents($this->sourceFile, $source);
        $firstParser = $this->createCountingParser();
        (new AstCache($firstParser, $this->buildDirectory, '8.4.0', 'parser-v1'))
            ->load($this->sourceFile, $source);

        $secondParser = $this->createCountingParser();
        (new AstCache($secondParser, $this->buildDirectory, '8.4.0', 'parser-v2'))
            ->load($this->sourceFile, $source);

        self::assertSame(1, $firstParser->parseCount);
        self::assertSame(1, $secondParser->parseCount);
    }

    public function testPrepareAndConvertShareOneParseAndRespectBuildDirectory(): void
    {
        global $translator;

        $source = "<?php function main(): void { echo 'cached'; }\n";
        file_put_contents($this->sourceFile, $source);
        $compiler = CompilerTest::create($this->directory);
        $translator = $compiler;
        $reflection = new \ReflectionClass($compiler);
        $setBuildDir = $reflection->getMethod('setBuildDir');
        $setBuildDir->setAccessible(true);
        $setBuildDir->invoke($compiler, $this->buildDirectory);

        $parser = $this->createCountingParser();
        $parserProperty = $reflection->getProperty('parser');
        $parserProperty->setAccessible(true);
        $parserProperty->setValue($compiler, $parser);
        $astCacheProperty = $reflection->getProperty('astCache');
        $astCacheProperty->setAccessible(true);
        $astCacheProperty->setValue($compiler, null);

        $compiler->addFiles([$this->sourceFile]);
        $compiler->prepareFile($this->sourceFile);
        $generated = $compiler->convertFile($this->sourceFile);

        self::assertSame(1, $parser->parseCount);
        self::assertNotNull($generated);
        self::assertFileExists($generated);
        self::assertCount(1, glob($this->buildDirectory . '/cache/ast/*.ast'));
        // Stub visitors replace names; they require an independent pristine AST.
        $stub = \TypePhp\StubGenerator\FileInfo::parseStubFile(
            $source, $compiler->getPhpVersion(), $this->sourceFile,
        );
        self::assertNotNull($stub);
        self::assertSame(1, $parser->parseCount);
        self::assertCount(1, glob($this->buildDirectory . '/cache/ast/*.ast'));
    }

    private function createCountingParser(): CountingParser
    {
        return new CountingParser((new ParserFactory())->createForVersion(
            \PhpParser\PhpVersion::fromString('8.4'),
        ));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }
}

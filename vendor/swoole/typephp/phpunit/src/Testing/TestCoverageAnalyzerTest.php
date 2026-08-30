<?php
/**
 * This file is part of Swoole-Compiler(AOT).
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

declare(strict_types=1);

namespace TypePhp\Tests\Testing;

use PHPUnit\Framework\TestCase;
use TypePhp\Testing\TestCoverageAnalyzer;

/**
 * @internal
 * @coversNothing
 */
final class TestCoverageAnalyzerTest extends TestCase
{
    private string $testRoot;

    protected function setUp(): void
    {
        $this->testRoot = sys_get_temp_dir() . '/typephp-coverage-' . bin2hex(random_bytes(8));
        mkdir($this->testRoot . '/phpt', 0777, true);
        mkdir($this->testRoot . '/phpunit-src', 0777, true);
        mkdir($this->testRoot . '/phpunit-code', 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->testRoot)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->testRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->testRoot);
    }

    public function testBuildsVersionedEvidenceMatrixFromPhptAndPhpUnitSources(): void
    {
        file_put_contents($this->testRoot . '/phpt/void-cast.phpt', <<<'PHPT'
--TEST--
PHP 8.5 void cast runtime semantics
--FILE--
<?php
function main(): void
{
    (void) strlen('value');
}
--EXPECT--

PHPT);

        file_put_contents($this->testRoot . '/phpt/negative-named-exit.phpt', <<<'PHPT'
--TEST--
PHP 8.4 invalid named exit argument fails cleanly
--FILE--
<?php
function main(): void
{
    exit(message: 'failure');
}
--EXPECTF--
Fatal error: unsupported test diagnostic in %s on line %d
PHPT);

        file_put_contents($this->testRoot . '/phpunit-src/CoverageFixtureTest.php', <<<'PHP'
<?php
final class CoverageFixtureTest extends \PHPUnit\Framework\TestCase
{
    /** @dataProvider invalidProvider */
    public function testRejectsUnsupportedFeature(string $source, string $phpVersion): void
    {
        $this->expectException(\RuntimeException::class);
    }

    public static function invalidProvider(): iterable
    {
        yield [
            '<?php class Example { public string $value { &get => $this->value; } }',
            '8.4',
        ];
    }
}
PHP);

        $analyzer = new TestCoverageAnalyzer(TYPEPHP_ROOT_PATH, ['8.4', '8.5']);
        $report = $analyzer->analyze(
            [$this->testRoot . '/phpt'],
            $this->testRoot . '/phpunit-src',
            $this->testRoot . '/phpunit-code',
        );

        self::assertSame(2, $report['summary']['phpt_files']);
        self::assertSame(2, $report['summary']['parsed_phpt_files']);
        self::assertSame([], $report['parse_errors']);
        self::assertSame([], $report['unresolved_phpunit_fixtures']);
        self::assertArrayNotHasKey('overall_percentage', $report['summary']);

        $void85 = $this->matrixRow($report, '8.5', 'semantic:void_cast');
        self::assertTrue($void85['positive_compile']);
        self::assertTrue($void85['runtime_semantics']);
        self::assertFalse($void85['negative_diagnostic']);
        self::assertNull($this->findMatrixRow($report, '8.4', 'semantic:void_cast'));

        $exit84 = $this->matrixRow($report, '8.4', 'semantic:exit_named_argument');
        self::assertFalse($exit84['positive_compile']);
        self::assertFalse($exit84['runtime_semantics']);
        self::assertTrue($exit84['negative_diagnostic']);

        $hook84 = $this->matrixRow($report, '8.4', 'semantic:property_hook_by_reference');
        self::assertFalse($hook84['positive_compile']);
        self::assertTrue($hook84['negative_diagnostic']);

        $markdown = $analyzer->renderMarkdown($report);
        self::assertStringContainsString('PHP version × feature × evidence matrix', $markdown);
        self::assertStringContainsString('No combined overall percentage is calculated.', $markdown);
    }

    /** @param array<string, mixed> $report @return array<string, mixed> */
    private function matrixRow(array $report, string $version, string $featureId): array
    {
        $row = $this->findMatrixRow($report, $version, $featureId);
        self::assertNotNull($row, $version . ' ' . $featureId);
        return $row;
    }

    /** @param array<string, mixed> $report @return array<string, mixed>|null */
    private function findMatrixRow(array $report, string $version, string $featureId): ?array
    {
        foreach ($report['matrix'] as $row) {
            if ($row['php_version'] === $version && $row['feature_id'] === $featureId) {
                return $row;
            }
        }
        return null;
    }
}

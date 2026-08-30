<?php
/**
 * This file is part of Swoole-Compiler(AOT).
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

declare(strict_types=1);

namespace TypePhp\Testing;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Static coverage inventory for PHPT sources and PHPUnit compiler fixtures.
 *
 * This intentionally reports separate ratios with explicit denominators. It
 * does not attempt to turn compile, runtime and diagnostic evidence into one
 * ambiguous project-wide percentage.
 */
final class TestCoverageAnalyzer
{
    private const AXES = ['positive_compile', 'runtime_semantics', 'negative_diagnostic'];
    private Parser $parser;

    /** @var array<string, array<string, mixed>> */
    private array $catalog;

    /** @var array<string, array<string, array<string, array<string, mixed>>>> */
    private array $evidence = [];

    /** @var list<array<string, mixed>> */
    private array $tests = [];

    /** @var list<array{file: string, message: string}> */
    private array $parseErrors = [];

    /** @var list<array{file: string, message: string}> */
    private array $expectedParserDiagnostics = [];

    /** @var list<array{test: string, fixture: string}> */
    private array $unresolvedPhpUnitFixtures = [];
    private int $discoveredPhptFiles = 0;

    /** @param list<string> $targetPhpVersions */
    public function __construct(
        private readonly string $projectRoot,
        private readonly array $targetPhpVersions = ['8.4', '8.5'],
    ) {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->catalog = $this->buildFeatureCatalog();
        $this->resetEvidence();
    }

    /**
     * @param list<string> $phptPaths
     * @return array<string, mixed>
     */
    public function analyze(
        array $phptPaths,
        ?string $phpUnitSourceDirectory = null,
        ?string $phpUnitFixtureDirectory = null,
    ): array {
        $this->resetEvidence();
        $this->tests = [];
        $this->parseErrors = [];
        $this->expectedParserDiagnostics = [];
        $this->unresolvedPhpUnitFixtures = [];

        $phptFiles = $this->discoverFiles($phptPaths, '.phpt');
        $this->discoveredPhptFiles = count($phptFiles);
        foreach ($phptFiles as $file) {
            $this->analyzePhpt($file);
        }

        if ($phpUnitSourceDirectory !== null && $phpUnitFixtureDirectory !== null) {
            $this->analyzePhpUnit($phpUnitSourceDirectory, $phpUnitFixtureDirectory);
        }

        return $this->buildReport($phptPaths, $phpUnitSourceDirectory, $phpUnitFixtureDirectory);
    }

    /** @param array<string, mixed> $report */
    public function renderSummary(array $report): string
    {
        $lines = [];
        $lines[] = 'TypePHP test coverage inventory';
        $lines[] = str_repeat('=', 32);
        $lines[] = sprintf(
            'Sources: %d PHPT, %d resolved PHPUnit fixture links, %d parsed source records',
            $report['summary']['phpt_files'],
            $report['summary']['phpunit_fixture_links'],
            $report['summary']['parsed_source_records'],
        );
        if ($report['summary']['parsed_phpt_files'] !== $report['summary']['phpt_files']) {
            $lines[] = sprintf(
                'Parsed PHPT sources: %d/%d',
                $report['summary']['parsed_phpt_files'],
                $report['summary']['phpt_files'],
            );
        }
        $lines[] = sprintf(
            'Parse issues: %d; unresolved PHPUnit fixture references: %d',
            count($report['parse_errors']),
            count($report['unresolved_phpunit_fixtures']),
        );
        $lines[] = 'Expected parser diagnostics in negative providers: '
            . count($report['expected_parser_diagnostics']);
        $lines[] = '';
        $ast = $report['summary']['ast_node_coverage'];
        $lines[] = sprintf(
            'AST node-kind coverage: %d/%d (%.1f%%)',
            $ast['covered'],
            $ast['denominator'],
            $ast['percentage'],
        );
        $lines[] = '  denominator = concrete AST node kinds shipped by the installed php-parser';
        $lines[] = '';
        $lines[] = 'Feature-axis coverage (each denominator is the applicable feature rows for that PHP version):';
        foreach ($report['summary']['coverage_by_php_version'] as $version => $coverage) {
            $lines[] = '  PHP ' . $version . ' (' . $coverage['applicable_features'] . ' feature rows)';
            foreach (self::AXES as $axis) {
                $metric = $coverage[$axis];
                $lines[] = sprintf(
                    '    %-20s %d/%d (%.1f%%)',
                    $axis . ':',
                    $metric['covered'],
                    $metric['denominator'],
                    $metric['percentage'],
                );
            }
        }
        $lines[] = '';
        $lines[] = 'No combined overall percentage is calculated.';
        $lines[] = 'Use --format=markdown or --format=json for the complete PHP version × feature matrix.';
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /** @param array<string, mixed> $report */
    public function renderMarkdown(array $report): string
    {
        $lines = [
            '# TypePHP test coverage inventory',
            '',
            '> This report is generated from test sources. It records static test intent/evidence; it is not a test execution result.',
            '',
            '## Scope and denominators',
            '',
            '| Item | Value |',
            '|------|------:|',
            '| PHPT files | ' . $report['summary']['phpt_files'] . ' |',
            '| Parsed PHPT files | ' . $report['summary']['parsed_phpt_files'] . ' |',
            '| Resolved PHPUnit fixture links | ' . $report['summary']['phpunit_fixture_links'] . ' |',
            '| Parsed source records | ' . $report['summary']['parsed_source_records'] . ' |',
            '| AST node-kind denominator | ' . $report['summary']['ast_node_coverage']['denominator'] . ' |',
            '| AST node kinds covered | ' . $report['summary']['ast_node_coverage']['covered'] . ' |',
            '| Parse issues | ' . count($report['parse_errors']) . ' |',
            '| Expected parser diagnostics | ' . count($report['expected_parser_diagnostics']) . ' |',
            '',
            'The AST denominator is the set of concrete AST node kinds shipped by the installed `nikic/php-parser`; `Expr_Error` is excluded because it represents parser recovery rather than a testable syntax feature. The feature-axis denominator is the number of catalog rows applicable to each target PHP version.',
            '',
            'No combined overall percentage is calculated.',
            '',
            '## Coverage by PHP version and evidence axis',
            '',
            '| PHP | Applicable feature rows | Positive compile | Runtime semantics | Negative diagnostic |',
            '|-----|------------------------:|-----------------:|------------------:|--------------------:|',
        ];

        foreach ($report['summary']['coverage_by_php_version'] as $version => $coverage) {
            $lines[] = sprintf(
                '| %s | %d | %s | %s | %s |',
                $version,
                $coverage['applicable_features'],
                $this->formatRatio($coverage['positive_compile']),
                $this->formatRatio($coverage['runtime_semantics']),
                $this->formatRatio($coverage['negative_diagnostic']),
            );
        }

        $lines[] = '';
        $lines[] = '## PHP version × feature × evidence matrix';
        $lines[] = '';
        $lines[] = '| PHP | Feature | Category | Positive compile | Runtime semantics | Negative diagnostic |';
        $lines[] = '|-----|---------|----------|:----------------:|:-----------------:|:-------------------:|';
        foreach ($report['matrix'] as $row) {
            $lines[] = sprintf(
                '| %s | `%s` | %s | %s | %s | %s |',
                $row['php_version'],
                str_replace('|', '\|', $row['feature']),
                $row['category'],
                $row['positive_compile'] ? 'yes' : '—',
                $row['runtime_semantics'] ? 'yes' : '—',
                $row['negative_diagnostic'] ? 'yes' : '—',
            );
        }

        $lines[] = '';
        $lines[] = '## AST node occurrences';
        $lines[] = '';
        $lines[] = '| AST node | Active test sources |';
        $lines[] = '|----------|--------------------:|';
        foreach ($report['ast_nodes'] as $node) {
            $lines[] = '| `' . $node['node_type'] . '` | ' . $node['active_test_sources'] . ' |';
        }

        if ($report['parse_errors'] !== []) {
            $lines[] = '';
            $lines[] = '## Parse issues';
            $lines[] = '';
            foreach ($report['parse_errors'] as $error) {
                $lines[] = '- `' . $error['file'] . '`: ' . str_replace("\n", ' ', $error['message']);
            }
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /** @return array<string, array<string, mixed>> */
    private function buildFeatureCatalog(): array
    {
        $catalog = [];
        $nodeDirectory = $this->projectRoot . '/vendor/nikic/php-parser/lib/PhpParser/Node';
        if (!is_dir($nodeDirectory)) {
            throw new \RuntimeException('php-parser node directory not found: ' . $nodeDirectory);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($nodeDirectory, \FilesystemIterator::SKIP_DOTS),
        );
        $seenClasses = [];
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($nodeDirectory) + 1, -4);
            $class = 'PhpParser\Node\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
            if (!class_exists($class)) {
                continue;
            }
            $reflection = new \ReflectionClass($class);
            $canonicalClass = $reflection->getName();
            if (isset($seenClasses[$canonicalClass]) || $reflection->isAbstract() || !$reflection->isSubclassOf(Node::class)) {
                continue;
            }
            $seenClasses[$canonicalClass] = true;
            try {
                /** @var Node $node */
                $node = $reflection->newInstanceWithoutConstructor();
                $nodeType = $node->getType();
            } catch (\Throwable) {
                continue;
            }
            if ($nodeType === 'Expr_Error') {
                continue;
            }
            $catalog['ast:' . $nodeType] = [
                'id' => 'ast:' . $nodeType,
                'label' => $nodeType,
                'category' => 'ast',
                'introduced' => $this->astNodeIntroducedVersion($nodeType),
                'node_type' => $nodeType,
            ];
        }

        foreach ($this->semanticFeatureDefinitions() as $id => [$label, $version]) {
            $catalog['semantic:' . $id] = [
                'id' => 'semantic:' . $id,
                'label' => $label,
                'category' => 'semantic',
                'introduced' => $version,
                'node_type' => null,
            ];
        }

        uasort($catalog, static function (array $left, array $right): int {
            return [$left['introduced'], $left['category'], $left['label']]
                <=> [$right['introduced'], $right['category'], $right['label']];
        });
        return $catalog;
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function semanticFeatureDefinitions(): array
    {
        return [
            'alternative_control_syntax' => ['alternative_control_syntax', '7.4'],
            'numeric_literal_binary' => ['numeric_literal_binary', '7.4'],
            'numeric_literal_explicit_octal' => ['numeric_literal_explicit_octal', '8.1'],
            'numeric_literal_separator' => ['numeric_literal_separator', '7.4'],
            'relative_namespace_name' => ['relative_namespace_name', '7.4'],
            'named_arguments' => ['named_arguments', '8.0'],
            'constructor_property_promotion' => ['constructor_property_promotion', '8.0'],
            'class_constant_attributes' => ['class_constant_attributes', '8.0'],
            'readonly_property' => ['readonly_property', '8.1'],
            'first_class_callable' => ['first_class_callable', '8.1'],
            'dnf_types' => ['dnf_types', '8.2'],
            'dnf_parameter' => ['dnf_parameter', '8.2'],
            'dnf_return' => ['dnf_return', '8.2'],
            'dnf_property' => ['dnf_property', '8.2'],
            'dnf_closure_signature' => ['dnf_closure_signature', '8.2'],
            'readonly_class' => ['readonly_class', '8.2'],
            'property_hooks' => ['property_hooks', '8.4'],
            'property_hook_get' => ['property_hook_get', '8.4'],
            'property_hook_set' => ['property_hook_set', '8.4'],
            'property_hook_by_reference' => ['property_hook_by_reference', '8.4'],
            'final_property_hook' => ['final_property_hook', '8.4'],
            'asymmetric_property_visibility' => ['asymmetric_property_visibility', '8.4'],
            'promoted_asymmetric_property' => ['promoted_asymmetric_property', '8.4'],
            'final_property' => ['final_property', '8.4'],
            'exit_named_argument' => ['exit_named_argument', '8.4'],
            'property_magic_constant' => ['property_magic_constant', '8.4'],
            'void_cast' => ['void_cast', '8.5'],
            'pipe_operator' => ['pipe_operator', '8.5'],
            'clone_with' => ['clone_with', '8.5'],
            'global_constant_attributes' => ['global_constant_attributes', '8.5'],
            'constant_closure' => ['constant_closure', '8.5'],
            'closure_default_value' => ['closure_default_value', '8.5'],
        ];
    }

    private function astNodeIntroducedVersion(string $nodeType): string
    {
        return match ($nodeType) {
            'Attribute', 'AttributeGroup', 'Expr_Match', 'MatchArm',
            'Expr_NullsafeMethodCall', 'Expr_NullsafePropertyFetch', 'UnionType' => '8.0',
            'Stmt_Enum', 'Stmt_EnumCase', 'IntersectionType', 'VariadicPlaceholder' => '8.1',
            'PropertyHook', 'Scalar_MagicConst_Property' => '8.4',
            'Expr_Cast_Void', 'Expr_BinaryOp_Pipe' => '8.5',
            default => '7.4',
        };
    }

    private function resetEvidence(): void
    {
        $this->evidence = [];
        foreach ($this->catalog as $id => $_) {
            foreach (self::AXES as $axis) {
                $this->evidence[$id][$axis] = [];
            }
        }
    }

    private function analyzePhpt(string $file): void
    {
        $contents = file_get_contents($file);
        if (!is_string($contents)) {
            $this->parseErrors[] = ['file' => $this->relativePath($file), 'message' => 'Unable to read PHPT'];
            return;
        }
        $sections = $this->parsePhptSections($contents);
        $code = $sections['FILE'] ?? null;
        if (!is_string($code)) {
            $this->parseErrors[] = ['file' => $this->relativePath($file), 'message' => 'Missing --FILE-- section'];
            return;
        }

        $testId = $this->relativePath($file);
        $title = trim($sections['TEST'] ?? basename($file));
        [$minPhp, $maxPhp] = $this->inferPhpVersionRange($title, $sections['SKIPIF'] ?? '');
        $excludedReason = null;
        if (isset($sections['XFAIL'])) {
            $excludedReason = 'xfail';
        } elseif ($this->isUnconditionallySkipped($sections['SKIPIF'] ?? '')) {
            $excludedReason = 'unconditional_skip';
        }

        $features = $this->parseSourceFeatures($code, $testId);
        $expected = $sections['EXPECT'] ?? $sections['EXPECTF'] ?? $sections['EXPECTREGEX'] ?? null;
        $negative = is_string($expected) && $this->looksLikeDiagnosticTest($title, $expected, $code);
        $record = [
            'id' => $testId,
            'kind' => 'phpt',
            'title' => $title,
            'min_php' => $minPhp,
            'max_php' => $maxPhp,
            'excluded_reason' => $excludedReason,
            'features' => array_keys($features),
        ];
        $this->tests[] = $record;

        if ($excludedReason !== null || $features === []) {
            return;
        }
        if ($negative) {
            $this->addEvidence($features, 'negative_diagnostic', $record);
            return;
        }
        $this->addEvidence($features, 'positive_compile', $record);
        if ($expected !== null) {
            $this->addEvidence($features, 'runtime_semantics', $record);
        }
    }

    /** @return array<string, string> */
    private function parsePhptSections(string $contents): array
    {
        $parts = preg_split('/^--([A-Z_]+)--\R/m', $contents, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return [];
        }
        $sections = [];
        for ($i = 1, $count = count($parts); $i + 1 < $count; $i += 2) {
            $sections[$parts[$i]] = $parts[$i + 1];
        }
        return $sections;
    }

    /** @return array{0: string|null, 1: string|null} */
    private function inferPhpVersionRange(string $title, string $skipIf): array
    {
        $min = null;
        $max = null;
        if (preg_match('/\bPHP\s+(8\.[0-9]+)\b/i', $title, $match)) {
            $min = $match[1];
        }
        if (preg_match_all('/PHP_VERSION_ID\s*<\s*(\d+)/i', $skipIf, $matches)) {
            foreach ($matches[1] as $versionId) {
                $min = $this->maxVersion($min, $this->versionIdToMinor((int) $versionId));
            }
        }
        if (preg_match_all('/PHP_VERSION_ID\s*>=\s*(\d+)/i', $skipIf, $matches)) {
            foreach ($matches[1] as $versionId) {
                $max = $this->minVersion($max, $this->previousMinor($this->versionIdToMinor((int) $versionId)));
            }
        }
        return [$min, $max];
    }

    private function isUnconditionallySkipped(string $skipIf): bool
    {
        $body = preg_replace('/^\s*<\?php|\?>\s*$/', '', trim($skipIf));
        if (!is_string($body) || $body === '') {
            return false;
        }
        return preg_match('/^\s*(?:die|exit)\s*\(/i', $body) === 1;
    }

    private function looksLikeDiagnosticTest(string $title, string $expected, string $code): bool
    {
        if (preg_match('/(?:Fatal error|Parse error|Compile Error|Uncaught |Warning:|Deprecated:|Notice:)/i', $expected)) {
            return true;
        }
        return preg_match('/\b(error|invalid|reject|forbid|unsupported|cannot|failure)\b/i', $title) === 1
            && preg_match('/\bcatch\s*\(/i', $code) === 1;
    }

    private function analyzePhpUnit(string $sourceDirectory, string $fixtureDirectory): void
    {
        $testFiles = $this->discoverFiles([$sourceDirectory], 'Test.php');
        foreach ($testFiles as $testFile) {
            $contents = file_get_contents($testFile);
            if (!is_string($contents)) {
                continue;
            }
            try {
                $stmts = $this->parser->parse($contents) ?? [];
            } catch (\Throwable $error) {
                $this->parseErrors[] = ['file' => $this->relativePath($testFile), 'message' => $error->getMessage()];
                continue;
            }
            $methods = $this->findTestMethods($stmts);
            $allMethods = $this->findClassMethods($stmts);
            $providers = [];
            foreach ($allMethods as $candidateMethod) {
                $providers[$candidateMethod->name->toString()] = $this->collectProviderRows($candidateMethod);
            }

            foreach ($methods as $method) {
                $methodId = $this->relativePath($testFile) . '::' . $method->name->toString();
                $calls = $this->collectMethodCalls($method->stmts ?? []);
                $providerName = $this->findDataProviderName($method);
                $providerRows = $providerName === null ? [] : ($providers[$providerName] ?? []);
                $hasExpectedException = false;
                $convertsAnotherLanguage = false;
                foreach ($calls as $call) {
                    if (in_array($call['name'], ['expectException', 'expectExceptionMessage'], true)) {
                        $hasExpectedException = true;
                    }
                    if (in_array($call['name'], ['convert', 'convertSource'], true)) {
                        $convertsAnotherLanguage = true;
                    }
                }
                $hasCompilerFixtureCall = false;
                foreach ($calls as $call) {
                    if (!in_array($call['name'], ['compile', 'exec', 'assertCompiles', 'compileNativeWithReporter'], true)) {
                        continue;
                    }
                    $hasCompilerFixtureCall = true;
                    $fixtures = [];
                    $literalFixture = $this->findPhpFixtureArgument($call['node']);
                    if ($literalFixture !== null) {
                        $fixtures[] = $literalFixture;
                    } else {
                        $fixtures = array_merge(
                            $this->findPhpFixtureStrings($method),
                            $this->findPhpFixtureStrings($providerRows),
                        );
                        $fixtures = array_values(array_unique($fixtures));
                    }
                    if ($fixtures === []) {
                        $this->unresolvedPhpUnitFixtures[] = ['test' => $methodId, 'fixture' => '<dynamic>'];
                        continue;
                    }
                    foreach ($fixtures as $fixture) {
                        $this->recordPhpUnitFixture(
                            $methodId,
                            $method,
                            $fixtureDirectory,
                            $fixture,
                            $call['name'] === 'exec' || $hasExpectedException,
                        );
                    }
                }

                if ($hasCompilerFixtureCall || $providerRows === [] || $convertsAnotherLanguage) {
                    continue;
                }
                $negativeProvider = $hasExpectedException
                    || preg_match('/(?:invalid|reject|incompatib|negative|unsupported|error)/i', $method->name->toString()) === 1;
                foreach ($providerRows as $index => $row) {
                    $source = $this->findInlinePhpSource($row);
                    if ($source === null) {
                        continue;
                    }
                    if (!str_contains($source, '<?php')) {
                        $source = '<?php ' . $source;
                        if (!preg_match('/;\s*$/', $source)) {
                            $source .= ';';
                        }
                    }
                    $inlineId = $methodId . ' -> provider row ' . $index;
                    $features = $this->parseSourceFeatures($source, $inlineId, $negativeProvider);
                    if ($features === []) {
                        continue;
                    }
                    $phpVersion = $this->findPhpVersionString($row);
                    $record = [
                        'id' => $inlineId,
                        'kind' => 'phpunit_inline_source',
                        'title' => $method->name->toString(),
                        'min_php' => $phpVersion,
                        'max_php' => $phpVersion,
                        'excluded_reason' => null,
                        'features' => array_keys($features),
                        'negative' => $negativeProvider,
                    ];
                    $this->tests[] = $record;
                    if ($negativeProvider) {
                        $this->addEvidence($features, 'negative_diagnostic', $record);
                    } else {
                        $this->addEvidence($features, 'positive_compile', $record);
                    }
                }
            }
        }
    }

    /** @param Node[] $nodes @return list<Node\Stmt\ClassMethod> */
    private function findTestMethods(array $nodes): array
    {
        return array_values(array_filter(
            $this->findClassMethods($nodes),
            static fn (Node\Stmt\ClassMethod $method): bool => str_starts_with($method->name->toString(), 'test'),
        ));
    }

    /** @param Node[] $nodes @return list<Node\Stmt\ClassMethod> */
    private function findClassMethods(array $nodes): array
    {
        $methods = [];
        $this->walkValues($nodes, static function (Node $node) use (&$methods): void {
            if ($node instanceof Node\Stmt\ClassMethod) {
                $methods[] = $node;
            }
        });
        return $methods;
    }

    /** @param Node[] $nodes @return list<array{name: string, node: Expr\MethodCall}> */
    private function collectMethodCalls(array $nodes): array
    {
        $calls = [];
        $this->walkValues($nodes, static function (Node $node) use (&$calls): void {
            if ($node instanceof Expr\MethodCall && $node->name instanceof Node\Identifier) {
                $calls[] = ['name' => $node->name->toString(), 'node' => $node];
            }
        });
        return $calls;
    }

    private function findPhpFixtureArgument(Expr\MethodCall $call): ?string
    {
        foreach (array_reverse($call->getArgs()) as $argument) {
            if ($argument->value instanceof Node\Scalar\String_ && str_ends_with($argument->value->value, '.php')) {
                return $argument->value->value;
            }
        }
        return null;
    }

    /**
     * @return list<list<string>>
     */
    private function collectProviderRows(Node\Stmt\ClassMethod $method): array
    {
        $rows = [];
        $this->walkValues($method->stmts ?? [], static function (Node $node) use (&$rows): void {
            if (!$node instanceof Expr\Yield_ || !$node->value instanceof Expr\Array_) {
                return;
            }
            $strings = [];
            foreach ($node->value->items as $item) {
                if ($item?->value instanceof Node\Scalar\String_) {
                    $strings[] = $item->value->value;
                }
            }
            if ($strings !== []) {
                $rows[] = $strings;
            }
        });

        if ($rows !== []) {
            return $rows;
        }

        // Array-returning providers are common for fixture-name lists.
        foreach ($method->stmts ?? [] as $statement) {
            if (!$statement instanceof Node\Stmt\Return_ || !$statement->expr instanceof Expr\Array_) {
                continue;
            }
            foreach ($statement->expr->items as $outerItem) {
                if (!$outerItem?->value instanceof Expr\Array_) {
                    continue;
                }
                $strings = [];
                foreach ($outerItem->value->items as $item) {
                    if ($item?->value instanceof Node\Scalar\String_) {
                        $strings[] = $item->value->value;
                    }
                }
                if ($strings !== []) {
                    $rows[] = $strings;
                }
            }
        }
        return $rows;
    }

    private function findDataProviderName(Node\Stmt\ClassMethod $method): ?string
    {
        $comment = $method->getDocComment()?->getText() ?? '';
        if (preg_match('/@dataProvider\s+([A-Za-z_][A-Za-z0-9_]*)/', $comment, $match)) {
            return $match[1];
        }
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (strtolower($attribute->name->getLast()) !== 'dataprovider') {
                    continue;
                }
                $argument = $attribute->args[0]->value ?? null;
                if ($argument instanceof Node\Scalar\String_) {
                    return $argument->value;
                }
            }
        }
        return null;
    }

    /** @param mixed $value @return list<string> */
    private function findPhpFixtureStrings(mixed $value): array
    {
        $fixtures = [];
        if (is_string($value)) {
            return str_ends_with($value, '.php') ? [$value] : [];
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                $fixtures = array_merge($fixtures, $this->findPhpFixtureStrings($item));
            }
            return $fixtures;
        }
        if ($value instanceof Node) {
            $this->walkValues($value, static function (Node $node) use (&$fixtures): void {
                if ($node instanceof Node\Scalar\String_ && str_ends_with($node->value, '.php')) {
                    $fixtures[] = $node->value;
                }
            });
        }
        return array_values(array_unique($fixtures));
    }

    /** @param list<string> $row */
    private function findInlinePhpSource(array $row): ?string
    {
        foreach ($row as $value) {
            if (str_contains($value, '<?php')) {
                return $value;
            }
        }
        foreach ($row as $value) {
            if (preg_match('/^(?:8\.\d+|[A-Za-z_][A-Za-z0-9_]*\.php)$/', trim($value))) {
                continue;
            }
            if (preg_match('/\b(?:function|class|interface|trait|enum|const|new)\b|\$|\|>|::|=>|\.\.\.|\(void\)|[;{}\[\]]/', $value)) {
                return $value;
            }
        }
        return null;
    }

    /** @param list<string> $row */
    private function findPhpVersionString(array $row): ?string
    {
        foreach ($row as $value) {
            if (preg_match('/^8\.\d+$/', $value)) {
                return $value;
            }
        }
        return null;
    }

    private function recordPhpUnitFixture(
        string $methodId,
        Node\Stmt\ClassMethod $method,
        string $fixtureDirectory,
        string $fixture,
        bool $negative,
    ): void {
        $fixturePath = rtrim($fixtureDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fixture;
        if (!is_file($fixturePath)) {
            $this->unresolvedPhpUnitFixtures[] = ['test' => $methodId, 'fixture' => $fixture];
            return;
        }
        $fixtureCode = file_get_contents($fixturePath);
        if (!is_string($fixtureCode)) {
            return;
        }
        $features = $this->parseSourceFeatures($fixtureCode, $this->relativePath($fixturePath));
        $record = [
            'id' => $methodId . ' -> ' . $this->relativePath($fixturePath),
            'kind' => 'phpunit_fixture',
            'title' => $method->name->toString(),
            'min_php' => null,
            'max_php' => null,
            'excluded_reason' => null,
            'features' => array_keys($features),
            'negative' => $negative,
        ];
        $this->tests[] = $record;
        $this->addEvidence($features, $negative ? 'negative_diagnostic' : 'positive_compile', $record);
    }

    /** @return array<string, true> */
    private function parseSourceFeatures(string $code, string $sourceId, bool $expectedInvalid = false): array
    {
        try {
            $stmts = $this->parser->parse($code) ?? [];
        } catch (\Throwable $error) {
            $target = ['file' => $sourceId, 'message' => $error->getMessage()];
            if ($expectedInvalid) {
                $this->expectedParserDiagnostics[] = $target;
            } else {
                $this->parseErrors[] = $target;
            }
            $features = [];
            $this->detectSourceFeatures($code, $features);
            return $features;
        }
        $features = [];
        $this->walkFeatureNodes($stmts, [], $features);
        $this->detectSourceFeatures($code, $features);
        return $features;
    }

    /**
     * @param list<Node> $ancestors
     * @param array<string, true> $features
     */
    private function walkFeatureNodes(mixed $value, array $ancestors, array &$features): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->walkFeatureNodes($item, $ancestors, $features);
            }
            return;
        }
        if (!$value instanceof Node) {
            return;
        }

        $astFeature = 'ast:' . $value->getType();
        if (isset($this->catalog[$astFeature])) {
            $features[$astFeature] = true;
        }
        $this->detectSemanticNodeFeatures($value, $ancestors, $features);
        $nextAncestors = [...$ancestors, $value];
        foreach ($value->getSubNodeNames() as $subNodeName) {
            $this->walkFeatureNodes($value->{$subNodeName}, $nextAncestors, $features);
        }
    }

    /** @param list<Node> $ancestors @param array<string, true> $features */
    private function detectSemanticNodeFeatures(Node $node, array $ancestors, array &$features): void
    {
        $mark = static function (string $id) use (&$features): void {
            $features['semantic:' . $id] = true;
        };

        if ($node instanceof Node\Name\Relative) {
            $mark('relative_namespace_name');
        }
        if ($node instanceof Node\Arg && $node->name !== null) {
            $mark('named_arguments');
        }
        if ($node instanceof Node\VariadicPlaceholder) {
            $mark('first_class_callable');
        }
        if ($node instanceof Node\Param && $node->isPromoted()) {
            $mark('constructor_property_promotion');
            if (($node->flags & Modifiers::VISIBILITY_SET_MASK) !== 0) {
                $mark('promoted_asymmetric_property');
                $mark('asymmetric_property_visibility');
            }
            if (($node->flags & Modifiers::READONLY) !== 0) {
                $mark('readonly_property');
            }
            if (($node->flags & Modifiers::FINAL) !== 0) {
                $mark('final_property');
            }
        }
        if ($node instanceof Node\Stmt\Property) {
            if (($node->flags & Modifiers::VISIBILITY_SET_MASK) !== 0) {
                $mark('asymmetric_property_visibility');
            }
            if (($node->flags & Modifiers::READONLY) !== 0) {
                $mark('readonly_property');
            }
            if (($node->flags & Modifiers::FINAL) !== 0) {
                $mark('final_property');
            }
            if ($node->hooks !== []) {
                $mark('property_hooks');
            }
        }
        if ($node instanceof Node\Stmt\Class_ && $node->isReadonly()) {
            $mark('readonly_class');
        }
        if ($node instanceof Node\PropertyHook) {
            $mark('property_hooks');
            $hookName = strtolower($node->name->toString());
            if ($hookName === 'get') {
                $mark('property_hook_get');
            } elseif ($hookName === 'set') {
                $mark('property_hook_set');
            }
            if ($node->byRef) {
                $mark('property_hook_by_reference');
            }
            if (($node->flags & Modifiers::FINAL) !== 0) {
                $mark('final_property_hook');
            }
        }
        if ($node instanceof Node\Scalar\MagicConst\Property) {
            $mark('property_magic_constant');
        }
        if ($node instanceof Expr\Cast\Void_) {
            $mark('void_cast');
        }
        if ($node instanceof Expr\BinaryOp\Pipe) {
            $mark('pipe_operator');
        }
        if ($node instanceof Expr\FuncCall
            && $node->name instanceof Node\Name
            && strtolower($node->name->toString()) === 'clone'
        ) {
            $mark('clone_with');
        }
        if ($node instanceof Node\Stmt\ClassConst && $node->attrGroups !== []) {
            $mark('class_constant_attributes');
        }
        if ($node instanceof Node\Stmt\Const_ && $node->attrGroups !== []) {
            $mark('global_constant_attributes');
        }
        if ($node instanceof Node\UnionType && $this->unionContainsIntersection($node)) {
            $mark('dnf_types');
            $parent = $ancestors[array_key_last($ancestors)] ?? null;
            if ($parent instanceof Node\Param) {
                $mark('dnf_parameter');
                if ($this->hasClosureAncestor($ancestors)) {
                    $mark('dnf_closure_signature');
                }
            } elseif ($parent instanceof Node\Stmt\Property) {
                $mark('dnf_property');
            } elseif ($parent instanceof FunctionLike) {
                $mark('dnf_return');
                if ($parent instanceof Expr\Closure || $parent instanceof Expr\ArrowFunction) {
                    $mark('dnf_closure_signature');
                }
            }
        }
        if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            foreach (array_reverse($ancestors) as $ancestor) {
                if ($ancestor instanceof Node\Const_) {
                    $mark('constant_closure');
                    break;
                }
                if ($ancestor instanceof Node\Param || $ancestor instanceof Node\PropertyItem) {
                    $mark('closure_default_value');
                    break;
                }
            }
        }
    }

    /** @param array<string, true> $features */
    private function detectSourceFeatures(string $code, array &$features): void
    {
        $mark = static function (string $id) use (&$features): void {
            $features['semantic:' . $id] = true;
        };
        if (preg_match('/\b(?:if|elseif|for|foreach|while|switch)\s*\([^;{}]*\)\s*:/s', $code)
            || preg_match('/\bend(?:if|for|foreach|while|switch)\s*;/i', $code)
        ) {
            $mark('alternative_control_syntax');
        }
        if (preg_match('/\b0[bB][01](?:_?[01])*\b/', $code)) {
            $mark('numeric_literal_binary');
        }
        if (preg_match('/\b0[oO][0-7](?:_?[0-7])*\b/', $code)) {
            $mark('numeric_literal_explicit_octal');
        }
        if (preg_match('/\b(?:\d[\dA-Fa-f]*_[\dA-Fa-f_]*|0[xXbBoO][\dA-Fa-f]+_[\dA-Fa-f_]*)\b/', $code)) {
            $mark('numeric_literal_separator');
        }
        if (preg_match('/\b(?:exit|die)\s*\(\s*message\s*:/i', $code)) {
            $mark('exit_named_argument');
        }
    }

    private function unionContainsIntersection(Node\UnionType $type): bool
    {
        foreach ($type->types as $member) {
            if ($member instanceof Node\IntersectionType) {
                return true;
            }
        }
        return false;
    }

    /** @param list<Node> $ancestors */
    private function hasClosureAncestor(array $ancestors): bool
    {
        foreach ($ancestors as $ancestor) {
            if ($ancestor instanceof Expr\Closure || $ancestor instanceof Expr\ArrowFunction) {
                return true;
            }
        }
        return false;
    }

    /** @param mixed $value @param callable(Node): void $visitor */
    private function walkValues(mixed $value, callable $visitor): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->walkValues($item, $visitor);
            }
            return;
        }
        if (!$value instanceof Node) {
            return;
        }
        $visitor($value);
        foreach ($value->getSubNodeNames() as $name) {
            $this->walkValues($value->{$name}, $visitor);
        }
    }

    /**
     * @param array<string, true> $features
     * @param array<string, mixed> $record
     */
    private function addEvidence(array $features, string $axis, array $record): void
    {
        foreach ($features as $featureId => $_) {
            if (isset($this->evidence[$featureId][$axis])) {
                $this->evidence[$featureId][$axis][$record['id']] = $record;
            }
        }
    }

    /** @return array<string, mixed> */
    private function buildReport(
        array $phptPaths,
        ?string $phpUnitSourceDirectory,
        ?string $phpUnitFixtureDirectory,
    ): array {
        $features = [];
        $matrix = [];
        $coverageByVersion = [];
        foreach ($this->targetPhpVersions as $version) {
            $coverageByVersion[$version] = ['applicable_features' => 0];
            foreach (self::AXES as $axis) {
                $coverageByVersion[$version][$axis] = ['covered' => 0, 'denominator' => 0, 'percentage' => 0.0];
            }
        }

        foreach ($this->catalog as $id => $definition) {
            $feature = $definition;
            $feature['evidence'] = [];
            foreach (self::AXES as $axis) {
                $feature['evidence'][$axis] = array_keys($this->evidence[$id][$axis]);
            }
            $features[] = $feature;

            foreach ($this->targetPhpVersions as $version) {
                if (!$this->featureAppliesToVersion($definition, $version)) {
                    continue;
                }
                $coverageByVersion[$version]['applicable_features']++;
                $row = [
                    'php_version' => $version,
                    'feature_id' => $id,
                    'feature' => $definition['label'],
                    'category' => $definition['category'],
                ];
                foreach (self::AXES as $axis) {
                    $coverageByVersion[$version][$axis]['denominator']++;
                    $covered = $this->hasCompatibleEvidence($this->evidence[$id][$axis], $version);
                    $row[$axis] = $covered;
                    if ($covered) {
                        $coverageByVersion[$version][$axis]['covered']++;
                    }
                }
                $matrix[] = $row;
            }
        }

        foreach ($coverageByVersion as &$coverage) {
            foreach (self::AXES as $axis) {
                $coverage[$axis]['percentage'] = $this->percentage(
                    $coverage[$axis]['covered'],
                    $coverage[$axis]['denominator'],
                );
            }
        }
        unset($coverage);

        $astNodes = [];
        $astCovered = 0;
        $astDenominator = 0;
        foreach ($this->catalog as $id => $definition) {
            if ($definition['category'] !== 'ast') {
                continue;
            }
            $astDenominator++;
            $activeTests = [];
            foreach (self::AXES as $axis) {
                foreach ($this->evidence[$id][$axis] as $testId => $_) {
                    $activeTests[$testId] = true;
                }
            }
            if ($activeTests !== []) {
                $astCovered++;
            }
            $astNodes[] = [
                'node_type' => $definition['node_type'],
                'introduced' => $definition['introduced'],
                'active_test_sources' => count($activeTests),
            ];
        }
        usort($astNodes, static fn (array $left, array $right): int => $left['node_type'] <=> $right['node_type']);

        $parsedPhptCount = count(array_filter($this->tests, static fn (array $test): bool => $test['kind'] === 'phpt'));
        $phpUnitLinks = count(array_filter($this->tests, static fn (array $test): bool => $test['kind'] === 'phpunit_fixture'));
        return [
            'schema_version' => 1,
            'generated_at' => date(DATE_ATOM),
            'scope' => [
                'phpt_paths' => $phptPaths,
                'phpunit_source_directory' => $phpUnitSourceDirectory,
                'phpunit_fixture_directory' => $phpUnitFixtureDirectory,
                'target_php_versions' => $this->targetPhpVersions,
            ],
            'classification_rules' => [
                'positive_compile' => 'Active, parseable non-diagnostic PHPT sources and positive PHPUnit compile fixtures.',
                'runtime_semantics' => 'Active, non-diagnostic PHPT sources with EXPECT/EXPECTF/EXPECTREGEX.',
                'negative_diagnostic' => 'PHPT diagnostic expectations plus PHPUnit TestError/exec fixture links.',
                'excluded' => 'XFAIL and syntactically unconditional SKIPIF tests do not satisfy evidence axes.',
            ],
            'denominators' => [
                'ast_node_kinds' => [
                    'total' => $astDenominator,
                    'definition' => 'Concrete Node kinds shipped by php-parser, excluding Expr_Error parser recovery.',
                ],
                'feature_axis' => 'All catalog features whose introduced version is <= the target PHP version.',
            ],
            'summary' => [
                'phpt_files' => $this->discoveredPhptFiles,
                'parsed_phpt_files' => $parsedPhptCount,
                'phpunit_fixture_links' => $phpUnitLinks,
                'parsed_source_records' => count($this->tests),
                'ast_node_coverage' => [
                    'covered' => $astCovered,
                    'denominator' => $astDenominator,
                    'percentage' => $this->percentage($astCovered, $astDenominator),
                ],
                'coverage_by_php_version' => $coverageByVersion,
            ],
            'features' => $features,
            'matrix' => $matrix,
            'ast_nodes' => $astNodes,
            'tests' => $this->tests,
            'parse_errors' => $this->parseErrors,
            'expected_parser_diagnostics' => $this->expectedParserDiagnostics,
            'unresolved_phpunit_fixtures' => $this->unresolvedPhpUnitFixtures,
        ];
    }

    /** @param array<string, array<string, mixed>> $evidence */
    private function hasCompatibleEvidence(array $evidence, string $version): bool
    {
        foreach ($evidence as $record) {
            if (($record['min_php'] === null || version_compare($version, $record['min_php'], '>='))
                && ($record['max_php'] === null || version_compare($version, $record['max_php'], '<='))
            ) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed> $feature */
    private function featureAppliesToVersion(array $feature, string $version): bool
    {
        return version_compare($version, $feature['introduced'], '>=');
    }

    /** @param list<string> $paths @return list<string> */
    private function discoverFiles(array $paths, string $suffix): array
    {
        $files = [];
        foreach ($paths as $path) {
            $absolute = $this->absolutePath($path);
            if (is_file($absolute)) {
                if (str_ends_with($absolute, $suffix)) {
                    $files[$absolute] = true;
                }
                continue;
            }
            if (!is_dir($absolute)) {
                throw new \RuntimeException('Test path not found: ' . $path);
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                    $files[$file->getPathname()] = true;
                }
            }
        }
        $files = array_keys($files);
        sort($files);
        return $files;
    }

    private function absolutePath(string $path): string
    {
        if ($path !== '' && ($path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path))) {
            return $path;
        }
        return rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
    }

    private function relativePath(string $path): string
    {
        $prefix = rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    private function versionIdToMinor(int $versionId): string
    {
        return intdiv($versionId, 10000) . '.' . intdiv($versionId % 10000, 100);
    }

    private function previousMinor(string $version): string
    {
        [$major, $minor] = array_map('intval', explode('.', $version, 2));
        return $minor > 0 ? $major . '.' . ($minor - 1) : ($major - 1) . '.99';
    }

    private function maxVersion(?string $left, string $right): string
    {
        return $left === null || version_compare($right, $left, '>') ? $right : $left;
    }

    private function minVersion(?string $left, string $right): string
    {
        return $left === null || version_compare($right, $left, '<') ? $right : $left;
    }

    private function percentage(int $covered, int $denominator): float
    {
        return $denominator === 0 ? 0.0 : round($covered * 100 / $denominator, 1);
    }

    /** @param array{covered: int, denominator: int, percentage: float} $metric */
    private function formatRatio(array $metric): string
    {
        return sprintf('%d/%d (%.1f%%)', $metric['covered'], $metric['denominator'], $metric['percentage']);
    }
}

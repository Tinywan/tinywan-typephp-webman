<?php

use TypePhp\Analysis\CompilationStatistics;
use TypePhp\Build\NanoExtensionSelector;
use TypePhp\Build\NanoSourceComposer;
use TypePhp\CompilerTest;
use TypePhp\Type;

final class CompilationStatisticsTest extends BaseTest
{
    public function testConvertCollectsFunctionAndEmittedTypeUsage(): void
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $file = dirname(__DIR__) . '/code/compilation_statistics.php';
        $compiler->addFiles([$file]);
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        $statistics = $compiler->getCompilationStatistics();
        self::assertTrue($statistics->has(CompilationStatistics::FUNCTIONS, 'json_encode'));
        self::assertTrue($statistics->has(CompilationStatistics::FUNCTIONS, 'time'));
        self::assertTrue($statistics->has(CompilationStatistics::DIRECT_FUNCTIONS, 'json_encode'));
        self::assertTrue($statistics->has(CompilationStatistics::DIRECT_FUNCTIONS, 'time'));
        self::assertTrue($statistics->has(CompilationStatistics::TYPES, Type::STR));

        $selection = (new NanoExtensionSelector())->select($statistics, ['date', 'json']);
        self::assertSame(['date', 'json'], $selection->extensions);
        self::assertSame([], $selection->features);
    }

    public function testStatisticsAreReusableByCategory(): void
    {
        $statistics = new CompilationStatistics();
        $statistics->begin();
        $statistics->record(CompilationStatistics::FUNCTIONS, 'json_encode');
        $statistics->record(CompilationStatistics::FUNCTIONS, 'json_encode');
        $statistics->record('future-module-data', 'example');
        $statistics->finish();

        self::assertSame(['json_encode' => 2], $statistics->get(CompilationStatistics::FUNCTIONS));
        self::assertSame(['example' => 1], $statistics->get('future-module-data'));
    }

    public function testNanoSelectorUsesFunctionsClassesTypesAndDependencies(): void
    {
        $statistics = new CompilationStatistics();
        $statistics->begin();
        $statistics->record(CompilationStatistics::FUNCTIONS, 'preg_match');
        $statistics->record(CompilationStatistics::RUNTIME_FUNCTIONS, 'preg_match');
        $statistics->record(CompilationStatistics::CLASSES, 'ArrayObject');
        $statistics->record(CompilationStatistics::TYPES, Type::BIGINT);
        $statistics->finish();

        $selection = (new NanoExtensionSelector())->select(
            $statistics,
            ['standard', 'date', 'hash', 'json', 'pcre', 'random', 'reflection', 'spl', 'filter', 'bcmath'],
        );

        self::assertFalse($selection->completeFallback);
        self::assertSame(['bcmath', 'json', 'pcre', 'spl'], $selection->extensions);
        self::assertSame([], $selection->features);
    }

    public function testDynamicFunctionCallFallsBackToBuiltInExtensionsOnly(): void
    {
        $statistics = new CompilationStatistics();
        $statistics->begin();
        $statistics->record(CompilationStatistics::DYNAMIC_CAPABILITIES, 'function-call');
        $statistics->finish();

        $selection = (new NanoExtensionSelector())->select(
            $statistics,
            ['curl', 'json', 'openssl', 'standard'],
        );

        self::assertTrue($selection->completeFallback);
        self::assertSame(['json', 'standard'], $selection->extensions);

        $composition = (new NanoSourceComposer())->compose(
            sys_get_temp_dir() . '/typephp-nano-dynamic-fallback-test',
            'dynamic_fallback_test',
            false,
            $statistics,
        );
        self::assertFalse($this->containsSource($composition['packageSources'], 'main/network.c'));
        self::assertFalse($this->containsSource($composition['packageSources'], 'main/streams/xp_socket.c'));
        self::assertFalse($this->containsSource($composition['packageSources'], 'ext/curl/interface.c'));
        self::assertFalse($this->containsSource($composition['packageSources'], 'ext/openssl/openssl.c'));
    }

    public function testDateRemainsWholeWhileStandardUsesFineGrainedFeatures(): void
    {
        $statistics = new CompilationStatistics();
        $statistics->begin();
        foreach (['date', 'gmdate', 'array_keys', 'trim'] as $function) {
            $statistics->record(CompilationStatistics::FUNCTIONS, $function);
            $statistics->record(CompilationStatistics::DIRECT_FUNCTIONS, $function);
        }
        $statistics->finish();

        $selection = (new NanoExtensionSelector())->select($statistics, ['date', 'standard']);

        self::assertSame(['date'], $selection->extensions);
        self::assertSame(
            ['standard.array', 'standard.string'],
            $selection->features,
        );
    }

    public function testZendDispatchedDateFunctionSelectsTheFullExtension(): void
    {
        $statistics = new CompilationStatistics();
        $statistics->begin();
        $statistics->record(CompilationStatistics::FUNCTIONS, 'date_create');
        $statistics->record(CompilationStatistics::RUNTIME_FUNCTIONS, 'date_create');
        $statistics->finish();

        $selection = (new NanoExtensionSelector())->select($statistics, ['date']);

        self::assertSame(['date'], $selection->extensions);
        self::assertSame([], $selection->features);

        $composition = (new NanoSourceComposer())->compose(
            sys_get_temp_dir() . '/typephp-nano-date-component-test',
            'date_component_test',
            false,
            $statistics,
        );
        self::assertTrue($this->containsSource($composition['packageSources'], 'ext/date/php_date.c'));
        self::assertTrue($this->containsSource($composition['packageSources'], 'ext/date/lib/timelib.c'));
        self::assertTrue($this->containsSource($composition['packageSources'], 'src/std/datetime.cc'));
        self::assertStringContainsString('date_module_entry', file_get_contents($composition['registry']));
    }

    public function testDirectWrapperStillSelectsItsProvidingExtension(): void
    {
        $statistics = new CompilationStatistics();
        $statistics->begin();
        $statistics->record(CompilationStatistics::FUNCTIONS, 'json_encode');
        $statistics->record(CompilationStatistics::DIRECT_FUNCTIONS, 'json_encode');
        $statistics->finish();

        $selection = (new NanoExtensionSelector())->select($statistics, ['json']);

        self::assertSame(['json'], $selection->extensions);
        self::assertSame([], $selection->features);
    }

    public function testNanoComposerAppliesStandardComponentSourcesAndDefines(): void
    {
        $statistics = new CompilationStatistics();
        $statistics->begin();
        $statistics->record(CompilationStatistics::FUNCTIONS, 'array_keys');
        $statistics->record(CompilationStatistics::DIRECT_FUNCTIONS, 'array_keys');
        $statistics->finish();

        $composition = (new NanoSourceComposer())->compose(
            sys_get_temp_dir() . '/typephp-nano-component-test',
            'component_test',
            false,
            $statistics,
        );

        self::assertContains('PHP_NANO_SELECTIVE=1', $composition['defines']);
        self::assertContains('PHP_NANO_STANDARD_ARRAY=1', $composition['defines']);
        self::assertContains('PHP_NANO_STANDARD_CORE=1', $composition['defines']);
        self::assertTrue($this->containsSource($composition['packageSources'], 'ext/standard/array.c'));
        self::assertTrue($this->containsSource($composition['packageSources'], 'ext/standard/math.c'));
        self::assertTrue($this->containsSource($composition['packageSources'], 'ext/standard/string.c'));
        self::assertTrue($this->containsSource($composition['packageSources'], 'ext/standard/html.c'));
        self::assertFalse($this->containsSource($composition['packageSources'], 'ext/standard/base64.c'));
        self::assertFalse($this->containsSource($composition['packageSources'], 'ext/date/php_date.c'));
        $registry = file_get_contents($composition['registry']);
        self::assertStringContainsString('basic_functions_module', $registry);
        self::assertStringContainsString('random_module_entry', $registry);
        self::assertStringNotContainsString('date_module_entry', $registry);
    }

    public function testNanoComposerAlwaysIncludesSharedStandardSources(): void
    {
        $statistics = new CompilationStatistics();
        $statistics->begin();
        $statistics->record(CompilationStatistics::FUNCTIONS, 'json_encode');
        $statistics->record(CompilationStatistics::DIRECT_FUNCTIONS, 'json_encode');
        $statistics->finish();

        $composition = (new NanoSourceComposer())->compose(
            sys_get_temp_dir() . '/typephp-nano-shared-standard-test',
            'shared_standard_test',
            false,
            $statistics,
        );

        self::assertTrue($this->containsSource($composition['packageSources'], 'ext/standard/array.c'));
        self::assertTrue($this->containsSource($composition['packageSources'], 'ext/standard/string.c'));
        self::assertTrue($this->containsSource($composition['packageSources'], 'ext/standard/html.c'));
        self::assertTrue($this->containsSource($composition['packageSources'], 'ext/standard/url.c'));
        self::assertTrue($this->containsSource($composition['packageSources'], 'ext/standard/scanf.c'));
        self::assertNotContains('PHP_NANO_STANDARD_ARRAY=1', $composition['defines']);
        self::assertNotContains('PHP_NANO_STANDARD_STRING=1', $composition['defines']);
        self::assertNotContains('PHP_NANO_STANDARD_ENCODING=1', $composition['defines']);
    }

    /** @param list<string> $sources */
    private function containsSource(array $sources, string $suffix): bool
    {
        foreach ($sources as $source) {
            if (str_ends_with(str_replace('\\', '/', $source), $suffix)) {
                return true;
            }
        }
        return false;
    }
}

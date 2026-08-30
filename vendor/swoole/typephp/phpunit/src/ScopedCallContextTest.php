<?php

class ScopedCallContextTest extends \BaseTest
{
    public function testMethodCreatesOneReusableCallableScope(): void
    {
        global $translator;
        $compiler = \TypePhp\CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $testFile = __DIR__ . '/../code/scoped-call-context-reuse.php';
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $cppFile = $compiler->convertFile($testFile);
        $cpp = file_get_contents($cppFile);

        $this->assertSame(1, substr_count($cpp, 'php::getCallableScope('));
        $this->assertMatchesRegularExpression(
            '/php::CallableScope (tmp_var_\d+) = php::getCallableScope\(/',
            $cpp,
        );
        preg_match('/php::CallableScope (tmp_var_\d+) =/', $cpp, $matches);
        $this->assertGreaterThanOrEqual(4, substr_count($cpp, $matches[1]));
        $this->assertSame(1, substr_count($cpp, 'php::UserCodeScopeGuard'));
        $this->assertStringNotContainsString('makeScopedCallableMap', $cpp);
    }
}

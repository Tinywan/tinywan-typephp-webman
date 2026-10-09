<?php

use TypePhp\CompilerTest;

final class PolymorphicClassDispatchTest extends BaseTest
{
    public function testPolymorphicClassIntrospectionAndStaticDispatch(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/polymorphic-dispatch.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $code = file_get_contents($compiler->convertFile($source));

        self::assertIsString($code);

        // Exact new object definition get_class() folds to compile-time literal string
        self::assertMatchesRegularExpression(
            '/php_getexactclass\(\) \{.*?tmp_var_\d+ = \(get_str\(\d+\)\);/s',
            $code,
        );

        // Polymorphic get_class() must NOT fold or blindly invoke unchecked C++ helper;
        // it must use runtime php::call to enforce PHP argument semantics (TypeError on null),
        // then restore the fixed string representation declared by get_class().
        $persistentFunctions = (new \ReflectionProperty($compiler, 'persistentFuncMap'))->getValue($compiler);
        $literalStrings = (new \ReflectionProperty($compiler, 'literalStrings'))->getValue($compiler);
        self::assertArrayHasKey('get_class', $persistentFunctions);
        self::assertArrayHasKey('get_class', $literalStrings);
        self::assertMatchesRegularExpression(
            sprintf(
                '/php_getpolymorphicclass\(\) \{.*?tmp_var_\d+ = \(php::toString\(php::call\(get_persistent_func'
                    . '\(PersistentFuncId\{%d\}, get_str\(%d\)\), php::VarList\{animal\}\)\)\);/s',
                $persistentFunctions['get_class'],
                $literalStrings['get_class'],
            ),
            $code,
        );

        // Exact new object definition static call devirtualizes to cached call
        self::assertMatchesRegularExpression(
            '/php_callexactstatic\(\) \{.*?typephp_call_cached\(get_str\(\d+\)/s',
            $code,
        );

        // Polymorphic static call from global function dispatches dynamically via callStaticMethod
        self::assertMatchesRegularExpression(
            '/php_callpolymorphicstatic\(\) \{.*?php::callStaticMethod\([^)]+\)/s',
            $code,
        );

        // Polymorphic static call from within a class method preserves lexical callable scope
        self::assertMatchesRegularExpression(
            "/php_basescopedcaller__exercise\(.*?php::CallableScope (tmp_var_\d+) = php::getCallableScope\(.*?php::callScoped\(php::concat\(.*?, \\1\)/s",
            $code,
        );
    }
}

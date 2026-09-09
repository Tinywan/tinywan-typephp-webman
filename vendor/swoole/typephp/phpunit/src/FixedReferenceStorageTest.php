<?php

use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

final class FixedReferenceStorageTest extends BaseTest
{
    /** @dataProvider fixedStorageProvider */
    public function testFixedStorageCannotBeCapturedByReference(string $fixture, string $type): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage('of fixed type ' . $type . '; initialize it with std::any()');

        $this->compileFixture($fixture);
    }

    public static function fixedStorageProvider(): iterable
    {
        yield 'object' => ['fixed-reference-generic-object.php', 'php::Object'];
        yield 'typed object' => ['fixed-reference-object.php', 'php::Object'];
        yield 'stream' => ['fixed-reference-stream.php', 'php::Stream'];
        yield 'std container' => ['fixed-reference-std-container.php', 'php::StdVector'];
    }

    /** @dataProvider localClosureFixedReferenceProvider */
    public function testNonEscapingLocalClosureUsesNativeReferenceCapture(
        string $fixture,
        string $capture,
    ): void
    {
        $code = $this->compileFixture($fixture);

        self::assertStringContainsString($capture, $code);
        self::assertStringNotContainsString('php::newClosureWithParameters(', $code);
    }

    public static function localClosureFixedReferenceProvider(): iterable
    {
        yield 'string' => ['fixed-reference-string.php', 'auto closure = [&value]() mutable -> php::Var {'];
        yield 'array' => ['fixed-reference-array.php', 'auto closure = [&value]() mutable -> php::Var {'];
    }

    public function testFixedStorageUsesNativeReferenceForKnownTypedParameter(): void
    {
        $code = $this->compileFixture('fixed-reference-argument.php');

        self::assertStringContainsString('void php_fixedreferenceargumenttarget(php::Str & value)', $code);
        self::assertStringContainsString('php_fixedreferenceargumenttarget(value)', $code);
    }

    public function testFixedStorageCanCreateOneTimeNativeLocalAlias(): void
    {
        $code = $this->compileFixture('fixed-reference-assignment.php');

        self::assertStringContainsString('php::Array & reference = value;', $code);
        self::assertStringNotContainsString('reference = value.toReference()', $code);
    }

    public function testTypedReferenceAssignmentMismatchIsATypePhpError(): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage(
            'Cannot re-assign `$reference` from `php::Str` to `php::Int`',
        );

        $this->compileFixture('typed-reference-assignment-type-error.php');
    }

    public function testTypedReferenceArgumentMismatchIsATypePhpError(): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage(
            'Cannot pass value of type php::Int to reference parameter of type php::Float &',
        );

        $this->compileFixture('typed-reference-argument-type-error.php');
    }

    public function testFixedStorageCannotBeReturnedByReference(): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage('variable $value of fixed type php::Str');

        $this->compileFixture('fixed-reference-return.php');
    }

    public function testTypedReferenceParameterCannotEscapeByReturn(): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage('variable $value of fixed type php::Int');

        $this->compileFixture('typed-reference-parameter-return.php');
    }

    public function testFixedStaticStorageCannotBeCapturedByReference(): void
    {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage('variable $value of fixed type php::Str');

        $this->compileFixture('fixed-reference-static.php');
    }

    public function testFixedStorageUsesBridgeForToRefAtKnownDynamicReferenceBoundary(): void
    {
        $code = $this->compileFixture('fixed-reference-to-ref.php');

        self::assertStringContainsString('php::RefWrap<php::Array>', $code);
        self::assertStringContainsString('.ref()', $code);
        self::assertStringContainsString('.commit()', $code);
    }

    public function testExplicitAnyCanUseReferenceStorage(): void
    {
        $code = $this->compileFixture('fixed-reference-explicit-any.php');

        self::assertStringContainsString('php::Var value', $code);
        self::assertStringContainsString('value.toReference()', $code);
    }

    /** @dataProvider invalidTypedReferenceOperationProvider */
    public function testInvalidTypedReferenceOperationsFailDuringTypePhpCompilation(
        string $fixture,
        string $message,
    ): void {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage($message);

        $this->compileFixture($fixture);
    }

    public static function invalidTypedReferenceOperationProvider(): iterable
    {
        yield 'conditional binding' => [
            'typed-reference-conditional-bind.php',
            'A typed reference local must be bound in the top-level scope of the function',
        ];
        yield 'rebind' => [
            'typed-reference-rebind.php',
            'Cannot rebind fixed or typed reference variable `$alias`',
        ];
        yield 'rebind root' => [
            'typed-reference-root-rebind.php',
            'Cannot rebind fixed or typed reference variable `$first`',
        ];
        yield 'unset alias' => [
            'typed-reference-unset-alias.php',
            'Cannot unset typed reference variable `$alias`',
        ];
        yield 'unset root' => [
            'typed-reference-unset-root.php',
            'Cannot unset variable `$value` while typed references point to it',
        ];
        yield 'property escape' => [
            'typed-reference-property-escape.php',
            'Cannot create a reference to variable $value of fixed type php::Int',
        ];
        yield 'array escape' => [
            'typed-reference-array-escape.php',
            'Cannot create a reference to variable $value of fixed type php::Int',
        ];
        yield 'typed object parameter' => [
            'typed-reference-object-param.php',
            'References are only supported for int, string, float, bool, array, mixed, or union types',
        ];
        yield 'typed object closure parameter' => [
            'typed-reference-object-closure-param.php',
            'References are only supported for int, string, float, bool, array, mixed, or union types',
        ];
    }

    private function compileFixture(string $fixture): string
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/' . $fixture;
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);
        self::assertIsString($code);
        return $code;
    }
}

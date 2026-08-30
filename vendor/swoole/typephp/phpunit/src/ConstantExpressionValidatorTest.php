<?php

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use TypePhp\Exception\SyntaxError;
use TypePhp\Transform\ConstantExpressionValidator;
use TypePhp\Transform\ConstantExpressionValidationVisitor;
use TypePhp\Transform\RuntimeAttributeFactoryLowering;

final class ConstantExpressionValidatorTest extends PHPUnit\Framework\TestCase
{
    /** @dataProvider validExpressionProvider */
    public function testAcceptsPhpAttributeConstantExpressions(string $expression, string $phpVersion): void
    {
        (new ConstantExpressionValidator($phpVersion))->validate(
            $this->parseAttributeExpression($expression, $phpVersion),
            true,
        );

        $this->addToAssertionCount(1);
    }

    public static function validExpressionProvider(): iterable
    {
        yield 'literal array with unpack' => ['[1, ...VALUES, "key" => C::VALUE]', '8.4'];
        yield 'new expression' => ['new Value(flag: true)', '8.4'];
        yield 'new expression property fetch' => ['(new Value())->name', '8.4'];
        yield 'PHP 8.5 cast' => ['(object) ["value" => 1]', '8.5'];
        yield 'PHP 8.5 static closure' => ['static function (): int { return 1; }', '8.5'];
        yield 'PHP 8.5 function callable' => ['strlen(...)', '8.5'];
        yield 'PHP 8.5 static method callable' => ['Value::make(...)', '8.5'];
        yield 'short-circuited invalid right operand' => ['true || loadValue()', '8.5'];
        yield 'unselected invalid ternary branch' => ['true ? 1 : loadValue()', '8.5'];
        yield 'unselected invalid coalesce operand' => ['1 ?? loadValue()', '8.5'];
    }

    /** @dataProvider invalidExpressionProvider */
    public function testRejectsExpressionsExactlyAsPhpDoes(
        string $expression,
        string $phpVersion,
        string $message,
    ): void {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage($message);

        (new ConstantExpressionValidator($phpVersion))->validate(
            $this->parseAttributeExpression($expression, $phpVersion),
            true,
        );
    }

    public static function invalidExpressionProvider(): iterable
    {
        yield 'nested function call' => [
            '[1, [loadValue()]]',
            '8.5',
            'Constant expression contains invalid operations',
        ];
        yield 'ordinary call is invalid in PHP 8.5' => [
            'strlen("value")',
            '8.5',
            'Constant expression contains invalid operations',
        ];
        yield 'PHP 8.5 pipe expression' => [
            '"value" |> strlen(...)',
            '8.5',
            'Constant expression contains invalid operations',
        ];
        yield 'PHP 8.5 void cast is a statement' => [
            '(void) 1',
            '8.5',
            'Constant expression contains invalid operations',
        ];
        yield 'first-class callable before PHP 8.5' => [
            'strlen(...)',
            '8.4',
            'Constant expression contains invalid operations',
        ];
        yield 'closure before PHP 8.5' => [
            'static function (): int { return 1; }',
            '8.4',
            'Constant expression contains invalid operations',
        ];
        yield 'non-static closure' => [
            'function (): int { return 1; }',
            '8.5',
            'Closures in constant expressions must be static',
        ];
        yield 'closure use' => [
            'static function () use ($value): mixed { return $value; }',
            '8.5',
            'Cannot use(...) variables in constant expression',
        ];
        yield 'anonymous class' => [
            'new class {}',
            '8.5',
            'Cannot use anonymous class in constant expression',
        ];
        yield 'dynamic class' => [
            'new $className()',
            '8.5',
            'Cannot use dynamic class name in constant expression',
        ];
        yield 'new static' => [
            'new static()',
            '8.5',
            '"static" is not allowed in compile-time constants',
        ];
        yield 'argument unpacking' => [
            'new Value(...[])',
            '8.5',
            'Argument unpacking in constant expressions is not supported',
        ];
        yield 'empty array dimension' => [
            '[1][]',
            '8.5',
            'Cannot use [] for reading',
        ];
        yield 'positional after named' => [
            'new Value(first: 1, 2)',
            '8.5',
            'Cannot use positional argument after named argument',
        ];
    }

    public function testRejectsUnpackingInTheAttributeArgumentList(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Cannot use unpacking in attribute argument list');

        (new ConstantExpressionValidator('8.5'))->validateArguments(
            $this->parseAttributeArguments('...VALUES', '8.5'),
            true,
            true,
        );
    }

    /** @dataProvider runtimeFactoryExpressionProvider */
    public function testPhp85DynamicExpressionsUseRuntimeFactories(string $expression): void
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromString('8.5'));
        $statements = $parser->parse("<?php #[Test({$expression})] class Target {}");
        self::assertNotNull($statements);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ConstantExpressionValidationVisitor('8.5'));
        $traverser->addVisitor(new RuntimeAttributeFactoryLowering('test.php'));
        $statements = $traverser->traverse($statements);
        $class = $statements[0];
        self::assertInstanceOf(Node\Stmt\Class_::class, $class);
        $value = $class->attrGroups[0]->attrs[0]->args[0]->value;

        self::assertNotSame('', $value->getAttribute(RuntimeAttributeFactoryLowering::FACTORY_NAME_ATTRIBUTE, ''));
        self::assertTrue($value->getAttribute(RuntimeAttributeFactoryLowering::FACTORY_LAZY_VALUE_ATTRIBUTE, false));
        self::assertInstanceOf(Node\Stmt\Function_::class, $statements[1]);
    }

    public static function runtimeFactoryExpressionProvider(): iterable
    {
        yield 'new' => ['new Value()'];
        yield 'object cast' => ['(object) ["value" => 1]'];
        yield 'static closure' => ['static function (): int { return 1; }'];
        yield 'function callable' => ['strlen(...)'];
        yield 'static method callable' => ['Value::make(...)'];
    }

    /** @dataProvider validDeclarationContextProvider */
    public function testDeclarationContextsMatchPhpAllowDynamicRules(string $code, string $phpVersion): void
    {
        $this->validateCode($code, $phpVersion);
        $this->addToAssertionCount(1);
    }

    public static function validDeclarationContextProvider(): iterable
    {
        yield 'parameter default allows new' => ['function f($value = new Value()) {}', '8.4'];
        yield 'global const allows new' => ['const VALUE = new Value();', '8.4'];
        yield 'static variable allows new' => ['function f() { static $value = new Value(); }', '8.4'];
        yield 'PHP 8.4 static variable allows dynamic initializer' => [
            'function f(int $seed) { static $value = loadValue($seed); }',
            '8.4',
        ];
        yield 'PHP 8.5 property allows scalar cast' => ['class C { public int $value = (int) 1.5; }', '8.5'];
    }

    /** @dataProvider invalidDeclarationContextProvider */
    public function testDeclarationContextsRejectDisallowedExpressions(
        string $code,
        string $phpVersion,
        string $message,
    ): void {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage($message);
        $this->validateCode($code, $phpVersion);
    }

    public static function invalidDeclarationContextProvider(): iterable
    {
        yield 'class constant rejects new' => [
            'class C { const VALUE = new Value(); }',
            '8.4',
            'New expressions are not supported in this context',
        ];
        yield 'property rejects new' => [
            'class C { public mixed $value = new Value(); }',
            '8.4',
            'New expressions are not supported in this context',
        ];
        yield 'enum case rejects new' => [
            'enum E: int { case Value = new Value(); }',
            '8.4',
            'New expressions are not supported in this context',
        ];
        yield 'class constant rejects ordinary call' => [
            'class C { const VALUE = loadValue(); }',
            '8.5',
            'Constant expression contains invalid operations',
        ];
        yield 'property rejects ordinary call' => [
            'class C { public mixed $value = loadValue(); }',
            '8.5',
            'Constant expression contains invalid operations',
        ];
        yield 'property rejects PHP 8.5 object cast' => [
            'class C { public mixed $value = (object) []; }',
            '8.5',
            'Object casts are not supported in this context',
        ];
        yield 'class constant rejects closure before PHP 8.5' => [
            'class C { const VALUE = static function (): int { return 1; }; }',
            '8.4',
            'Constant expression contains invalid operations',
        ];
        yield 'PHP 8.5 global constant closure is not supported by TypePHP' => [
            'const VALUE = static function (): int { return 1; };',
            '8.5',
            'Closures in constant declarations are not supported by TypePHP',
        ];
        yield 'PHP 8.5 class constant closure is not supported by TypePHP' => [
            'class C { const VALUE = static function (): int { return 1; }; }',
            '8.5',
            'Closures in constant declarations are not supported by TypePHP',
        ];
        yield 'PHP 8.5 nested class constant closure is not supported by TypePHP' => [
            'class C { const VALUE = [static function (): int { return 1; }]; }',
            '8.5',
            'Closures in constant declarations are not supported by TypePHP',
        ];
        yield 'PHP 8.5 parameter default closure is not supported by TypePHP' => [
            'function f(Closure $value = static function (): int { return 1; }) {}',
            '8.5',
            'Closures in parameter default values are not supported by TypePHP',
        ];
        yield 'PHP 8.5 property default closure is not supported by TypePHP' => [
            'class C { public Closure $value = static function (): int { return 1; }; }',
            '8.5',
            'Closures in property default values are not supported by TypePHP',
        ];
        yield 'PHP 8.5 nested parameter default closure is not supported by TypePHP' => [
            'function f(mixed $value = [static function (): int { return 1; }]) {}',
            '8.5',
            'Closures in parameter default values are not supported by TypePHP',
        ];
        yield 'PHP 8.5 nested property default closure is not supported by TypePHP' => [
            'class C { public mixed $value = [static function (): int { return 1; }]; }',
            '8.5',
            'Closures in property default values are not supported by TypePHP',
        ];
    }

    public function testExposesPhpStyleNodeWhitelist(): void
    {
        $php84 = new ConstantExpressionValidator('8.4');
        $php85 = new ConstantExpressionValidator('8.5');

        self::assertTrue($php84->isAllowedInConstantExpression(
            $this->parseAttributeExpression('new Value()', '8.4'),
        ));
        self::assertFalse($php84->isAllowedInConstantExpression(
            $this->parseAttributeExpression('strlen(...)', '8.4'),
        ));
        self::assertTrue($php85->isAllowedInConstantExpression(
            $this->parseAttributeExpression('strlen(...)', '8.5'),
        ));
        self::assertFalse($php85->isAllowedInConstantExpression(
            $this->parseAttributeExpression('"value" |> strlen(...)', '8.5'),
        ));
    }

    public function testCompilerBoundaryRoutesUnsupportedSyntaxThroughFatalDiagnostic(): void
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromString('8.5'));
        $statements = $parser->parse("<?php\nconst VALUE = loadValue();");
        self::assertNotNull($statements);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ConstantExpressionValidationVisitor(
            '8.5',
            static function (Node $node, string $message): never {
                throw new \RuntimeException("fatal: {$message} at line {$node->getStartLine()}");
            },
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'fatal: Constant expression contains invalid operations at line 2',
        );
        $traverser->traverse($statements);
    }

    private function parseAttributeExpression(string $expression, string $phpVersion): Node\Expr
    {
        return $this->parseAttributeArguments($expression, $phpVersion)[0]->value;
    }

    /** @return list<Node\Arg> */
    private function parseAttributeArguments(string $arguments, string $phpVersion): array
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromString($phpVersion));
        $statements = $parser->parse("<?php #[Test({$arguments})] class Target {}");
        self::assertNotNull($statements);
        $class = $statements[0];
        self::assertInstanceOf(Node\Stmt\Class_::class, $class);

        return $class->attrGroups[0]->attrs[0]->args;
    }

    private function validateCode(string $code, string $phpVersion): void
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromString($phpVersion));
        $statements = $parser->parse("<?php {$code}");
        self::assertNotNull($statements);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ConstantExpressionValidationVisitor($phpVersion));
        $traverser->traverse($statements);
    }
}

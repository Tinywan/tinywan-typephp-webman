<?php

namespace TypePhp\Tests\Analysis;

use PhpParser\Node;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TypePhp\Analysis\NativeObjectStackPromotionAnalyzer;

final class NativeObjectStackPromotionAnalyzerTest extends TestCase
{
    private function analyze(string $body, array $unsafeMethods = []): array
    {
        $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse(
            "<?php function test(): void {\n{$body}\n}",
        );
        self::assertNotNull($ast);
        $function = $ast[0];
        self::assertInstanceOf(Node\Stmt\Function_::class, $function);

        $analyzer = new NativeObjectStackPromotionAnalyzer(
            static fn (Node\Expr\New_ $new): ?string => $new->class instanceof Node\Name
                ? $new->class->toString()
                : null,
            static fn (string $class): bool => str_starts_with($class, 'Native'),
            static fn (string $_class, string $method): bool => !in_array($method, $unsafeMethods, true),
        );
        return $analyzer->analyze($function->stmts);
    }

    public function testPromotesPropertyAndSafeMethodReceiver(): void
    {
        $result = $this->analyze(<<<'PHP'
$point = new NativePoint();
$point->x = 21;
echo $point->x * 2;
$point->touch();
PHP);

        self::assertSame(['point'], array_keys($result));
        self::assertSame('NativePoint', $result['point']['class']);
    }

    public function testRejectsAliasReturnArgumentAndUnsafeMethod(): void
    {
        self::assertSame([], $this->analyze('$value = new NativeValue(); $alias = $value;'));
        self::assertSame([], $this->analyze('$value = new NativeValue(); consume($value);'));
        self::assertSame([], $this->analyze('$value = new NativeValue(); return $value;'));
        self::assertSame([], $this->analyze(
            '$value = new NativeValue(); $value->publish();',
            ['publish'],
        ));
    }

    public function testRejectsRepeatedOrReassignedAllocation(): void
    {
        self::assertSame([], $this->analyze('for ($i = 0; $i < 2; $i++) { $value = new NativeValue(); }'));
        self::assertSame([], $this->analyze('$value = new NativeValue(); $value = null;'));
    }

    public function testRejectsPropertyAddressEscape(): void
    {
        self::assertSame([], $this->analyze('$value = new NativeValue(); consume($value->field);'));
        self::assertSame([], $this->analyze('$value = new NativeValue(); $ref =& $value->field;'));
    }
}

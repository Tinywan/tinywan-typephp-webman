<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\VariadicPlaceholder;

class AstNodeTypeTest extends TestCase
{
    private CompilerTest $compiler;
    private \ReflectionClass $ref;
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/ast_node_type_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->compiler = CompilerTest::create($this->tmpDir);
        $this->ref = new \ReflectionClass($this->compiler);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tmpDir)) {
            $this->removeDirectory($this->tmpDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function invoke(string $method, ...$args): mixed
    {
        $m = $this->ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke($this->compiler, ...$args);
    }

    // ========================================================================
    // isArrayDimFetch
    // ========================================================================

    public function testIsArrayDimFetch(): void
    {
        $var = new Expr\Variable('arr');
        $this->assertTrue($this->invoke('isArrayDimFetch', new Expr\ArrayDimFetch($var)));
        $this->assertFalse($this->invoke('isArrayDimFetch', $var));
    }

    // ========================================================================
    // isVarExpr
    // ========================================================================

    public function testIsVarExpr(): void
    {
        $this->assertTrue($this->invoke('isVarExpr', new Expr\Variable('foo')));
        $this->assertFalse($this->invoke('isVarExpr', new Node\Scalar\Int_(42)));
    }

    // ========================================================================
    // isIdExpr
    // ========================================================================

    public function testIsIdExpr(): void
    {
        $this->assertTrue($this->invoke('isIdExpr', new Node\Identifier('foo')));
        $this->assertFalse($this->invoke('isIdExpr', new Expr\Variable('foo')));
    }

    // ========================================================================
    // isPropertyFetch
    // ========================================================================

    public function testIsPropertyFetch(): void
    {
        $obj = new Expr\Variable('obj');
        $this->assertTrue($this->invoke('isPropertyFetch', new Expr\PropertyFetch($obj, 'prop')));
        $this->assertFalse($this->invoke('isPropertyFetch', $obj));
    }

    // ========================================================================
    // isStaticPropertyFetch
    // ========================================================================

    public function testIsStaticPropertyFetch(): void
    {
        $class = new Node\Name('Foo');
        $this->assertTrue($this->invoke('isStaticPropertyFetch', new Expr\StaticPropertyFetch($class, 'prop')));
        $this->assertFalse($this->invoke('isStaticPropertyFetch', new Expr\Variable('a')));
    }

    // ========================================================================
    // isClassConstFetch
    // ========================================================================

    public function testIsClassConstFetch(): void
    {
        $class = new Node\Name('Foo');
        $this->assertTrue($this->invoke('isClassConstFetch', new Expr\ClassConstFetch($class, 'BAR')));
        $this->assertFalse($this->invoke('isClassConstFetch', new Expr\Variable('a')));
    }

    // ========================================================================
    // isNewExpr
    // ========================================================================

    public function testIsNewExpr(): void
    {
        $class = new Node\Name('Foo');
        $this->assertTrue($this->invoke('isNewExpr', new Expr\New_($class)));
        $this->assertFalse($this->invoke('isNewExpr', new Expr\Variable('a')));
    }

    // ========================================================================
    // isNameExpr
    // ========================================================================

    public function testIsNameExpr(): void
    {
        $this->assertTrue($this->invoke('isNameExpr', new Node\Name('Foo')));
        $this->assertFalse($this->invoke('isNameExpr', new Expr\Variable('Foo')));
    }

    // ========================================================================
    // isFullNameExpr
    // ========================================================================

    public function testIsFullNameExpr(): void
    {
        $this->assertTrue($this->invoke('isFullNameExpr', new Node\Name\FullyQualified('Foo\\Bar')));
        $this->assertFalse($this->invoke('isFullNameExpr', new Node\Name('Foo')));
    }

    // ========================================================================
    // isNamedMethod
    // ========================================================================

    public function testIsNamedMethod(): void
    {
        $this->assertTrue($this->invoke('isNamedMethod', new Node\Identifier('methodName')));
        $this->assertFalse($this->invoke('isNamedMethod', new Expr\Variable('a')));
    }

    // ========================================================================
    // isScalarString
    // ========================================================================

    public function testIsScalarString(): void
    {
        $this->assertTrue($this->invoke('isScalarString', new Node\Scalar\String_('hello')));
        $this->assertFalse($this->invoke('isScalarString', new Node\Scalar\Int_(1)));
    }

    // ========================================================================
    // isFuncCallExpr
    // ========================================================================

    public function testIsFuncCallExpr(): void
    {
        $name = new Node\Name('foo');
        $this->assertTrue($this->invoke('isFuncCallExpr', new Expr\FuncCall($name)));
        $this->assertFalse($this->invoke('isFuncCallExpr', new Expr\Variable('a')));
    }

    // ========================================================================
    // isRefvalCall
    // ========================================================================

    public function testIsRefvalCall(): void
    {
        $refvalCall = new Expr\FuncCall(new Node\Name('refval'));
        $this->assertTrue($this->invoke('isRefvalCall', $refvalCall));

        $otherCall = new Expr\FuncCall(new Node\Name('other'));
        $this->assertFalse($this->invoke('isRefvalCall', $otherCall));

        $this->assertFalse($this->invoke('isRefvalCall', new Expr\Variable('a')));
    }

    // ========================================================================
    // isMethodCall
    // ========================================================================

    public function testIsMethodCall(): void
    {
        $obj = new Expr\Variable('obj');
        $this->assertTrue($this->invoke('isMethodCall', new Expr\MethodCall($obj, 'method')));
        $this->assertFalse($this->invoke('isMethodCall', $obj));
    }

    // ========================================================================
    // isStaticCall
    // ========================================================================

    public function testIsStaticCall(): void
    {
        $class = new Node\Name('Foo');
        $this->assertTrue($this->invoke('isStaticCall', new Expr\StaticCall($class, 'method')));
        $this->assertFalse($this->invoke('isStaticCall', new Expr\Variable('a')));
    }

    // ========================================================================
    // isScalar / isScalarInt / isScalarBool
    // ========================================================================

    public function testIsScalar(): void
    {
        $this->assertTrue($this->invoke('isScalar', new Node\Scalar\Int_(1)));
        $this->assertTrue($this->invoke('isScalar', new Node\Scalar\String_('s')));
        $this->assertTrue($this->invoke('isScalar', new Node\Scalar\Float_(1.0)));
        $this->assertFalse($this->invoke('isScalar', new Expr\Variable('a')));
    }

    public function testIsScalarInt(): void
    {
        $this->assertTrue($this->invoke('isScalarInt', new Node\Scalar\Int_(42)));
        $this->assertFalse($this->invoke('isScalarInt', new Node\Scalar\String_('42')));
    }

    public function testIsScalarBoolTrue(): void
    {
        $trueConst = new Expr\ConstFetch(new Node\Name('true'));
        $this->assertTrue($this->invoke('isScalarBool', $trueConst));
        $this->assertEquals('php::true_', $this->invoke('getBoolValue', $trueConst));
    }

    public function testIsScalarBoolFalse(): void
    {
        $falseConst = new Expr\ConstFetch(new Node\Name('false'));
        $this->assertTrue($this->invoke('isScalarBool', $falseConst));
        $this->assertEquals('php::false_', $this->invoke('getBoolValue', $falseConst));
    }

    public function testIsScalarBoolNotBool(): void
    {
        $nullConst = new Expr\ConstFetch(new Node\Name('null'));
        $this->assertFalse($this->invoke('isScalarBool', $nullConst));
    }

    // ========================================================================
    // isMatchExpr
    // ========================================================================

    public function testIsMatchExpr(): void
    {
        $cond = new Expr\Variable('x');
        $this->assertTrue($this->invoke('isMatchExpr', new Expr\Match_($cond)));
        $this->assertFalse($this->invoke('isMatchExpr', $cond));
    }

    public function testIsAssignExpr(): void
    {
        $var = new Expr\Variable('a');
        $val = new Node\Scalar\Int_(1);
        $this->assertTrue($this->invoke('isAssignExpr', new Expr\Assign($var, $val)));
        $this->assertFalse($this->invoke('isAssignExpr', new Expr\AssignOp\Plus($var, $val)));
    }

    // ========================================================================
    // isCallExpr
    // ========================================================================

    public function testIsCallExpr(): void
    {
        $name = new Node\Name('foo');
        $obj = new Expr\Variable('obj');
        $class = new Node\Name('Foo');

        $this->assertTrue($this->invoke('isCallExpr', new Expr\FuncCall($name)));
        $this->assertTrue($this->invoke('isCallExpr', new Expr\MethodCall($obj, 'bar')));
        $this->assertTrue($this->invoke('isCallExpr', new Expr\StaticCall($class, 'baz')));
        $this->assertFalse($this->invoke('isCallExpr', $obj));
    }

    // ========================================================================
    // isPlaceholderExpr
    // ========================================================================

    public function testIsPlaceholderExpr(): void
    {
        $this->assertTrue($this->invoke('isPlaceholderExpr', new VariadicPlaceholder()));
        $this->assertFalse($this->invoke('isPlaceholderExpr', new Expr\Variable('a')));
    }

    // ========================================================================
    // isReturnExpr
    // ========================================================================

    public function testIsReturnExpr(): void
    {
        $this->assertTrue($this->invoke('isReturnExpr', new Node\Stmt\Return_(new Node\Scalar\Int_(1))));
        $this->assertFalse($this->invoke('isReturnExpr', new Expr\Variable('a')));
    }

    // ========================================================================
    // isBreakExpr
    // ========================================================================

    public function testIsBreakExpr(): void
    {
        $this->assertTrue($this->invoke('isBreakExpr', new Node\Stmt\Break_()));
        $this->assertFalse($this->invoke('isBreakExpr', new Expr\Variable('a')));
    }

    // ========================================================================
    // isThrowExpr
    // ========================================================================

    public function testIsThrowExprDirect(): void
    {
        $this->assertTrue($this->invoke('isThrowExpr', new Expr\Throw_(new Expr\Variable('e'))));
    }

    public function testIsThrowExprWrapped(): void
    {
        $throwExpr = new Expr\Throw_(new Expr\Variable('e'));
        $wrapped = new Node\Stmt\Expression($throwExpr);
        $this->assertTrue($this->invoke('isThrowExpr', $wrapped));
    }

    public function testIsThrowExprNotThrow(): void
    {
        $this->assertFalse($this->invoke('isThrowExpr', new Expr\Variable('a')));
    }

    // ========================================================================
    // isExitExpr
    // ========================================================================

    public function testIsExitExprDirect(): void
    {
        $this->assertTrue($this->invoke('isExitExpr', new Expr\Exit_()));
    }

    public function testIsExitExprWrapped(): void
    {
        $exitExpr = new Expr\Exit_();
        $wrapped = new Node\Stmt\Expression($exitExpr);
        $this->assertTrue($this->invoke('isExitExpr', $wrapped));
    }

    public function testIsExitExprNotExit(): void
    {
        $this->assertFalse($this->invoke('isExitExpr', new Expr\Variable('a')));
    }

    // ========================================================================
    // isEmptyArray
    // ========================================================================

    public function testIsEmptyArray(): void
    {
        $this->assertTrue($this->invoke('isEmptyArray', new Expr\Array_([])));
        $this->assertFalse($this->invoke('isEmptyArray', new Expr\Array_([new Node\ArrayItem(new Node\Scalar\Int_(1))])));
    }

    public function testIsEmptyArrayNotArray(): void
    {
        $this->assertFalse($this->invoke('isEmptyArray', new Expr\Variable('a')));
    }

    // ========================================================================
    // isNull
    // ========================================================================

    public function testIsNull(): void
    {
        $this->assertTrue($this->invoke('isNull', new Expr\ConstFetch(new Node\Name('null'))));
        $this->assertFalse($this->invoke('isNull', new Expr\ConstFetch(new Node\Name('true'))));
        $this->assertFalse($this->invoke('isNull', new Expr\Variable('a')));
    }

    // ========================================================================
    // Cross-type verification: each is* is false for unrelated types
    // ========================================================================

    public function testNoCrossFalsePositives(): void
    {
        $var = new Expr\Variable('x');

        $methods = [
            'isArrayDimFetch', 'isPropertyFetch', 'isStaticPropertyFetch',
            'isClassConstFetch', 'isNewExpr', 'isNameExpr', 'isFullNameExpr',
            'isFuncCallExpr', 'isRefvalCall', 'isMethodCall', 'isStaticCall',
            'isMatchExpr', 'isAssignExpr',
            'isCallExpr', 'isPlaceholderExpr', 'isReturnExpr', 'isBreakExpr',
            'isThrowExpr', 'isExitExpr', 'isEmptyArray', 'isNull',
        ];

        foreach ($methods as $method) {
            $this->assertFalse(
                $this->invoke($method, $var),
                "{$method} should return false for a plain Variable"
            );
        }
    }
}

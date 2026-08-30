<?php

use TypePhp\Exception\TestError;

class ConstructorVisibilityTest extends BaseTest
{
    /**
     * 编译期错误在转换阶段直接以 TestError 抛出，而在桩文件生成阶段
     * (gen_stub.php) 会被包成 RuntimeException，这里两者都要捕获。
     */
    protected function exec(string $expected, string $file): void
    {
        try {
            $this->compile($file);
        } catch (TestError|RuntimeException $exception) {
            $this->assertStringContainsString($expected, $exception->getMessage());
            return;
        }
        $this->fail('Expected compile-time error was not thrown');
    }

    public function testPrivateConstructorCannotBeCalledFromOutside(): void
    {
        // 私有构造器不能从类外部通过 `new` 调用
        $this->exec('Cannot call private TestClass::__construct()', 'constructor_visibility_private.php');
    }

    public function testProtectedConstructorCannotBeCalledFromGlobalScope(): void
    {
        // 保护构造器不能从全局作用域调用
        $this->exec('Cannot call protected TestClass::__construct()', 'constructor_visibility_protected.php');
    }

    public function testProtectedConstructorCannotBeCalledFromNonSubclass(): void
    {
        // 保护构造器不能从非子类的其它类内部调用
        $this->exec('Cannot call protected Base::__construct()', 'constructor_visibility_protected_foreign_class.php');
    }

    public function testInheritedPrivateConstructorCannotBeCalledFromChildScope(): void
    {
        $this->exec(
            'Cannot call private PrivateConstructorParent::__construct()',
            'constructor_visibility_inherited_private.php'
        );
    }

    public function testInternalPrivateConstructorCannotBeCalled(): void
    {
        $this->exec('Cannot call private Closure::__construct()', 'constructor_visibility_internal_private.php');
    }

    public function testNamespacedConstructorUsesPhpClassNameInDiagnostic(): void
    {
        $this->exec(
            'Cannot call private ConstructorVisibility\Hidden::__construct()',
            'constructor_visibility_namespaced.php'
        );
    }

    public function testTraitPrivateConstructorCannotBeCalledFromGlobalScope(): void
    {
        // trait 提供的私有构造器扁平化后等价于类的私有构造器
        $this->exec('Cannot call private TestClass::__construct()', 'trait_constructor_private.php');
    }

    public function testTraitProtectedConstructorCannotBeCalledFromGlobalScope(): void
    {
        // trait 提供的保护构造器扁平化后等价于类的保护构造器
        $this->exec('Cannot call protected TestClass::__construct()', 'trait_constructor_protected.php');
    }

    public function testConflictingTraitConstructorMustBeResolved(): void
    {
        // 两个 trait 各自声明 __construct 时必须显式解决冲突
        $this->exec('Trait `TraitB` method `__construct` already exists', 'trait_constructor_conflict.php');
    }
}

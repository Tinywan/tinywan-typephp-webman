<?php

use TypePhp\CompilerTest;

class ClassMethodOverrideTest extends \BaseTest
{
    public function testChildBeforeParentOverride(): void
    {
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $testFile = __DIR__ . '/../code/class-method-override-order.php';
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);

        $ref = new ReflectionClass($compiler);
        $prop = $ref->getProperty('classMethodOverride');
        $classMethodOverride = $prop->getValue($compiler);

        $parentMethodLower = strtolower('ParentBase::bar');
        $childMethodLower = strtolower('ChildOverride::bar');

        $this->assertArrayHasKey($parentMethodLower, $classMethodOverride, 'ParentBase::bar should be registered');
        $this->assertArrayHasKey($childMethodLower, $classMethodOverride, 'ChildOverride::bar should be registered');
        // 父类方法应被标记为已被子类覆盖
        $this->assertTrue($classMethodOverride[$parentMethodLower], 'ParentBase::bar should be marked as overridden by child');
        // 子类方法未被覆盖
        $this->assertFalse($classMethodOverride[$childMethodLower], 'ChildOverride::bar should not be marked as overridden');
    }

    public function testNamespacedOverrideKeysUseFullClassName(): void
    {
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $testFile = __DIR__ . '/../code/class-method-override-namespace.php';
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);

        $ref = new ReflectionClass($compiler);
        $prop = $ref->getProperty('classMethodOverride');
        $classMethodOverride = $prop->getValue($compiler);

        $parentMethodLower = strtolower('Demo\\Dispatch\\ParentBaseNs::bar');
        $childMethodLower = strtolower('Demo\\Dispatch\\ChildOverrideNs::bar');

        $this->assertArrayHasKey($parentMethodLower, $classMethodOverride, 'Namespaced parent method should be registered with full class name');
        $this->assertArrayHasKey($childMethodLower, $classMethodOverride, 'Namespaced child method should be registered with full class name');
        $this->assertTrue($classMethodOverride[$parentMethodLower], 'Namespaced parent method should be marked as overridden');
        $this->assertFalse($classMethodOverride[$childMethodLower], 'Namespaced child method should not be marked as overridden');
    }
}

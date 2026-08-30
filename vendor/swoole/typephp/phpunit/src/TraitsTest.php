<?php

namespace TypePhp\Tests;

use TypePhp\Type;

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;
use TypePhp\Entity\ArgInfo;

class TraitsTest extends TestCase
{
    private string $testDir;
    private CompilerTest $compiler;
    private \ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . '/traits_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
        $this->compiler = CompilerTest::create($this->testDir);
        $this->ref = new \ReflectionClass($this->compiler);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
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
    // MagicMethodDetector::checkArgType
    // ========================================================================

    public function testCheckArgTypeExactMatch(): void
    {
        $this->assertTrue($this->invoke('checkArgType', 'php::Int', 'php::Int'));
        $this->assertTrue($this->invoke('checkArgType', 'php::Str', 'php::Str'));
        $this->assertTrue($this->invoke('checkArgType', 'php::Array', 'php::Array'));
    }

    public function testCheckArgTypeMismatch(): void
    {
        $this->assertFalse($this->invoke('checkArgType', 'php::Int', 'php::Str'));
        $this->assertFalse($this->invoke('checkArgType', 'php::Float', 'php::Int'));
    }

    public function testCheckArgTypeVarMatchesAny(): void
    {
        // TYPE_VAR matches any expected type when canBeVar is true (default)
        $this->assertTrue($this->invoke('checkArgType', 'php::Var', 'php::Int'));
        $this->assertTrue($this->invoke('checkArgType', 'php::Var', 'php::Str'));
        $this->assertTrue($this->invoke('checkArgType', 'php::Var', 'php::Array'));
    }

    public function testCheckArgTypeVarDoesNotMatchWhenCannotBeVar(): void
    {
        // TYPE_VAR does NOT match when canBeVar is false
        $this->assertFalse($this->invoke('checkArgType', 'php::Var', 'php::Str', false));
        $this->assertFalse($this->invoke('checkArgType', 'php::Var', 'php::Int', false));
    }

    public function testCheckArgTypeExactMatchWithCannotBeVar(): void
    {
        $this->assertTrue($this->invoke('checkArgType', 'php::Str', 'php::Str', false));
        $this->assertTrue($this->invoke('checkArgType', 'php::Array', 'php::Array', false));
    }

    public function testCheckArgTypeVoid(): void
    {
        $this->assertTrue($this->invoke('checkArgType', 'void', 'void'));
        $this->assertFalse($this->invoke('checkArgType', 'void', 'php::Str'));
    }

    // ========================================================================
    // StdContainerParser::isStdContainerType
    // ========================================================================

    public function testIsStdContainerTypeTrue(): void
    {
        $this->assertTrue($this->invoke('isStdContainerType', Type::STD_ARRAY));
        $this->assertTrue($this->invoke('isStdContainerType', Type::STD_VECTOR));
        $this->assertTrue($this->invoke('isStdContainerType', Type::STD_MAP));
        $this->assertTrue($this->invoke('isStdContainerType', Type::STD_ORDERED_MAP));
    }

    public function testIsStdContainerTypeFalse(): void
    {
        $this->assertFalse($this->invoke('isStdContainerType', Type::ARRAY));
        $this->assertFalse($this->invoke('isStdContainerType', Type::INT));
        $this->assertFalse($this->invoke('isStdContainerType', Type::VAR));
        $this->assertFalse($this->invoke('isStdContainerType', Type::OBJECT));
    }

    // ========================================================================
    // StdContainerParser::getStdTypeKey
    // ========================================================================

    public function testGetStdTypeKeyBasic(): void
    {
        $info = [
            'kind' => 'vector',
            'decl' => 'php::StdVector<php::Int>',
            'type' => 'php::Int',
            'class' => '',
        ];
        $key = $this->invoke('getStdTypeKey', $info);
        $this->assertStringContainsString('kind=vector', $key);
        $this->assertStringContainsString('decl=php::StdVector<php::Int>', $key);
        $this->assertStringContainsString('type=php::Int', $key);
        $this->assertStringContainsString('class=', $key);
    }

    public function testGetStdTypeKeyWithClass(): void
    {
        $info = [
            'kind' => 'vector',
            'decl' => 'php::StdVector<php::Object>',
            'type' => 'php::Object',
            'class' => 'App\\Entity\\User',
        ];
        $key = $this->invoke('getStdTypeKey', $info);
        $this->assertStringContainsString('class=App\\Entity\\User', $key);
    }

    public function testGetStdTypeKeyWithKeyType(): void
    {
        $info = [
            'kind' => 'map',
            'decl' => 'php::StdMap<php::Str, php::Int>',
            'type' => 'php::Int',
            'class' => '',
            'keyType' => 'php::Str',
        ];
        $key = $this->invoke('getStdTypeKey', $info);
        $this->assertStringContainsString('keyType=php::Str', $key);
    }

    public function testGetStdTypeKeyWithoutKeyType(): void
    {
        $info = [
            'kind' => 'array',
            'decl' => 'php::StdArray<php::Int, 10>',
            'type' => 'php::Int',
            'class' => '',
        ];
        $key = $this->invoke('getStdTypeKey', $info);
        $this->assertStringNotContainsString('keyType', $key);
    }

    public function testGetStdTypeKeyOrderedMap(): void
    {
        $info = [
            'kind' => 'ordered_map',
            'decl' => 'php::StdOrderedMap<php::Str, php::Int>',
            'type' => 'php::Int',
            'class' => '',
            'keyType' => 'php::Str',
        ];
        $key = $this->invoke('getStdTypeKey', $info);
        $this->assertStringContainsString('kind=ordered_map', $key);
    }

    // ========================================================================
    // PropertyPromotion::genPropertyPromotion
    // ========================================================================

    public function testGenPropertyPromotion(): void
    {
        // Need context initialized for genCharPtr
        $this->invoke('resetFunction');

        $argInfo = new ArgInfo();
        $argInfo->name = 'title';
        $argInfo->type = 'php::Str';

        $result = $this->invoke('genPropertyPromotion', $argInfo);
        $this->assertStringContainsString('this_.setProperty', $result);
        $this->assertStringContainsString('title', $result);
    }

    public function testGenPropertyPromotionWithNumber(): void
    {
        $this->invoke('resetFunction');

        $argInfo = new ArgInfo();
        $argInfo->name = 'count';
        $argInfo->type = 'php::Int';

        $result = $this->invoke('genPropertyPromotion', $argInfo);
        $this->assertStringContainsString('count', $result);
    }

    // ========================================================================
    // ClosureGenerator::genUserCodeCallableScopeGuard
    // ========================================================================

    public function testGenUnpackedCallbackScopeGuard(): void
    {
        $this->invoke('resetFunction');

        $result = $this->invoke('genUserCodeCallableScopeGuard');
        $this->assertStringContainsString('php::UserCodeScopeGuard', $result);
        $this->assertStringContainsString('php::CallableScope(nullptr, nullptr, nullptr)', $result);
        $this->assertStringNotContainsString('ON_SCOPE_EXIT', $result);
    }

    public function testGenUnpackedCallbackScopeGuardUsesOneRaiiObject(): void
    {
        $this->invoke('resetFunction');

        $result = $this->invoke('genUserCodeCallableScopeGuard');
        $this->assertMatchesRegularExpression(
            '/php::UserCodeScopeGuard tmp_var_\d+\{php::CallableScope\(nullptr, nullptr, nullptr\)\};/',
            $result,
        );
    }

    // ========================================================================
    // StdContainerParser::getStdArrayDecl
    // ========================================================================

    public function testGetStdArrayDecl(): void
    {
        $result = $this->invoke('getStdArrayDecl', 'php::Int', [10]);
        $this->assertEquals('php::StdArray<php::Int, 10>', $result);
    }

    public function testGetStdArrayDeclNested(): void
    {
        $result = $this->invoke('getStdArrayDecl', 'php::Int', [3, 4]);
        $this->assertEquals('php::StdArray<php::StdArray<php::Int, 4>, 3>', $result);
    }

    public function testGetStdArrayDeclFloat(): void
    {
        $result = $this->invoke('getStdArrayDecl', 'php::Float', [5]);
        $this->assertEquals('php::StdArray<php::Float, 5>', $result);
    }

    // ========================================================================
    // StdContainerParser::getStdMapDecl
    // ========================================================================

    public function testGetStdMapDecl(): void
    {
        $result = $this->invoke('getStdMapDecl', 'php::StdMap', 'php::Str', 'php::Int');
        $this->assertEquals('php::StdMap<php::Str, php::Int>', $result);
    }

    public function testGetStdMapDeclOrdered(): void
    {
        $result = $this->invoke('getStdMapDecl', 'php::StdOrderedMap', 'php::Int', 'php::Str');
        $this->assertEquals('php::StdOrderedMap<php::Int, php::Str>', $result);
    }

    // ========================================================================
    // StdContainerParser::getStdValueTypeBytes
    // ========================================================================

    public function testGetStdValueTypeBytes(): void
    {
        $this->assertGreaterThan(0, $this->invoke('getStdValueTypeBytes', Type::INT));
        $this->assertGreaterThan(0, $this->invoke('getStdValueTypeBytes', Type::FLOAT));
        $this->assertGreaterThan(0, $this->invoke('getStdValueTypeBytes', Type::BOOL));
    }
}

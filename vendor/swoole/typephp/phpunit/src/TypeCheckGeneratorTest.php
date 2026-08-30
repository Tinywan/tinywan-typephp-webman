<?php

use TypePhp\Entity\ArgInfo;
use TypePhp\CompilerTest;
use TypePhp\Entity\ClassDef;
use TypePhp\Entity\FunctionDef;
use TypePhp\Type;

class TypeCheckGeneratorTest extends \PHPUnit\Framework\TestCase
{
    private function setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }

    private function invokeMethod(object $object, string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }

    public function testMethodTypeCheckErrorUsesClassQualifiedCallableName(): void
    {
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $classDef = new ClassDef('Demo', 0, 'Foo\\Bar');
        $functionDef = new FunctionDef('run', 'php::Var', 'Foo\\Bar');
        $argInfo = new ArgInfo();
        $argInfo->name = 'value';
        $argInfo->typeStr = 'int|string';

        $this->setProtectedProperty($compiler, 'noLiteralStrings', true);
        $functionDef->returnTypeCheck = [['kind' => 'isInt'], ['kind' => 'isString']];
        $functionDef->returnTypeStr = 'int|string';

        $this->setProtectedProperty($compiler, 'classDef', $classDef);
        $this->setProtectedProperty($compiler, 'functionDef', $functionDef);

        $callableName = $this->invokeMethod($compiler, 'getTypeCheckCallableName');
        $paramExpr = $this->invokeMethod($compiler, 'genUnionParamTypeErrorExpr', [$argInfo, 'value', '1']);
        $returnCode = $this->invokeMethod($compiler, 'genUnionReturnCheck', ['retval']);

        $this->assertSame('Foo\\Bar\\Demo::run', $callableName);
        $this->assertStringContainsString('php::throwArgumentTypeError(', $paramExpr);
        $this->assertStringContainsString('Foo\\\\Bar\\\\Demo::run', $paramExpr);
        $this->assertStringNotContainsString('must be of type', $paramExpr);
        $this->assertStringContainsString('php::throwReturnTypeError(', $returnCode);
        $this->assertStringContainsString('Foo\\\\Bar\\\\Demo::run', $returnCode);
    }

    public function testFunctionTypeCheckErrorUsesFunctionQualifiedCallableName(): void
    {
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $functionDef = new FunctionDef('run', 'php::Var', 'Foo\\Bar');
        $argInfo = new ArgInfo();
        $argInfo->name = 'value';
        $argInfo->typeStr = 'int|string';

        $this->setProtectedProperty($compiler, 'noLiteralStrings', true);
        $functionDef->returnTypeCheck = [['kind' => 'isInt'], ['kind' => 'isString']];
        $functionDef->returnTypeStr = 'int|string';

        $this->setProtectedProperty($compiler, 'classDef', null);
        $this->setProtectedProperty($compiler, 'functionDef', $functionDef);

        $callableName = $this->invokeMethod($compiler, 'getTypeCheckCallableName');
        $paramExpr = $this->invokeMethod($compiler, 'genUnionParamTypeErrorExpr', [$argInfo, 'value', '1']);
        $returnCode = $this->invokeMethod($compiler, 'genUnionReturnCheck', ['retval']);

        $this->assertSame('Foo\\Bar\\run', $callableName);
        $this->assertStringContainsString('php::throwArgumentTypeError(', $paramExpr);
        $this->assertStringContainsString('Foo\\\\Bar\\\\run', $paramExpr);
        $this->assertStringNotContainsString('must be of type', $paramExpr);
        $this->assertStringContainsString('php::throwReturnTypeError(', $returnCode);
        $this->assertStringContainsString('Foo\\\\Bar\\\\run', $returnCode);
    }

    public function testStrictScalarChecksKeepTheFastCheckAndDelegateColdErrors(): void
    {
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $functionDef = new FunctionDef('run', 'php::Int', 'Foo\\Bar');
        $argInfo = new ArgInfo();
        $argInfo->name = 'value';
        $argInfo->phpName = 'value';
        $argInfo->type = Type::INT;

        $this->setProtectedProperty($compiler, 'classDef', null);
        $this->setProtectedProperty($compiler, 'functionDef', $functionDef);

        $paramCode = $this->invokeMethod(
            $compiler,
            'genStrictScalarParamCheck',
            [$argInfo, 'raw_value', 'Foo\\Bar\\run', '1'],
        );
        $returnCode = $this->invokeMethod($compiler, 'genStrictScalarReturnCheck', ['retval', Type::INT]);

        $this->assertStringContainsString('raw_value.isInt()', $paramCode);
        $this->assertStringContainsString('php::throwArgumentTypeError(', $paramCode);
        $this->assertStringNotContainsString('must be of type', $paramCode);
        $this->assertStringContainsString('retval.isInt()', $returnCode);
        $this->assertStringContainsString('php::throwReturnTypeError(', $returnCode);
        $this->assertStringNotContainsString('must be of type', $returnCode);
    }

    public function testDynamicStrictScalarArgumentsUseInlinePhpxConversions(): void
    {
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $this->setProtectedProperty($compiler, 'noLiteralStrings', true);

        foreach ([
            Type::INT => 'php::toIntArgExact',
            Type::FLOAT => 'php::toFloatArgExact',
            Type::BOOL => 'php::toBoolArgExact',
            Type::STR => 'php::toStringArgExact',
        ] as $type => $helper) {
            $argInfo = new ArgInfo();
            $argInfo->name = 'value';
            $argInfo->phpName = 'value';
            $argInfo->type = $type;

            $expr = $this->invokeMethod(
                $compiler,
                'genStrictScalarArgConversion',
                [$argInfo, 'dynamic_value', 'Foo\\Bar::run', '2'],
            );

            $this->assertSame(
                $helper . '(dynamic_value, php::Str{ZEND_STRL("Foo\\\\Bar::run")}, 2, php::Str{ZEND_STRL("value")})',
                $expr,
            );
            $this->assertStringNotContainsString('[&]', $expr);
        }
    }
}

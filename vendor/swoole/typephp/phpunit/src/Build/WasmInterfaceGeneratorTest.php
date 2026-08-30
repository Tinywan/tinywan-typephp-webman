<?php

namespace TypePhpTest\Build;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypePhp\Build\WasmInterfaceGenerator;
use TypePhp\Entity\ArgInfo;
use TypePhp\Entity\FunctionDef;
use TypePhp\Type;

final class WasmInterfaceGeneratorTest extends TestCase
{
    public function testGeneratesTypedWitAndManifest(): void
    {
        $function = new FunctionDef('greetUser', Type::STR, 'App');
        $function->wasmExport = true;
        $function->displayName = 'App\\greetUser';
        $function->returnTypeStr = 'string';
        $argument = new ArgInfo();
        $argument->name = 'name';
        $argument->phpName = 'name';
        $argument->type = Type::STR;
        $function->argInfoList = [$argument];

        $generator = new WasmInterfaceGenerator();
        $manifest = $generator->buildManifest(
            [$function],
            'acme:demo@1.0.0',
            'demo',
            'demo',
            static fn (): string => 'php_app__greetuser',
        );

        self::assertSame('greet-user', $manifest['functions'][0]['name']);
        self::assertSame('php_app__greetuser', $manifest['functions'][0]['cpp-symbol']);
        self::assertSame('string', $manifest['functions'][0]['parameters'][0]['wit-type']);
        self::assertStringContainsString(
            'greet-user: func(name: string) -> result<string, typephp-error>;',
            $generator->renderWit($manifest),
        );
        $adapter = $generator->renderCppAdapter($manifest);
        self::assertStringContainsString(
            'extern "C" bool exports_acme_demo_api_method_runtime_greet_user(',
            $adapter,
        );
        self::assertStringContainsString('auto result = php_app__greetuser(', $adapter);
        self::assertStringContainsString('catch (zend_object *exception)', $adapter);
        self::assertStringContainsString('TYPEPHP_RUNTIME_INIT(demo)(1, argv)', $adapter);
        self::assertSame('typephp_demo_runtime_init', $manifest['runtime']['init-symbol']);
        self::assertSame(
            "acme:demo/api@1.0.0#create-runtime\n"
            . "acme:demo/api@1.0.0#[method]runtime.greet-user\n",
            $generator->renderJcoAsyncExports($manifest),
        );
    }

    public function testRejectsExportNameCollisions(): void
    {
        $first = new FunctionDef('first', Type::VOID, '');
        $first->wasmExport = true;
        $first->wasmExportName = 'same-name';
        $second = new FunctionDef('second', Type::VOID, '');
        $second->wasmExport = true;
        $second->wasmExportName = 'same-name';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('WasmExport name collision');
        (new WasmInterfaceGenerator())->buildManifest(
            [$first, $second],
            'acme:demo@1.0.0',
            'demo',
            'demo',
            static fn (FunctionDef $function): string => $function->name,
        );
    }

    public function testRejectsUntypedAbi(): void
    {
        $function = new FunctionDef('dynamicValue', Type::VAR, '');
        $function->wasmExport = true;
        $function->returnTypeUndeclared = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must declare a return type');
        (new WasmInterfaceGenerator())->buildManifest(
            [$function],
            'acme:demo@1.0.0',
            'demo',
            'demo',
            static fn (): string => 'php_dynamicvalue',
        );
    }
}

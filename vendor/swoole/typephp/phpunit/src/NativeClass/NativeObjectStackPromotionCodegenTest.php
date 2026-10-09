<?php

namespace TypePhp\Tests\NativeClass;

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;

final class NativeObjectStackPromotionCodegenTest extends TestCase
{
    public function testOnlyNonEscapingAllocationUsesStackSlot(): void
    {
        global $translator;

        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/native-stack-promotion.php';
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $headerFile = $compiler->getIncludeDir() . '/php_native_cpp_class_func_decl.h';
        $compiler->genFunctionDeclarations($headerFile);
        $cppFile = $compiler->convertFile($source);
        $code = file_get_contents($cppFile);
        $header = file_get_contents($headerFile);

        self::assertIsString($code);
        self::assertIsString($header);
        self::assertStringContainsString('class php_nativestackpromotionfixture {', $header);
        self::assertStringContainsString(
            'php_nativestackpromotionfixture(php::Int value);',
            $header,
        );
        self::assertStringContainsString(
            'php::Int read();',
            $header,
        );
        self::assertStringContainsString(
            '~php_nativestackpromotionfixture() noexcept;',
            $header,
        );
        self::assertStringNotContainsString(
            'virtual ~php_nativestackpromotionfixture()',
            $header,
        );
        self::assertStringContainsString(
            '~php_nativestackfinalizerfixture() noexcept(false);',
            $header,
        );
        self::assertStringNotContainsString(
            'virtual ~php_nativestackfinalizerfixture()',
            $header,
        );
        self::assertStringContainsString(
            'virtual ~php_nativestackpromotionbase() noexcept;',
            $header,
        );
        self::assertStringContainsString(
            'virtual ~php_nativestackpromotionchild() noexcept override;',
            $header,
        );
        self::assertStringContainsString('int32_t number = 0;', $header);
        self::assertStringContainsString('int8_t tag = 0;', $header);
        self::assertStringContainsString('uint16_t flags = 0;', $header);
        self::assertStringContainsString('float ratio = 0;', $header);
        self::assertStringContainsString(
            'virtual php::Int __typephp_virtual_php_nativestackpromotionbase__readfrombase() override;',
            $header,
        );
        self::assertStringContainsString(
            'php_nativestackpromotionfixture::php_nativestackpromotionfixture(php::Int value)',
            $code,
        );
        self::assertStringContainsString(
            'php::Int php_nativestackpromotionfixture::read()',
            $code,
        );
        self::assertStringContainsString(
            'php_nativestackfinalizerfixture::~php_nativestackfinalizerfixture() noexcept(false)',
            $code,
        );
        self::assertStringNotContainsString(
            '__typephp_method_',
            $code,
        );
        self::assertSame(1, preg_match(
            '/php::Int php_promotednativeobject\(\) \{(?<body>.*?)\n\}/s',
            $code,
            $promoted,
        ));
        self::assertSame(1, preg_match(
            '/php_nativestackpromotionfixture \* php_escapednativeobject\(\) \{(?<body>.*?)\n\}/s',
            $code,
            $escaped,
        ));
        self::assertSame(1, preg_match(
            '/php::Int php_finalizednativeobject\(\) \{(?<body>.*?)\n\}/s',
            $code,
            $finalized,
        ));
        self::assertSame(1, preg_match(
            '/php::Int php_inheritednativeobject\(\) \{(?<body>.*?)\n\}/s',
            $code,
            $inherited,
        ));
        self::assertStringContainsString('php::NativeStackSlot<', $promoted['body']);
        self::assertStringContainsString('value__native_stack_slot.constructObject(', $promoted['body']);
        self::assertStringNotContainsString(
            'php::NativeRootSlot _native_root_slots[] = {&value};',
            $promoted['body'],
        );
        self::assertStringContainsString('php::nativeConstructObject<', $escaped['body']);
        self::assertStringContainsString(
            'php::NativeRootSlot _native_root_slots[] = {&value};',
            $escaped['body'],
        );
        self::assertStringContainsString('php::nativeConstructObject<', $finalized['body']);
        self::assertStringNotContainsString('php::NativeStackSlot<', $finalized['body']);
        self::assertStringContainsString('php::nativeConstructObject<', $inherited['body']);
        self::assertStringNotContainsString('php::NativeStackSlot<', $inherited['body']);
    }
}

<?php

use TypePhp\CompilerTest;
use TypePhp\Translator;
use function TypePhp\StubGenerator\getTranslator;

final class TranslatorInstanceTest extends BaseTest
{
    public function testLatestTranslatorInstanceIsUsedByStubGenerator(): void
    {
        $first = CompilerTest::create(TYPEPHP_ROOT_PATH);
        self::assertSame($first, Translator::getInstance());
        self::assertSame($first, getTranslator());

        $second = CompilerTest::create(TYPEPHP_ROOT_PATH);
        self::assertSame($second, Translator::getInstance());
        self::assertSame($second, getTranslator());
    }
}

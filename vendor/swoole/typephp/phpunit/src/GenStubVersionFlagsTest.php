<?php

use TypePhp\StubGenerator\EvaluatedValue;
use TypePhp\StubGenerator\VersionFlags;
use const TypePhp\StubGenerator\PHP_70_VERSION_ID;

final class GenStubVersionFlagsTest extends BaseTest
{
    public function testPersistentStringMetadataUsesANonRefcountedInternedZval(): void
    {
        global $translator;
        $translator = \TypePhp\CompilerTest::create(TYPEPHP_ROOT_PATH);

        $value = EvaluatedValue::createFromExpression(
            new PhpParser\Node\Scalar\String_('PHP'),
            null,
            null,
            [],
        );

        $code = $value->initializeZval('property_lang_default_value');

        self::assertStringContainsString('zend_string_init_interned("PHP"', $code);
        self::assertStringContainsString(
            'ZVAL_INTERNED_STR(&property_lang_default_value, property_lang_default_value_str);',
            $code,
        );
    }

    public function testFlagIntroducedBeforeMinimumSupportedVersionRemainsEnabled(): void
    {
        $flags = new VersionFlags(['ZEND_ACC_PRIVATE']);
        $flags->addForVersionsAbove('ZEND_ACC_STATIC', PHP_70_VERSION_ID);

        self::assertSame(
            'ZEND_ACC_PRIVATE|ZEND_ACC_STATIC',
            $flags->generateVersionDependentFlagCode('%s', null),
        );
    }
}

<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

use TypePhp\CompilerTest;

/**
 * @internal
 * @coversNothing
 */
final class TraitCrossFileCallTest extends BaseTest
{
    public function testTraitMethodsAreResolvedBeforeTheConsumingClassFileIsConverted(): void
    {
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $directory = TYPEPHP_ROOT_PATH . '/phpunit/code/trait-cross-file-call/';
        $caller = $directory . 'a-caller.php';
        $classes = $directory . 'z-classes.php';
        $trait = $directory . 'zz-trait.php';

        // The caller is deliberately prepared and converted before the class
        // file. Native method resolution must depend on the complete
        // declaration graph, not source filenames or conversion order.
        $files = [$caller, $classes, $trait];
        $compiler->addFiles($files);
        foreach ($files as $file) {
            $compiler->prepareFile($file);
        }

        $classDef = $compiler->getClassDef('CrossFileTrait\\ClassB');
        self::assertNotNull($classDef);
        self::assertFalse($classDef->hasMethod('getAttribute'));

        // Trait expansion is an explicit declaration phase after all files
        // have been prepared and before any body is converted.
        $compiler->composeTraitDeclarations($files);
        self::assertTrue($classDef->hasMethod('getAttribute'));
        $argument = $classDef->getMethod('getAttribute')->functionDef->argInfoList[0];
        self::assertTrue($argument->hasDefaultValue());
        self::assertSame('', $argument->default);

        $generated = $compiler->convertFile($caller);
        self::assertNotNull($generated);
        $code = file_get_contents($generated);
        self::assertIsString($code);

        self::assertStringContainsString(
            'php_crossfiletrait__classb__getattribute(',
            $code,
        );
        self::assertStringContainsString(
            'php_crossfiletrait__classc__getattribute(',
            $code,
        );
        self::assertStringNotContainsString(
            'php_crossfiletrait__classb____call(',
            $code,
        );
        self::assertStringNotContainsString('.call(', $code);
        self::assertNotSame('', $argument->default);
    }
}

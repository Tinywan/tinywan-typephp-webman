<?php

/** Verifies that traits are AST templates rather than standalone native functions. */
class TraitFuncDeclTest extends \BaseTest
{
    public function testAliasedTraitConstructorIsCompiledOnlyForComposingClasses(): void
    {
        // BaseTest::compile() populates the global $translator and translates the file.
        $this->compile('trait-aliased-constructor-parent-call.php');

        global $translator;
        $compiler = $translator;

        $headerPath = $compiler->getIncludeDir() . '/php_trait_parent_ce_func_decl.h';
        if (file_exists($headerPath)) {
            @unlink($headerPath);
        }
        // Emit the shared function-declaration header (genFunctionDeclarations), which
        // is what the fix targets.
        $compiler->genFunctionDeclarations($headerPath);

        $decl = file_get_contents($headerPath);
        $this->assertDoesNotMatchRegularExpression(
            '/extern void php_tpdodriver____construct\(/',
            $decl,
            'A trait must not have a standalone native function declaration'
        );
        $this->assertMatchesRegularExpression(
            '/extern void php_(?:driver__tpdodriverconstruct|directdriver____construct)\(/',
            $decl,
            'Each composing class must receive its own native method declaration'
        );
        $this->assertStringNotContainsString('trait_parent_ce', $decl);
    }
}

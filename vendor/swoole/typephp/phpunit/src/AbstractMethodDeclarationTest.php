<?php

/**
 * Zend abstract-method declaration rules: an abstract method may not
 * carry a body, and abstract private is only legal inside traits.
 */
class AbstractMethodDeclarationTest extends BaseTest
{
    public function testAbstractMethodCannotContainBody(): void
    {
        $this->exec('Abstract function `Job::run()` cannot contain body', 'abstract_rule_body.php');
    }

    public function testAbstractClassMethodCannotBePrivate(): void
    {
        $this->exec('Abstract function `Job::run()` cannot be declared private', 'abstract_rule_private.php');
    }

    public function testAbstractPrivateTraitMethodIsAllowed(): void
    {
        $this->compile('abstract_rule_private_trait_valid.php');
    }
}

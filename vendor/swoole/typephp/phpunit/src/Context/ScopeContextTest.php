<?php

namespace TypePhp\Tests\Context;

use PHPUnit\Framework\TestCase;
use TypePhp\Context\ScopeContext;

class ScopeContextTest extends TestCase
{
    public function testCanInstantiate(): void
    {
        $scope = new ScopeContext();
        $this->assertInstanceOf(ScopeContext::class, $scope);
    }
}

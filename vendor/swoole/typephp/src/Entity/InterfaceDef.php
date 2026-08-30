<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Entity;

class InterfaceDef extends ClassLikeDef
{
    /**
     * @var array<string, MethodDef>
     */
    public array $methods = [];

    /**
     * @var array<string, ConstantDef>
     */
    public array $constants = [];

    /**
     * Abstract hooked-property contracts, keyed by the case-sensitive property name.
     *
     * @var array<string, InterfacePropertyDef>
     */
    public array $properties = [];

    /**
     * @var string[]
     */
    public array $extendsList = [];

    public function __construct(string $name, string $namespace = '')
    {
        parent::__construct($name, $namespace);
    }

    public function addMethod(MethodDef $method): void
    {
        $this->methods[strtolower($method->name)] = $method;
    }

    public function hasMethod(string $method): bool
    {
        return isset($this->methods[strtolower($method)]);
    }

    public function hasConstant(string $name): bool
    {
        return isset($this->constants[$name]);
    }

    public function hasProperty(string $name): bool
    {
        return isset($this->properties[$name]);
    }
}

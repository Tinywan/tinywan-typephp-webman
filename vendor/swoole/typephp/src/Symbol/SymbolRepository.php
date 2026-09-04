<?php

namespace TypePhp\Symbol;

use TypePhp\Entity\ClassDef;
use TypePhp\Entity\FunctionDef;
use TypePhp\Entity\InterfaceDef;

final class SymbolRepository
{
    /**
     * Stores the definitions of all functions and class methods. The key is the native name:
     * the namespace must be converted to `_` and the result must be lowercase.
     * @var array<string, FunctionDef>
     */
    private array $functions = [];
    /**
     * Keyed by class name, including the namespace.
     * @var array<string, ClassDef>
     */
    private array $classes = [];
    /** @var array<string, InterfaceDef> */
    private array $interfaces = [];
    /** @var array<string, string> */
    private array $parents = [];

    public function putFunction(string $key, FunctionDef $definition): void { $this->functions[$key] = $definition; }
    public function hasFunction(string $key): bool { return array_key_exists($key, $this->functions); }
    public function function(string $key): FunctionDef { return $this->functions[$key]; }
    public function removeFunction(string $key): void { unset($this->functions[$key]); }
    /** @return array<string, FunctionDef> */
    public function functions(): array { return $this->functions; }

    public function putClass(string $key, ClassDef $definition): void { $this->classes[$key] = $definition; }
    public function hasClass(string $key): bool { return array_key_exists($key, $this->classes); }
    public function class(string $key): ClassDef { return $this->classes[$key]; }
    public function findClass(string $key): ?ClassDef { return $this->classes[$key] ?? null; }
    /** @return array<string, ClassDef> */
    public function classes(): array { return $this->classes; }

    public function putInterface(string $key, InterfaceDef $definition): void { $this->interfaces[$key] = $definition; }
    public function hasInterface(string $key): bool { return array_key_exists($key, $this->interfaces); }
    public function interface(string $key): InterfaceDef { return $this->interfaces[$key]; }
    /** @return array<string, InterfaceDef> */
    public function interfaces(): array { return $this->interfaces; }

    public function setParent(string $class, string $parent): void { $this->parents[$class] = $parent; }
    public function parent(string $class): string { return $this->parents[$class] ?? ''; }

    /** @param array<string, FunctionDef> $functions */
    public function replaceFunctions(array $functions): void { $this->functions = $functions; }
    /** @param array<string, ClassDef> $classes */
    public function replaceClasses(array $classes): void { $this->classes = $classes; }
    /** @param array<string, InterfaceDef> $interfaces */
    public function replaceInterfaces(array $interfaces): void { $this->interfaces = $interfaces; }
    /** @param array<string, string> $parents */
    public function replaceParents(array $parents): void { $this->parents = $parents; }
}

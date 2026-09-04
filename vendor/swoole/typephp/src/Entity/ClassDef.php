<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Entity;

use TypePhp\Context\FunctionContext;
use PhpParser\Modifiers;
use PhpParser\Node\Stmt\Trait_;

class ClassDef extends ClassLikeDef
{
    /**
     * @var array<string, MethodDef>
     */
    public array $methods = [];

    /**
     * @var array<string, PropertyDef>
     */
    public array $properties = [];

    /**
     * @var array<string, ConstantDef>
     */
    public array $constants = [];
    public array $implements = [];
    public string $extends = '';
    public bool $requireCtor = false;
    public bool $enum = false;
    /** Compile-time-only class using the Native Object layout instead of zend_object. */
    public bool $nativeObject = false;
    /** Whether this class and its methods are part of the public ABI of a library build. */
    public bool $exported = true;
    public ?string $methodsForTarget = null;
    /** Whether #[Printer] generated this class's own __toString() method. */
    public bool $printerGenerated = false;
    /** @var list<string>|null Explicit fields, or null to include every public instance property. */
    public ?array $printerFields = null;
    /** Whether #[Arrayable] generated this class's own toArray() method. */
    public bool $arrayableGenerated = false;
    /** @var list<string>|null Explicit fields, or null to include every public instance property. */
    public ?array $arrayableFields = null;

    /**
     * Backing type for backed enums ('int' or 'string'), null for pure enums.
     */
    public ?string $enumBackingType = null;

    /**
     * Enum cases: case name => backing value (int/string for backed enums, null for pure enums).
     * @var array<string, int|string|null>
     */
    public array $enumCases = [];

    /**
     * Backing-value ASTs waiting for declaration-expression finalization.
     * These expressions are never emitted as runtime calculations.
     * @var array<string, \PhpParser\Node\Expr>
     */
    public array $enumCaseExpressions = [];
    /**
     * Abstract method name (lowercase) => flags
     * @var array<string, int>
     */
    public array $abstractMethods = [];

    /**
     * Abstract method name (lowercase) => method definition
     * @var array<string, MethodDef>
     */
    public array $abstractMethodDefs = [];
    public ?Trait_ $trait = null;
    /** @var list<string> */
    public array $traitUseNamespaces = [];
    /** @var array<string, string> */
    public array $traitUseAliases = [];
    /** @var array<string, string> */
    public array $traitUseFunctions = [];
    /** @var array<string, string> */
    public array $traitUseConstants = [];
    /** @var list<string> Traits directly used by this class or trait. */
    public array $usedTraits = [];

    /**
     * FullMethodName -> alias list
     * @var array<string, list<array{
     *     group: string,
     *     trait: string|null,
     *     method: string,
     *     newName: string,
     *     newModifier: int
     * }>>
     */
    public array $traitAliases = [];

    /**
     * Excluded FullMethodName -> precedence rules.
     *
     * A list is required here: PHP rejects excluding the same method more
     * than once, so overwriting duplicate rules would hide an invalid
     * declaration before the composition phase can diagnose it.
     *
     * @var array<string, list<array{winnerTrait: string, loserTrait: string, method: string}>>
     */
    public array $traitIgnored = [];
    public int $flags;
    public bool $inheritedFromInternalClass = false;
    public string $ctorInit = '';
    public string $ctorClean = '';
    public FunctionContext $propertyContext;

    public function __construct(string $name, int $flags, string $namespace = '')
    {
        $this->flags = $flags;
        $this->propertyContext = new FunctionContext();
        parent::__construct($name, $namespace);
    }

    public function addMethod(MethodDef $method): void
    {
        $this->methods[strtolower($method->name)] = $method;
    }

    public function addAbstractMethod(string $name, int $flags, ?MethodDef $methodDef = null): void
    {
        $lower = strtolower($name);
        $this->abstractMethods[$lower] = $flags;
        if ($methodDef !== null) {
            $this->abstractMethodDefs[$lower] = $methodDef;
        }
    }

    public function hasMethod(string $method): bool
    {
        return isset($this->methods[strtolower($method)]);
    }

    public function removeMethod(string $method): ?MethodDef
    {
        $name = strtolower($method);
        $methodDef = $this->methods[$name] ?? null;
        unset($this->methods[$name]);
        return $methodDef;
    }

    public function hasAbstractMethod(string $method): bool
    {
        return isset($this->abstractMethods[strtolower($method)]);
    }

    /**
     * Returns method flags for concrete or abstract methods. Returns 0 if not found.
     */
    public function getMethodFlags(string $method): int
    {
        $lower = strtolower($method);
        if (isset($this->methods[$lower])) {
            return $this->methods[$lower]->flags;
        }
        return $this->abstractMethods[$lower] ?? 0;
    }

    public function hasProperty(string $property): bool
    {
        return isset($this->properties[$property]);
    }

    public function hasConstant(string $name): bool
    {
        return isset($this->constants[$name]);
    }

    public function getProperty($property): PropertyDef
    {
        return $this->properties[$property];
    }

    public function getMethod($method): MethodDef
    {
        return $this->methods[strtolower($method)];
    }

    public function getAbstractMethod($method): MethodDef
    {
        return $this->abstractMethodDefs[strtolower($method)];
    }

    public function getConstant($name): ConstantDef
    {
        return $this->constants[$name];
    }

    public function isAbstract(): bool
    {
        return ($this->flags & Modifiers::ABSTRACT) !== 0;
    }
}

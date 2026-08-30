<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Context;

use TypePhp\Analysis\SsaBuilder;

class FunctionContext
{
    /** SSA builder for the current function. Built once per function, discarded with the context. */
    public ?SsaBuilder $ssaBuilder = null;

    /** Map of SSA-stable object variable name => class name (SsaPropOptimizer). */
    public array $stableObjects = [];

    /** Map of hoisted property refs: objName => [propName => true] (SsaPropOptimizer). */
    public array $hoistedProps = [];

    /** Map of properties that must not be hoisted: objName => [propName|'*' => true] (SsaPropOptimizer). */
    public array $unsafeObjectProps = [];

    /**
     * @var array<string, string>
     */
    public array $objects = [];

    /** @var array<string, string> Native Object pointer variable => fully-qualified class name. */
    public array $nativeObjects = [];

    /**
     * Native pointer variables proven non-null at the current parse point.
     *
     * Non-null Native parameters enter this set after their single function
     * entry check. Any assignment or unset conservatively removes the proof.
     *
     * @var array<string, true>
     */
    public array $nonNullNativeObjects = [];

    /**
     * Declared object constraints that are not used for native-call dispatch.
     *
     * @var array<string, string>
     */
    public array $declaredObjects = [];

    /**
     * @var array<string, array>
     */
    public array $stdArrays = [];

    /**
     * @var array<string, array>
     */
    public array $stdContainers = [];
    public array $localVars = [];
    /** @var array<string, string> C++ initializers folded into function-scope local declarations. */
    public array $localVarInitializers = [];
    public array $staticVars = [];
    public array $globalVars = [];

    /**
     * @var array<string, string>
     */
    public array $ceWrappers = [];
    /** @var array<string, string> Process-stable class name => function-local zend_class_entry* variable. */
    public array $classEntryPtrs = [];
    /** Reusable php::CallableScope local, created only when this function performs scoped calls. */
    public ?string $callableScopeVar = null;
    /** This generated body needs a temporary lexical scope on the nearest user-code frame. */
    public bool $needsUserCodeCallableScope = false;
    public int $tmpVarIndex = 0;
    public array $arguments = [];
    /** @var array<string, true> Bindings protected by #[Immutable]. */
    public array $immutableVars = [];
    /** @var array<string, true> Immutable bindings which may contain object identity. */
    public array $immutableObjectVars = [];
    /** True while parsing a breakable loop or switch. */
    public bool $inLoop = false;
    /** True while parsing a for/foreach/while/do-while body. */
    public bool $inContinuableLoop = false;
    public bool $inClosure = false;
    public ?array $closureReturnTypeCheck = null;
    public string $closureReturnTypeStr = '';

    /** True if any break N (N > 1) appears in this function. */
    public bool $hasMultiLevelBreak = false;

    /** True if any continue N (N > 1) appears in this function. */
    public bool $hasMultiLevelContinue = false;

    public array $beforeStmtLines = [];
    public array $afterStmtLines = [];
    public array $objectProps;
    /** Map of static property local slots. int/float keep stable zval* slots; other types use Var slots. */
    public array $staticPropRefs = [];
    public int $scopeLevel = 0;
    /**
     * @var array<int, ScopeContext>
     */
    public array $scopeLayouts = [];

    public function __construct()
    {
        $this->localVars = [];
        $this->localVarInitializers = [];
        $this->staticVars = [];
        $this->arguments = [];
        $this->immutableVars = [];
        $this->immutableObjectVars = [];
        $this->objects = [];
        $this->nativeObjects = [];
        $this->nonNullNativeObjects = [];
        $this->declaredObjects = [];
        $this->stdArrays = [];
        $this->stdContainers = [];
        $this->objectProps = [];
        $this->ssaBuilder = null;
        $this->stableObjects = [];
        $this->hoistedProps = [];
        $this->unsafeObjectProps = [];
        $this->staticPropRefs = [];
        $this->ceWrappers = [];
        $this->classEntryPtrs = [];
        $this->callableScopeVar = null;
        $this->tmpVarIndex = 0;
        $this->scopeLayouts = [];
        $this->callableScopeVar = null;
        $this->scopeLevel = 0;
        $this->inLoop = false;
        $this->inContinuableLoop = false;
        $this->inClosure = false;
        $this->closureReturnTypeCheck = null;
        $this->closureReturnTypeStr = '';
    }

    public function enterScope(): void
    {
        $this->scopeLayouts[$this->scopeLevel] = new ScopeContext();
        $this->scopeLevel++;
    }

    public function leaveScope(): void
    {
        $this->scopeLevel--;
        unset($this->scopeLayouts[$this->scopeLevel]);
    }

    public function resetAnalysisTemporaries(
        array $localVars,
        int $tmpVarIndex,
        array $declaredObjects,
        array $nativeObjects = [],
        array $nonNullNativeObjects = [],
    ): void
    {
        $this->localVars = $localVars;
        $this->localVarInitializers = [];
        $this->tmpVarIndex = $tmpVarIndex;
        $this->declaredObjects = $declaredObjects;
        $this->nativeObjects = $nativeObjects;
        $this->nonNullNativeObjects = $nonNullNativeObjects;
        $this->beforeStmtLines = [];
        $this->afterStmtLines = [];
        $this->objectProps = [];
        $this->hoistedProps = [];
        $this->staticPropRefs = [];
        $this->classEntryPtrs = [];
        $this->scopeLayouts = [];
        $this->scopeLevel = 0;
        $this->inLoop = false;
        $this->inContinuableLoop = false;
    }
}

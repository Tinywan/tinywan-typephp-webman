<?php
/**
 * SSA/e-SSA builder for TypePHP.
 *
 * Based on php-src Zend Optimizer's SSA/e-SSA mechanisms (zend_ssa.h, sccp.c, dce.c).
 * Adapted to operate on nikic/php-parser AST nodes instead of Zend VM opcodes.
 *
 * Core algorithm:
 *   1. Build CFG (basic blocks from statement list)
 *   2. Compute dominator tree (iterative algorithm)
 *   3. Compute dominance frontier (for φ placement)
 *   4. Place φ functions at dominance frontier join points
 *   5. Rename variables to SSA form (walk dominator tree)
 *
 * Special handling:
 *   - goto: creates non-local CFG edges; label targets become block entries with
 *           additional predecessors. φ functions merge definitions from all paths.
 *   - unset($var): kills the variable at that point. Modeled as a definition to a
 *                  special "undefined" SSA var. Any subsequent use of the variable
 *                  references the undefined SSA var → detectable at analysis time.
 *   - &$var reference: taking a reference marks the variable as "escaped". When a
 *                      function call receives &$var, the variable is re-defined with
 *                      unknown value after the call. Tracked via IS_REFERENCE flag.
 */

namespace TypePhp\Analysis;

use TypePhp\Parser\AstNodeType;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Node\FunctionLike;
use PhpParser\NodeAbstract;

/**
 * Represents a single static single assignment definition of a variable.
 */
class SsaVar
{
    public int $id;
    public string $origName;         // original PHP variable name (e.g. "$x")
    public int $flags = 0;

    /** The AST node where this variable was defined (Assign, Param, etc.) */
    public ?Node $definition = null;

    /** Type constraint from instanceof/type-check conditions (e-SSA pi node) */
    public ?PiConstraint $pi = null;

    /** For φ-function vars: the source block IDs and their SsaVar IDs */
    public array $phiSources = [];   // blockId => ssaVarId

    /** Whether this var has a constant value (from constant propagation later) */
    public bool $isConstant = false;
    /** The constant value, if known */
    public mixed $constantValue = null;

    public function __construct(int $id, string $origName, int $flags = 0) {
        $this->id = $id;
        $this->origName = $origName;
        $this->flags = $flags;
    }
}

/**
 * e-SSA pi node constraint: records type narrowing from a condition.
 *
 * e.g. after `if ($x instanceof Foo)`, the true-branch gets a PiConstraint
 * that narrows $x's type to Foo (or subclass).
 */
class PiConstraint
{
    /** Narrowed type bitmask or class name */
    public string $narrowedType = '';
    /** The condition expression that produces this constraint */
    public ?NodeAbstract $condition = null;
    /** Whether this is an instanceof check (is_instanceof = true means may be subclass) */
    public bool $isInstanceof = true;

    /** Range constraint: min <= var <= max (for integer range inference) */
    public bool $hasRange = false;
    public int|float $rangeMin = 0;
    public int|float $rangeMax = 0;
}

// SsaVar flags (mirror php-src zend_ssa_var_info patterns)
class SsaFlags
{
    public const int UNDEFINED   = 1 << 0;  // variable is undefined (after unset)
    public const int REFERENCE   = 1 << 1;  // variable has been referenced (&$var)
    public const int ESCAPED     = 1 << 2;  // variable escapes (global, closure use, etc.)
    public const int PHI         = 1 << 3;  // this SSA var is a φ function result
    public const int PARAM       = 1 << 4;  // defined by function parameter
    public const int KILLED      = 1 << 5;  // explicitly killed via unset()
}

/**
 * A basic block in the control flow graph.
 */
class SsaBlock
{
    public int $id;
    /** @var Node[] AST statements in this block (in order) */
    public array $stmts = [];
    /** @var int[] Predecessor block IDs */
    public array $predecessors = [];
    /** @var int[] Successor block IDs */
    public array $successors = [];

    // For goto/label handling
    public bool $isGotoTarget = false;
    public ?string $labelName = null;
    public bool $endsWithGoto = false;
    public ?string $gotoLabel = null;

    // For branch CFG expansion: block IDs (indices in blocks array)
    /** First block of the true branch, -1 if not set */
    public int $branchTrueBlock = -1;
    /** First block of the false branch, -1 if no false branch */
    public int $branchFalseBlock = -1;
    /** Join block after both branches converge */
    public int $branchJoinBlock = -1;
    /** This block is a join point for a preceding branch */
    public bool $isJoinPoint = false;
    /** If >= 0, this block must jump to the specified block (no fall-through) */
    public int $forceJumpTo = -1;

    /** @var array<string, SsaVar> φ functions at block entry: varName => SsaVar */
    public array $phi = [];

    // Dominator tree
    public int $dominator = -1;          // immediate dominator block ID
    /** @var int[] Blocks that this block immediately dominates */
    public array $dominatedChildren = [];
    /** @var int[] Blocks in the dominance frontier of this block */
    public array $dominanceFrontier = [];
}

/**
 * Tracks variable state during SSA renaming pass.
 */
class VarState
{
    /** Stack of SsaVar IDs for this variable name (current definition is on top) */
    public array $stack = [];
    /** Counter for generating unique SSA var IDs for this variable */
    public int $counter = 0;
}

/**
 * SSA Builder: transforms a function body into SSA form.
 *
 * Usage:
 *   $builder = new SsaBuilder($functionDef->stmts, $functionDef->argInfoList);
 *   $builder->build();
 *
 * After build():
 *   - $builder->blocks: basic blocks with φ functions
 *   - $builder->ssaVars: all SSA variable definitions
 *   - $builder->getDefBlocks($varName): all blocks where $varName is defined
 */
class SsaBuilder
{
    use AstNodeType;

    /** @var SsaBlock[] All basic blocks */
    public array $blocks = [];

    /** @var SsaVar[] Map of ssaVarId => SsaVar */
    public array $ssaVars = [];

    /** @var VarState[] Map of varName => VarState (used during renaming) */
    private array $varStates = [];

    /** Next SSA variable ID */
    private int $nextSsaId = 0;

    /** @var array<string, int[]> Block IDs where each variable is defined */
    private array $defBlocks = [];

    /** @var string[] Parameter names */
    private array $params = [];

    /** @var array Parameter byRef flags: paramName => bool */
    private array $paramByRef = [];

    /** @var array<string, int> goto label → block ID */
    private array $labelBlocks = [];

    /** @var int The entry block ID */
    private int $entryBlockId = 0;

    /** @var int The exit block ID */
    private int $exitBlockId = -1;

    /**
     * @param Node[]        $stmts     Function body AST statements
     * @param array         $argInfoList Array of ArgInfo for function parameters
     */
    public function __construct(
        private array $stmts,
        private array $argInfoList = []
    ) {
        foreach ($this->argInfoList as $argInfo) {
            $paramName = $argInfo->phpName ?: $argInfo->name;
            $this->params[] = $paramName;
            $this->paramByRef[$paramName] = $argInfo->byRef ?? false;
        }
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Run the full SSA construction pipeline.
     */
    /** @return Node[] Original function body AST statements */
    public function getStmts(): array
    {
        return $this->stmts;
    }

    public function build(): void
    {
        $this->buildCfg();
        $this->computeDominators();
        $this->computeDominanceFrontier();
        $this->placePhiFunctions();
        $this->renameVariables();
    }

    /**
     * Get all blocks where a variable has definitions.
     *
     * @return int[] Block IDs
     */
    public function getDefBlocks(string $varName): array
    {
        return $this->defBlocks[$varName] ?? [];
    }

    /**
     * Check if a variable is defined at the entry of a block (via φ function).
     */
    public function hasPhiAtBlock(string $varName, int $blockId): bool
    {
        return isset($this->blocks[$blockId]->phi[$varName]);
    }

    /**
     * Get the block containing a goto label.
     */
    public function getLabelBlock(string $labelName): ?int
    {
        return $this->labelBlocks[$labelName] ?? null;
    }

    // =========================================================================
    // Step 1: CFG Construction
    // =========================================================================

    /**
     * Build the control flow graph: split statements into basic blocks.
     *
     * Block boundaries are created at:
     *  - Labels (goto targets) — always start a new block
     *  - Goto statements — end the current block
     *  - If/else/while/for/foreach/switch/try — sub-blocks are created recursively
     *  - Return/throw/exit — terminate the block
     */
    private function buildCfg(): void
    {
        // First pass: collect all label positions (goto targets)
        $this->collectLabels($this->stmts);

        // Second pass: split into blocks
        $this->blocks = $this->splitIntoBlocks($this->stmts);

        // Create exit block
        $exitBlock = new SsaBlock();
        $exitBlock->id = count($this->blocks);
        $this->blocks[] = $exitBlock;
        $this->exitBlockId = $exitBlock->id;

        // Connect blocks
        $this->connectBlocks();
    }

    /**
     * First pass: find all goto labels and their statement positions.
     */
    private function collectLabels(array $stmts): void
    {
        foreach ($stmts as $i => $stmt) {
            if ($stmt instanceof Stmt\Label) {
                $this->labelBlocks[$stmt->name->name] = -1; // placeholder, will be set in splitIntoBlocks
            } elseif ($stmt instanceof Stmt\If_) {
                $this->collectLabels($stmt->stmts);
                if ($stmt->elseifs) {
                    foreach ($stmt->elseifs as $elseif) {
                        $this->collectLabels($elseif->stmts);
                    }
                }
                if ($stmt->else) {
                    $this->collectLabels($stmt->else->stmts);
                }
            } elseif ($stmt instanceof Stmt\While_ || $stmt instanceof Stmt\Do_) {
                $this->collectLabels($stmt->stmts);
            } elseif ($stmt instanceof Stmt\For_) {
                $this->collectLabels($stmt->stmts);
            } elseif ($stmt instanceof Stmt\Foreach_) {
                $this->collectLabels($stmt->stmts);
            } elseif ($stmt instanceof Stmt\Switch_) {
                foreach ($stmt->cases as $case) {
                    $this->collectLabels($case->stmts);
                }
            } elseif ($stmt instanceof Stmt\TryCatch) {
                $this->collectLabels($stmt->stmts);
                foreach ($stmt->catches as $catch) {
                    $this->collectLabels($catch->stmts);
                }
                if ($stmt->finally) {
                    $this->collectLabels($stmt->finally->stmts);
                }
            } elseif ($stmt instanceof Stmt\Block) {
                $this->collectLabels($stmt->stmts);
            }
        }
    }

    /**
     * Split a list of statements into basic blocks.
     *
     * A new block starts at:
     *  - The beginning of the function (entry block)
     *  - After a label (goto target)
     *  - After a conditional branch target
     *
     * A block ends at:
     *  - A goto statement
     *  - A return/throw/exit statement
     *  - A label (the label starts a new block)
     *  - A branch (if/while/etc. — the branch itself is the last stmt)
     *
     * @param Node[] $stmts
     * @return SsaBlock[]
     */
    private function splitIntoBlocks(array $stmts): array
    {
        $currentBlock = $this->newBlock();
        $currentBlock->id = 0;
        $this->entryBlockId = 0;
        $blocks = [$currentBlock];

        $this->splitStmtList($stmts, $blocks, $currentBlock);

        // Renumber blocks sequentially
        foreach ($blocks as $idx => $block) {
            $block->id = $idx;
        }
        $this->entryBlockId = 0;

        return $blocks;
    }

    /**
     * Recursively split a statement list into blocks, appending to $blocks.
     * Returns the index within $blocks of the first block created.
     *
     * @param Node[]    $stmts
     * @param SsaBlock[] $blocks  Reference to the blocks array being built
     * @param SsaBlock  $currentBlock  The current block (already in $blocks)
     */
    private function splitStmtList(array $stmts, array &$blocks, SsaBlock &$currentBlock): void
    {
        for ($i = 0; $i < count($stmts); $i++) {
            $stmt = $stmts[$i];

            // Label: always starts a new block
            if ($stmt instanceof Stmt\Label) {
                if (!empty($currentBlock->stmts)) {
                    $currentBlock = $this->newBlock();
                    $blocks[] = $currentBlock;
                }
                $currentBlock->isGotoTarget = true;
                $currentBlock->labelName = $stmt->name->name;
                // Use index in $blocks array (matches final ID after renumbering)
                $this->labelBlocks[$stmt->name->name] = count($blocks) - 1;
                continue;
            }

            // Goto: ends the current block
            if ($stmt instanceof Stmt\Goto_) {
                $currentBlock->stmts[] = $stmt;
                $currentBlock->endsWithGoto = true;
                $currentBlock->gotoLabel = $stmt->name->name;
                if ($i < count($stmts) - 1) {
                    $currentBlock = $this->newBlock();
                    $blocks[] = $currentBlock;
                }
                continue;
            }

            // Terminal statements: end the current block
            if ($this->isReturnExpr($stmt) || $this->isThrowExpr($stmt)) {
                $currentBlock->stmts[] = $stmt;
                if ($i < count($stmts) - 1) {
                    $currentBlock = $this->newBlock();
                    $blocks[] = $currentBlock;
                }
                continue;
            }

            // If statement: expand into condition block + body blocks + join
            if ($stmt instanceof Stmt\If_) {
                $this->expandIfStmt($stmt, $blocks, $currentBlock, $i, $stmts);
                continue;
            }

            // Other branch statements: end current block, will be handled later
            if ($this->isBranch($stmt)) {
                $currentBlock->stmts[] = $stmt;
                if ($i < count($stmts) - 1) {
                    $currentBlock = $this->newBlock();
                    $blocks[] = $currentBlock;
                }
                continue;
            }

            // Regular statement: add to current block
            $currentBlock->stmts[] = $stmt;
        }
    }

    /**
     * Expand an if/elseif/else statement into proper CFG blocks.
     *
     * Creates:
     *   - A condition block (ending with the if)
     *   - Body blocks for the true branch
     *   - Body blocks for the false branch (else/elseif)
     *   - A join block where both paths converge
     *
     * @param SsaBlock[] $blocks
     */
    private function expandIfStmt(Stmt\If_ $ifStmt, array &$blocks, SsaBlock &$currentBlock, int $stmtIndex, array $allStmts): void
    {
        // The if statement is the last statement in the current (condition) block
        $currentBlock->stmts[] = $ifStmt;
        $condBlockIndex = count($blocks) - 1;

        // Process true branch body into new blocks
        $trueStartIndex = count($blocks);
        $trueStartBlock = $this->newBlock();
        $blocks[] = $trueStartBlock;
        $this->splitStmtList($ifStmt->stmts, $blocks, $trueStartBlock);
        $trueEndIndex = count($blocks) - 1;

        // Process false branch (else/elseif chain)
        $falseStartIndex = -1;
        $hasFalseBranch = false;
        if (!empty($ifStmt->elseifs)) {
            $falseStartIndex = count($blocks);
            $hasFalseBranch = true;
            foreach ($ifStmt->elseifs as $elseif) {
                $innerIf = new Stmt\If_($elseif->cond, [
                    'stmts' => $elseif->stmts,
                    'elseifs' => [],
                    'else' => null,
                ]);
                if (!empty($currentBlock->stmts)) {
                    $currentBlock = $this->newBlock();
                    $blocks[] = $currentBlock;
                }
                $this->expandIfStmt($innerIf, $blocks, $currentBlock, 0, [$innerIf]);
            }
        }
        if ($ifStmt->else) {
            if (!$hasFalseBranch) {
                $falseStartIndex = count($blocks);
                $hasFalseBranch = true;
            }
            $falseStartBlock = $this->newBlock();
            $blocks[] = $falseStartBlock;
            if ($falseStartIndex === -1) {
                $falseStartIndex = count($blocks) - 1;
            }
            $this->splitStmtList($ifStmt->else->stmts, $blocks, $falseStartBlock);
        }
        $falseEndIndex = $hasFalseBranch ? (count($blocks) - 1) : -1;

        // Create join block (where both true and false paths converge)
        $joinBlock = $this->newBlock();
        $joinBlock->isJoinPoint = true;
        $joinIndex = count($blocks);
        $blocks[] = $joinBlock;

        // Force the last block of each branch to jump to the join block
        // (prevents fall-through from true body into false body, etc.)
        if ($trueEndIndex >= $trueStartIndex) {
            $blocks[$trueEndIndex]->forceJumpTo = $joinIndex;
        }
        if ($falseEndIndex >= 0) {
            $blocks[$falseEndIndex]->forceJumpTo = $joinIndex;
        }

        // Store branch info on condition block
        $blocks[$condBlockIndex]->branchTrueBlock = $trueStartIndex;
        $blocks[$condBlockIndex]->branchFalseBlock = $falseStartIndex;
        $blocks[$condBlockIndex]->branchJoinBlock = $joinIndex;

        // If there are more statements after this if, continue in the join block
        if ($stmtIndex < count($allStmts) - 1) {
            $currentBlock = $joinBlock;
        }
    }

    /**
     * Check if a statement is a control flow branch.
     */
    private function isBranch(Node $stmt): bool
    {
        return $stmt instanceof Stmt\If_
            || $stmt instanceof Stmt\While_
            || $stmt instanceof Stmt\Do_
            || $stmt instanceof Stmt\For_
            || $stmt instanceof Stmt\Foreach_
            || $stmt instanceof Stmt\Switch_
            || $stmt instanceof Stmt\TryCatch;
    }

    /**
     * Connect blocks: compute predecessor/successor relationships.
     */
    private function connectBlocks(): void
    {
        $n = count($this->blocks);
        for ($i = 0; $i < $n; $i++) {
            $block = $this->blocks[$i];

            // Forced jump overrides normal flow (used by branch bodies to reach join)
            if ($block->forceJumpTo >= 0 && $block->forceJumpTo < $n) {
                $block->successors[] = $block->forceJumpTo;
                $this->blocks[$block->forceJumpTo]->predecessors[] = $i;
                continue;
            }

            // Empty blocks always fall through
            if (empty($block->stmts)) {
                if ($i < $n - 1) {
                    $block->successors[] = $i + 1;
                    $this->blocks[$i + 1]->predecessors[] = $i;
                }
                continue;
            }

            $lastStmt = end($block->stmts);

            if ($block->endsWithGoto && $block->gotoLabel !== null) {
                // Goto: jump to label target
                $targetId = $this->labelBlocks[$block->gotoLabel] ?? -1;
                if ($targetId >= 0) {
                    $block->successors[] = $targetId;
                    $this->blocks[$targetId]->predecessors[] = $i;
                }
                // Goto does NOT fall through
            } elseif ($this->isReturnExpr($lastStmt) || $this->isThrowExpr($lastStmt)) {
                // Terminal: connect to exit block
                $block->successors[] = $this->exitBlockId;
                $this->blocks[$this->exitBlockId]->predecessors[] = $i;
            } elseif ($this->isBranch($lastStmt)) {
                $this->connectBranch($block, $lastStmt, $i, $n);
            } elseif ($i < $n - 1) {
                // Fall through to next block
                $block->successors[] = $i + 1;
                $this->blocks[$i + 1]->predecessors[] = $i;
            }
        }
    }

    /**
     * Connect branch successors for if/while/for/foreach/switch/try.
     */
    private function connectBranch(SsaBlock $block, Node $stmt, int $blockId, int $totalBlocks): void
    {
        if ($stmt instanceof Stmt\If_) {
            // Use branch tracking fields set during expandIfStmt
            if ($block->branchTrueBlock >= 0 && $block->branchTrueBlock < $totalBlocks) {
                $block->successors[] = $block->branchTrueBlock;
                $this->blocks[$block->branchTrueBlock]->predecessors[] = $blockId;
            }
            if ($block->branchFalseBlock >= 0 && $block->branchFalseBlock < $totalBlocks) {
                $block->successors[] = $block->branchFalseBlock;
                $this->blocks[$block->branchFalseBlock]->predecessors[] = $blockId;
            } else {
                // No false branch: condition falls through to join block
                if ($block->branchJoinBlock >= 0 && $block->branchJoinBlock < $totalBlocks) {
                    $block->successors[] = $block->branchJoinBlock;
                    $this->blocks[$block->branchJoinBlock]->predecessors[] = $blockId;
                }
            }
            // Connect the last block of each branch to the join block.
            // This is done by findAndConnectToJoin() since body blocks may fall through.
            // For now, trust that the body blocks' final blocks fall through to join.
        } elseif ($stmt instanceof Stmt\While_) {
            // Back-edge: loop body → condition
            // Forward-edge: condition → after loop
            $nextBlockId = $blockId + 1;
            if ($nextBlockId < $totalBlocks) {
                $block->successors[] = $nextBlockId;
                $this->blocks[$nextBlockId]->predecessors[] = $blockId;
            }
            // After loop
            $afterLoopId = $blockId + 2;
            if ($afterLoopId < $totalBlocks) {
                $block->successors[] = $afterLoopId;
                $this->blocks[$afterLoopId]->predecessors[] = $blockId;
            }
        } elseif ($stmt instanceof Stmt\Do_) {
            $nextBlockId = $blockId + 1;
            if ($nextBlockId < $totalBlocks) {
                $block->successors[] = $nextBlockId;
                $this->blocks[$nextBlockId]->predecessors[] = $blockId;
            }
        } elseif ($stmt instanceof Stmt\For_ || $stmt instanceof Stmt\Foreach_) {
            $nextBlockId = $blockId + 1;
            if ($nextBlockId < $totalBlocks) {
                $block->successors[] = $nextBlockId;
                $this->blocks[$nextBlockId]->predecessors[] = $blockId;
            }
            $afterLoopId = $blockId + 2;
            if ($afterLoopId < $totalBlocks) {
                $block->successors[] = $afterLoopId;
                $this->blocks[$afterLoopId]->predecessors[] = $blockId;
            }
        } elseif ($stmt instanceof Stmt\TryCatch) {
            $nextBlockId = $blockId + 1;
            if ($nextBlockId < $totalBlocks) {
                $block->successors[] = $nextBlockId;
                $this->blocks[$nextBlockId]->predecessors[] = $blockId;
            }
            // Catch blocks are also successors (exception path)
            // Simplified: catch blocks are after the try body
        } elseif ($stmt instanceof Stmt\Switch_) {
            $nextBlockId = $blockId + 1;
            if ($nextBlockId < $totalBlocks) {
                $block->successors[] = $nextBlockId;
                $this->blocks[$nextBlockId]->predecessors[] = $blockId;
            }
        }
    }

    // =========================================================================
    // Step 2: Dominator Tree
    // =========================================================================

    /**
     * Compute immediate dominators using the iterative algorithm
     * (Cooper, Harvey, Kennedy 2001).
     *
     * dom[entry] = {entry}
     * dom[b] = {all blocks} for b != entry
     *
     * Iterate: dom[b] = {b} ∪ (∩_{p ∈ preds[b]} dom[p])
     */
    private function computeDominators(): void
    {
        $n = count($this->blocks);
        if ($n === 0) {
            return;
        }

        // Initialize: entry dominates only itself; others dominate all blocks
        $allBlocks = [];
        for ($i = 0; $i < $n; $i++) {
            $allBlocks[] = $i;
        }

        // dominators[b] = set of blocks that dominate b
        $dominators = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i === $this->entryBlockId) {
                $dominators[$i] = [$this->entryBlockId => true];
            } else {
                $dominators[$i] = array_fill_keys($allBlocks, true);
            }
        }

        // Iterate until fixed point
        $changed = true;
        while ($changed) {
            $changed = false;
            for ($b = 0; $b < $n; $b++) {
                if ($b === $this->entryBlockId) {
                    continue;
                }

                // Intersection of all predecessors' dominator sets
                $newDom = null;
                foreach ($this->blocks[$b]->predecessors as $predId) {
                    if ($newDom === null) {
                        $newDom = $dominators[$predId];
                    } else {
                        $newDom = array_intersect_key($newDom, $dominators[$predId]);
                    }
                }
                if ($newDom === null) {
                    $newDom = [];
                }
                // Add self
                $newDom[$b] = true;

                if ($newDom !== $dominators[$b]) {
                    $dominators[$b] = $newDom;
                    $changed = true;
                }
            }
        }

        // Compute immediate dominator (idom) from dominator sets
        // idom[b] = the strict dominator of b that is closest to b
        for ($b = 0; $b < $n; $b++) {
            if ($b === $this->entryBlockId) {
                $this->blocks[$b]->dominator = $this->entryBlockId;
                continue;
            }
            // Strict dominators of b (excluding b itself)
            $strictDom = array_keys(array_diff_key($dominators[$b], [$b => true]));
            // idom is the one that is dominated by all other strict dominators
            $idom = $this->entryBlockId;
            foreach ($strictDom as $d) {
                if ($d !== $b && $d !== $this->entryBlockId) {
                    $idom = $d; // approximate — real implementation would find the max
                    break;
                }
            }
            // Better idom computation: idom is the strict dominator with max DFS number
            // Simplified: pick the first strict dominator that isn't block itself
            if ($strictDom) {
                // Find the one that dominates no other strict dominator (the "closest")
                $idom = $this->findImmediateDominator($b, $strictDom, $dominators);
            }
            $this->blocks[$b]->dominator = $idom;
            $this->blocks[$idom]->dominatedChildren[] = $b;
        }
    }

    /**
     * Find the immediate dominator: the strict dominator that is dominated by
     * all other strict dominators of b (the closest dominator to b).
     */
    private function findImmediateDominator(int $b, array $strictDom, array $dominators): int
    {
        if (count($strictDom) === 1) {
            return $strictDom[0];
        }

        foreach ($strictDom as $candidate) {
            $dominatedByAllOthers = true;
            foreach ($strictDom as $other) {
                if ($other !== $candidate && !isset($dominators[$candidate][$other])) {
                    $dominatedByAllOthers = false;
                    break;
                }
            }
            if ($dominatedByAllOthers) {
                return $candidate;
            }
        }

        return $this->entryBlockId;
    }

    // =========================================================================
    // Step 3: Dominance Frontier
    // =========================================================================

    /**
     * Compute dominance frontier for each block.
     *
     * DF[b] = { y | b dominates a predecessor of y, but b does NOT strictly dominate y }
     *
     * This is used to determine where φ functions must be placed:
     * if variable v is defined in block b, then every block in DF[b]
     * needs a φ function for v.
     */
    private function computeDominanceFrontier(): void
    {
        $n = count($this->blocks);

        for ($b = 0; $b < $n; $b++) {
            $this->blocks[$b]->dominanceFrontier = [];
        }

        for ($b = 0; $b < $n; $b++) {
            $preds = $this->blocks[$b]->predecessors;
            if (count($preds) < 2) {
                continue; // No join point, no φ needed from this block
            }

            foreach ($preds as $predId) {
                $runner = $predId;
                // Walk up the dominator tree from pred until we find a node
                // that dominates b
                while ($runner !== $this->blocks[$b]->dominator && $runner !== $this->entryBlockId) {
                    // runner is in the dominance frontier of b
                    $this->blocks[$runner]->dominanceFrontier[] = $b;
                    $runner = $this->blocks[$runner]->dominator;
                }
            }
        }
    }

    // =========================================================================
    // Step 4: φ Function Placement
    // =========================================================================

    /**
     * Place φ functions for all variables at appropriate join points.
     *
     * Algorithm:
     *   For each variable v:
     *     worklist = all blocks where v is defined
     *     for each b in worklist:
     *       for each df in DF[b]:
     *         if df does not already have φ for v:
     *           add φ for v at entry of df
     *           add df to worklist (φ is also a definition)
     */
    private function placePhiFunctions(): void
    {
        // Collect all variable names defined anywhere (including params)
        $allVars = $this->collectAllVariables();

        // Collect definition blocks for each variable
        foreach ($allVars as $varName) {
            $this->defBlocks[$varName] = [];
        }

        foreach ($this->blocks as $block) {
            foreach ($block->stmts as $stmt) {
                $defs = $this->getDefinedVars($stmt);
                foreach ($defs as $varName) {
                    if (!in_array($block->id, $this->defBlocks[$varName] ?? [])) {
                        $this->defBlocks[$varName][] = $block->id;
                    }
                    if (!in_array($varName, $allVars)) {
                        $allVars[] = $varName;
                        $this->defBlocks[$varName] = [$block->id];
                    }
                }
            }
        }

        // For each variable, place φ at dominance frontier of each definition
        foreach ($allVars as $varName) {
            $worklist = $this->defBlocks[$varName] ?? [];
            $hasPhi = []; // set of blocks that already have φ for this var
            $iterCount = 0;

            while (!empty($worklist) && $iterCount < 1000) {
                $iterCount++;
                $b = array_shift($worklist);

                foreach ($this->blocks[$b]->dominanceFrontier as $dfBlockId) {
                    if (!isset($hasPhi[$dfBlockId])) {
                        // Place φ at dfBlockId
                        $phiVarId = $this->allocateSsaId();
                        $phiVar = new SsaVar($phiVarId, $varName, SsaFlags::PHI);
                        $this->ssaVars[$phiVarId] = $phiVar;

                        $this->blocks[$dfBlockId]->phi[$varName] = $phiVar;

                        $hasPhi[$dfBlockId] = true;
                        // φ is also a definition → add dfBlockId to worklist
                        if (!in_array($dfBlockId, $worklist)) {
                            $worklist[] = $dfBlockId;
                        }
                        if (!in_array($dfBlockId, $this->defBlocks[$varName])) {
                            $this->defBlocks[$varName][] = $dfBlockId;
                        }
                    }
                }
            }
        }
    }

    /**
     * Collect all variable names that appear in the function body.
     */
    private function collectAllVariables(): array
    {
        $vars = [];

        // Parameters always count as variables
        foreach ($this->params as $paramName) {
            $vars[] = $paramName;
        }

        // Walk all blocks and statements
        foreach ($this->blocks as $block) {
            foreach ($block->stmts as $stmt) {
                $this->collectVarsFromNode($stmt, $vars);
            }
        }

        return array_unique($vars);
    }

    /**
     * Recursively collect variable names from an AST node.
     */
    private function collectVarsFromNode(Node $node, array &$vars): void
    {
        if ($node instanceof Expr\Variable && is_string($node->name)) {
            $vars[] = $node->name;
            return;
        }

        // Recursively walk child nodes
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->$subNodeName;
            if ($subNode instanceof Node) {
                $this->collectVarsFromNode($subNode, $vars);
            } elseif (is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof Node) {
                        $this->collectVarsFromNode($item, $vars);
                    }
                }
            }
        }
    }

    /**
     * Get variable names defined by a statement.
     * Also recurses into TryCatch and other compound statements
     * to find nested definitions (e.g., catch variables).
     */
    private function getDefinedVars(Node $stmt): array
    {
        $defs = [];

        if ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\Assign) {
            $var = $stmt->expr->var;
            if ($var instanceof Expr\Variable && is_string($var->name)) {
                $defs[] = $var->name;
            }
        } elseif ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\AssignOp) {
            $var = $stmt->expr->var;
            if ($var instanceof Expr\Variable && is_string($var->name)) {
                $defs[] = $var->name;
            }
        } elseif ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\AssignRef) {
            $var = $stmt->expr->var;
            if ($var instanceof Expr\Variable && is_string($var->name)) {
                $defs[] = $var->name;
            }
        } elseif ($stmt instanceof Stmt\Expression
            && ($stmt->expr instanceof Expr\PreInc || $stmt->expr instanceof Expr\PostInc
                || $stmt->expr instanceof Expr\PreDec || $stmt->expr instanceof Expr\PostDec)) {
            $var = $stmt->expr->var;
            if ($var instanceof Expr\Variable && is_string($var->name)) {
                $defs[] = $var->name;
            }
        } elseif ($stmt instanceof Stmt\Static_) {
            foreach ($stmt->vars as $staticVar) {
                $defs[] = $staticVar->var->name;
            }
        } elseif ($stmt instanceof Stmt\Foreach_) {
            if ($stmt->keyVar instanceof Expr\Variable && is_string($stmt->keyVar->name)) {
                $defs[] = $stmt->keyVar->name;
            }
            if ($stmt->valueVar instanceof Expr\Variable && is_string($stmt->valueVar->name)) {
                $defs[] = $stmt->valueVar->name;
            }
        } elseif ($stmt instanceof Stmt\Catch_) {
            if ($stmt->var && is_string($stmt->var->name)) {
                $defs[] = $stmt->var->name;
            }
        }
        // Note: unset($var) is also a kind of "definition" — kills the variable
        elseif ($stmt instanceof Stmt\Unset_) {
            foreach ($stmt->vars as $var) {
                if ($var instanceof Expr\Variable && is_string($var->name)) {
                    $defs[] = $var->name;
                }
            }
        }

        // Recurse into compound statements for nested definitions
        if ($stmt instanceof Stmt\While_ || $stmt instanceof Stmt\Do_ || $stmt instanceof Stmt\For_) {
            foreach ($stmt->stmts as $nestedStmt) {
                $defs = array_merge($defs, $this->getDefinedVars($nestedStmt));
            }
        }

        if ($stmt instanceof Stmt\Foreach_) {
            foreach ($stmt->stmts as $nestedStmt) {
                $defs = array_merge($defs, $this->getDefinedVars($nestedStmt));
            }
        }

        if ($stmt instanceof Stmt\Switch_) {
            foreach ($stmt->cases as $case) {
                foreach ($case->stmts as $nestedStmt) {
                    $defs = array_merge($defs, $this->getDefinedVars($nestedStmt));
                }
            }
        }

        if ($stmt instanceof Stmt\TryCatch) {
            foreach ($stmt->stmts as $nestedStmt) {
                $defs = array_merge($defs, $this->getDefinedVars($nestedStmt));
            }
            foreach ($stmt->catches as $catch) {
                $defs = array_merge($defs, $this->getDefinedVars($catch));
                foreach ($catch->stmts as $nestedStmt) {
                    $defs = array_merge($defs, $this->getDefinedVars($nestedStmt));
                }
            }
            if ($stmt->finally) {
                foreach ($stmt->finally->stmts as $nestedStmt) {
                    $defs = array_merge($defs, $this->getDefinedVars($nestedStmt));
                }
            }
        }

        return array_values(array_unique($defs));
    }

    // =========================================================================
    // Step 5: Variable Renaming (SSA Construction)
    // =========================================================================

    /**
     * Rename variables to SSA form by walking the dominator tree.
     *
     * For each block:
     *   1. For each φ function at block entry: create new SSA var, push to stack
     *   2. For each statement in block:
     *      a. Rename all variable USEs to use the current SSA var from stack
     *      b. For each variable DEF: create new SSA var, push to stack
     *   3. For each successor block: fill in φ function source operands
     *   4. Recursively process dominated children
     *   5. Pop all SSA vars pushed in this block
     */
    private function renameVariables(): void
    {
        if (empty($this->blocks)) {
            return;
        }

        // Initialize parameter SSA vars at entry block
        foreach ($this->params as $paramName) {
            $ssaId = $this->allocateSsaId();
            $flags = SsaFlags::PARAM;
            if ($this->paramByRef[$paramName] ?? false) {
                $flags |= SsaFlags::REFERENCE;
            }
            $ssaVar = new SsaVar($ssaId, $paramName, $flags);
            $this->ssaVars[$ssaId] = $ssaVar;

            if (!isset($this->varStates[$paramName])) {
                $this->varStates[$paramName] = new VarState();
            }
            $this->varStates[$paramName]->stack[] = $ssaId;
            $this->varStates[$paramName]->counter = 1;
        }

        // Walk dominator tree starting from entry
        $this->renameBlock($this->entryBlockId);
    }

    /**
     * Rename variables within a single block, then recurse into dominated children.
     */
    private function renameBlock(int $blockId): void
    {
        $block = $this->blocks[$blockId];
        $pushedVars = [];

        // Step 1: Process φ functions at block entry
        // Each φ function produces a new SSA var
        foreach ($block->phi as $varName => $phiVar) {
            if (!isset($this->varStates[$varName])) {
                $this->varStates[$varName] = new VarState();
            }
            $this->varStates[$varName]->stack[] = $phiVar->id;
            $pushedVars[] = [$varName, 'phi'];
        }

        // Step 2: Walk statements in block
        foreach ($block->stmts as $stmtIndex => $stmt) {
            // First: rename all variable USEs in the statement
            $this->renameUses($stmt, $blockId, $stmtIndex);

            // Then: handle definitions
            $this->renameDefs($stmt, $blockId, $stmtIndex, $pushedVars);
        }

        // Step 3: Fill φ source operands for successors
        $this->fillPhiSources($block);

        // Step 4: Recurse into dominated children
        foreach ($block->dominatedChildren as $childId) {
            $this->renameBlock($childId);
        }

        // Step 5: Pop SSA vars pushed in this block
        foreach (array_reverse($pushedVars) as [$varName, $type]) {
            array_pop($this->varStates[$varName]->stack);
        }
    }

    /**
     * Rename variable uses in a statement: replace each use with the current
     * SSA var ID from the stack.
     */
    private function renameUses(Node $stmt, int $blockId, int $stmtIndex): void
    {
        $uses = $this->getUsedVars($stmt);
        foreach ($uses as $varName) {
            $currentSsaId = $this->getCurrentSsaId($varName);
            if ($currentSsaId !== null) {
                // Record: at block $blockId, stmt $stmtIndex, var $varName
                // → SSA var $currentSsaId
                // (This mapping can be used later for type inference etc.)
            }
        }
    }

    /**
     * Handle variable definitions in a statement: create new SSA vars.
     */
    private function renameDefs(Node $stmt, int $blockId, int $stmtIndex, array &$pushedVars): void
    {
        $this->handleCallByRef($stmt, $pushedVars);

        // Handle unset($var) — kill the variable
        if ($stmt instanceof Stmt\Unset_) {
            foreach ($stmt->vars as $var) {
                if ($var instanceof Expr\Variable && is_string($var->name)) {
                    $varName = $var->name;
                    $ssaId = $this->allocateSsaId();
                    $ssaVar = new SsaVar($ssaId, $varName, SsaFlags::KILLED | SsaFlags::UNDEFINED);
                    $ssaVar->definition = $stmt;
                    $this->ssaVars[$ssaId] = $ssaVar;

                    if (!isset($this->varStates[$varName])) {
                        $this->varStates[$varName] = new VarState();
                    }
                    $this->varStates[$varName]->stack[] = $ssaId;
                    $pushedVars[] = [$varName, 'unset'];
                }
            }
            return;
        }

        // Handle reference assignment: $x =& $y
        if ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\AssignRef) {
            $assignRef = $stmt->expr;
            // LHS: $x gets a new SSA var with REFERENCE flag
            $var = $assignRef->var;
            if ($var instanceof Expr\Variable && is_string($var->name)) {
                $varName = $var->name;
                $ssaId = $this->allocateSsaId();
                $ssaVar = new SsaVar($ssaId, $varName, SsaFlags::REFERENCE);
                $ssaVar->definition = $stmt;
                $this->ssaVars[$ssaId] = $ssaVar;

                if (!isset($this->varStates[$varName])) {
                    $this->varStates[$varName] = new VarState();
                }
                $this->varStates[$varName]->stack[] = $ssaId;
                $pushedVars[] = [$varName, 'assign_ref'];
            }
            // RHS: $y gets a new SSA var with ESCAPED flag because taking a
            // reference to $y makes it escape — it can now be mutated through $x.
            $rhs = $assignRef->expr;
            if ($rhs instanceof Expr\Variable && is_string($rhs->name)) {
                $rhsName = $rhs->name;
                $rhsId = $this->allocateSsaId();
                $rhsVar = new SsaVar($rhsId, $rhsName, SsaFlags::ESCAPED);
                $rhsVar->definition = $stmt;
                $this->ssaVars[$rhsId] = $rhsVar;

                if (!isset($this->varStates[$rhsName])) {
                    $this->varStates[$rhsName] = new VarState();
                }
                $this->varStates[$rhsName]->stack[] = $rhsId;
                $pushedVars[] = [$rhsName, 'assign_ref_rhs'];
            }
            return;
        }

        // Handle regular assignment: $x = expr
        if ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\Assign) {
            $var = $stmt->expr->var;
            if ($var instanceof Expr\Variable && is_string($var->name)) {
                $varName = $var->name;
                $ssaId = $this->allocateSsaId();
                $ssaVar = new SsaVar($ssaId, $varName, 0);
                $ssaVar->definition = $stmt;
                $this->ssaVars[$ssaId] = $ssaVar;

                if (!isset($this->varStates[$varName])) {
                    $this->varStates[$varName] = new VarState();
                }
                $this->varStates[$varName]->stack[] = $ssaId;
                $pushedVars[] = [$varName, 'assign'];
            }
            return;
        }

        // Handle compound assignment: $x += expr, $x %= expr, ...
        if ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\AssignOp) {
            $var = $stmt->expr->var;
            if ($var instanceof Expr\Variable && is_string($var->name)) {
                $varName = $var->name;
                $ssaId = $this->allocateSsaId();
                $ssaVar = new SsaVar($ssaId, $varName, 0);
                $ssaVar->definition = $stmt;
                $this->ssaVars[$ssaId] = $ssaVar;

                if (!isset($this->varStates[$varName])) {
                    $this->varStates[$varName] = new VarState();
                }
                $this->varStates[$varName]->stack[] = $ssaId;
                $pushedVars[] = [$varName, 'assign_op'];
            }
            return;
        }

        // Handle increment/decrement: ++$x, $x++, --$x, $x--
        if ($stmt instanceof Stmt\Expression
            && ($stmt->expr instanceof Expr\PreInc || $stmt->expr instanceof Expr\PostInc
                || $stmt->expr instanceof Expr\PreDec || $stmt->expr instanceof Expr\PostDec)) {
            $var = $stmt->expr->var;
            if ($var instanceof Expr\Variable && is_string($var->name)) {
                $varName = $var->name;
                $ssaId = $this->allocateSsaId();
                $ssaVar = new SsaVar($ssaId, $varName, 0);
                $ssaVar->definition = $stmt;
                $this->ssaVars[$ssaId] = $ssaVar;

                if (!isset($this->varStates[$varName])) {
                    $this->varStates[$varName] = new VarState();
                }
                $this->varStates[$varName]->stack[] = $ssaId;
                $pushedVars[] = [$varName, 'inc_dec'];
            }
            return;
        }

        // Handle loop bodies that are not expanded into separate CFG blocks.
        if ($stmt instanceof Stmt\While_ || $stmt instanceof Stmt\Do_) {
            foreach ($stmt->stmts as $nestedStmt) {
                $this->renameDefs($nestedStmt, $blockId, $stmtIndex, $pushedVars);
            }
            return;
        }

        if ($stmt instanceof Stmt\For_) {
            foreach ($stmt->stmts as $nestedStmt) {
                $this->renameDefs($nestedStmt, $blockId, $stmtIndex, $pushedVars);
            }
            return;
        }

        if ($stmt instanceof Stmt\Switch_) {
            foreach ($stmt->cases as $case) {
                foreach ($case->stmts as $nestedStmt) {
                    $this->renameDefs($nestedStmt, $blockId, $stmtIndex, $pushedVars);
                }
            }
            return;
        }

        // Handle foreach value/key variables
        if ($stmt instanceof Stmt\Foreach_) {
            if ($stmt->valueVar instanceof Expr\Variable && is_string($stmt->valueVar->name)) {
                $varName = $stmt->valueVar->name;
                $flags = $stmt->byRef ? SsaFlags::REFERENCE : 0;
                $ssaId = $this->allocateSsaId();
                $ssaVar = new SsaVar($ssaId, $varName, $flags);
                $ssaVar->definition = $stmt;
                $this->ssaVars[$ssaId] = $ssaVar;

                if (!isset($this->varStates[$varName])) {
                    $this->varStates[$varName] = new VarState();
                }
                $this->varStates[$varName]->stack[] = $ssaId;
                $pushedVars[] = [$varName, 'foreach'];
            }
            if ($stmt->keyVar instanceof Expr\Variable && is_string($stmt->keyVar->name)) {
                $varName = $stmt->keyVar->name;
                $ssaId = $this->allocateSsaId();
                $ssaVar = new SsaVar($ssaId, $varName, 0);
                $ssaVar->definition = $stmt;
                $this->ssaVars[$ssaId] = $ssaVar;

                if (!isset($this->varStates[$varName])) {
                    $this->varStates[$varName] = new VarState();
                }
                $this->varStates[$varName]->stack[] = $ssaId;
                $pushedVars[] = [$varName, 'foreach_key'];
            }
            foreach ($stmt->stmts as $nestedStmt) {
                $this->renameDefs($nestedStmt, $blockId, $stmtIndex, $pushedVars);
            }
            return;
        }

        // Handle catch variables
        if ($stmt instanceof Stmt\Catch_) {
            if ($stmt->var && is_string($stmt->var->name)) {
                $varName = $stmt->var->name;
                $ssaId = $this->allocateSsaId();
                $ssaVar = new SsaVar($ssaId, $varName, 0);
                $ssaVar->definition = $stmt;
                $this->ssaVars[$ssaId] = $ssaVar;

                if (!isset($this->varStates[$varName])) {
                    $this->varStates[$varName] = new VarState();
                }
                $this->varStates[$varName]->stack[] = $ssaId;
                $pushedVars[] = [$varName, 'catch'];
            }
            return;
        }

        // Handle static variable declarations
        if ($stmt instanceof Stmt\Static_) {
            foreach ($stmt->vars as $staticVar) {
                $varName = $staticVar->var->name;
                $ssaId = $this->allocateSsaId();
                $ssaVar = new SsaVar($ssaId, $varName, SsaFlags::ESCAPED);
                $ssaVar->definition = $stmt;
                $this->ssaVars[$ssaId] = $ssaVar;

                if (!isset($this->varStates[$varName])) {
                    $this->varStates[$varName] = new VarState();
                }
                $this->varStates[$varName]->stack[] = $ssaId;
                $pushedVars[] = [$varName, 'static'];
            }
            return;
        }

        // Handle TryCatch: process catch variables as definitions
        if ($stmt instanceof Stmt\TryCatch) {
            foreach ($stmt->stmts as $nestedStmt) {
                $this->renameDefs($nestedStmt, $blockId, $stmtIndex, $pushedVars);
            }
            foreach ($stmt->catches as $catch) {
                $this->renameDefs($catch, $blockId, $stmtIndex, $pushedVars);
                foreach ($catch->stmts as $nestedStmt) {
                    $this->renameDefs($nestedStmt, $blockId, $stmtIndex, $pushedVars);
                }
            }
            if ($stmt->finally) {
                foreach ($stmt->finally->stmts as $nestedStmt) {
                    $this->renameDefs($nestedStmt, $blockId, $stmtIndex, $pushedVars);
                }
            }
        }
    }

    /**
     * Handle function/method calls that receive variables by reference.
     *
     * Two mechanisms in TypePHP:
     *   1. Explicit &$var at call site: func(&$x) — detected via $arg->byRef
     *   2. refval() pseudo-function: func(refval($x)) — used for dynamic calls
     *      where the compiler can't statically determine if the parameter is byRef.
     *      The compiler detects refval() via isRefvalCall() and unwraps it during codegen.
     *
     * When a variable is passed by reference to a function call, the function
     * may modify it. We model this as:
     *   - Mark the variable as REFERENCE/ESCAPED
     *   - Create a new SSA definition for the variable (value is unknown after call)
     */
    private function handleCallByRef(Node $stmt, array &$pushedVars): void
    {
        if ($stmt instanceof Stmt\Expression) {
            $this->handleCallByRefInExpr($stmt->expr, $stmt, $pushedVars);
            return;
        }

        if (($stmt instanceof Stmt\If_
            || $stmt instanceof Stmt\While_
            || $stmt instanceof Stmt\Do_)
            && $stmt->cond instanceof Node) {
            $this->handleCallByRefInExpr($stmt->cond, $stmt, $pushedVars);
        }

        if ($stmt instanceof Stmt\For_) {
            foreach ([$stmt->init, $stmt->cond, $stmt->loop] as $exprList) {
                foreach ($exprList as $expr) {
                    if ($expr instanceof Node) {
                        $this->handleCallByRefInExpr($expr, $stmt, $pushedVars);
                    }
                }
            }
        }

        if ($stmt instanceof Stmt\Foreach_ && $stmt->expr instanceof Node) {
            $this->handleCallByRefInExpr($stmt->expr, $stmt, $pushedVars);
        }

        if ($stmt instanceof Stmt\Switch_ && $stmt->cond instanceof Node) {
            $this->handleCallByRefInExpr($stmt->cond, $stmt, $pushedVars);
        }

        if ($stmt instanceof Stmt\Return_ && $stmt->expr instanceof Node) {
            $this->handleCallByRefInExpr($stmt->expr, $stmt, $pushedVars);
        }

        if ($stmt instanceof Stmt\Echo_) {
            foreach ($stmt->exprs as $expr) {
                if ($expr instanceof Node) {
                    $this->handleCallByRefInExpr($expr, $stmt, $pushedVars);
                }
            }
        }
    }

    private function handleCallByRefInExpr(Node $expr, Node $callStmt, array &$pushedVars): void
    {
        if ($expr instanceof Expr\FuncCall || $expr instanceof Expr\MethodCall
            || $expr instanceof Expr\StaticCall || $expr instanceof Expr\NullsafeMethodCall) {
            $this->collectCallByRefArgs($expr->args, $callStmt, $pushedVars);
        }

        foreach ($expr->getSubNodeNames() as $subNodeName) {
            $subNode = $expr->$subNodeName;
            if ($subNode instanceof Node) {
                $this->handleCallByRefInExpr($subNode, $callStmt, $pushedVars);
            } elseif (is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof Node) {
                        $this->handleCallByRefInExpr($item, $callStmt, $pushedVars);
                    }
                }
            }
        }
    }

    /**
     * Collect variables passed by reference in call arguments.
     *
     * Handles both:
     *   - Explicit &$var (arg->byRef === true)
     *   - refval($var) pseudo-function wrapping
     *
     * @param Node\Arg[] $args
     */
    private function collectCallByRefArgs(array $args, Node $callStmt, array &$pushedVars): void
    {
        foreach ($args as $arg) {
            if ($arg instanceof Node\VariadicPlaceholder) {
                continue;
            }
            $varName = null;

            // Case 1: Explicit &$var at call site
            if ($arg->byRef && $arg->value instanceof Expr\Variable && is_string($arg->value->name)) {
                $varName = $arg->value->name;
            }

            // Case 2: refval($var) — TypePHP convention for dynamic calls
            if ($varName === null && $arg->value instanceof Expr\FuncCall
                && $arg->value->name instanceof Node\Name
                && $arg->value->name->toLowerString() === 'refval'
                && !empty($arg->value->args)) {
                $inner = $arg->value->args[0]->value;
                if ($inner instanceof Expr\Variable && is_string($inner->name)) {
                    $varName = $inner->name;
                }
            }

            if ($varName !== null) {
                $ssaId = $this->allocateSsaId();
                $ssaVar = new SsaVar($ssaId, $varName, SsaFlags::REFERENCE | SsaFlags::ESCAPED);
                $ssaVar->definition = $callStmt;
                $this->ssaVars[$ssaId] = $ssaVar;

                if (!isset($this->varStates[$varName])) {
                    $this->varStates[$varName] = new VarState();
                }
                $this->varStates[$varName]->stack[] = $ssaId;
                $pushedVars[] = [$varName, 'call_by_ref'];
            }
        }
    }

    /**
     * Fill φ function source operands for successor blocks.
     * After processing block $blockId, for each successor that has φ functions,
     * set the source operand from this block to the current SSA var.
     */
    private function fillPhiSources(SsaBlock $block): void
    {
        foreach ($block->successors as $succId) {
            $succ = $this->blocks[$succId];
            foreach ($succ->phi as $varName => $phiVar) {
                $currentSsaId = $this->getCurrentSsaId($varName);
                if ($currentSsaId !== null) {
                    $phiVar->phiSources[$block->id] = $currentSsaId;
                }
            }
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function newBlock(): SsaBlock
    {
        $block = new SsaBlock();
        $block->id = count($this->blocks);
        return $block;
    }

    private function allocateSsaId(): int
    {
        return $this->nextSsaId++;
    }

    /**
     * Get the current SSA var ID for a variable name (top of stack).
     */
    private function getCurrentSsaId(string $varName): ?int
    {
        $state = $this->varStates[$varName] ?? null;
        if ($state === null || empty($state->stack)) {
            return null;
        }
        return end($state->stack);
    }

    /**
     * Get all variable names USED in a statement.
     */
    private function getUsedVars(Node $stmt): array
    {
        $vars = [];
        $this->collectVarUses($stmt, $vars, false);
        return array_unique($vars);
    }

    /**
     * Recursively collect variable uses, skipping left-hand sides of assignments.
     */
    private function collectVarUses(Node $node, array &$vars, bool $isLhs): void
    {
        // Skip left-hand side of assignments (those are definitions, not uses)
        if ($node instanceof Expr\Assign) {
            // RHS is a use
            $this->collectVarUses($node->expr, $vars, false);
            // LHS: if it's a simple variable, it's a definition (skip)
            // But array dim fetch on LHS uses the array variable
            if ($node->var instanceof Expr\ArrayDimFetch) {
                $this->collectVarUses($node->var->var, $vars, false);
                if ($node->var->dim !== null) {
                    $this->collectVarUses($node->var->dim, $vars, false);
                }
            } elseif ($node->var instanceof Expr\PropertyFetch) {
                $this->collectVarUses($node->var->var, $vars, false);
            }
            return;
        }

        if ($node instanceof Expr\AssignRef) {
            $this->collectVarUses($node->expr, $vars, false);
            if ($node->var instanceof Expr\ArrayDimFetch) {
                $this->collectVarUses($node->var->var, $vars, false);
                if ($node->var->dim !== null) {
                    $this->collectVarUses($node->var->dim, $vars, false);
                }
            }
            return;
        }

        // Assignment operators ($x += expr): $x is both use and def
        if ($node instanceof Expr\AssignOp) {
            $this->collectVarUses($node->expr, $vars, false);
            if ($node->var instanceof Expr\Variable && is_string($node->var->name)) {
                $vars[] = $node->var->name; // use of old value
            }
            if ($node->var instanceof Expr\ArrayDimFetch) {
                $this->collectVarUses($node->var->var, $vars, false);
                if ($node->var->dim !== null) {
                    $this->collectVarUses($node->var->dim, $vars, false);
                }
            }
            return;
        }

        // Pre/post increment/decrement: both use and def
        if ($node instanceof Expr\PreInc || $node instanceof Expr\PostInc
            || $node instanceof Expr\PreDec || $node instanceof Expr\PostDec) {
            if ($node->var instanceof Expr\Variable && is_string($node->var->name)) {
                $vars[] = $node->var->name;
            }
            if ($node->var instanceof Expr\ArrayDimFetch) {
                $this->collectVarUses($node->var->var, $vars, false);
                if ($node->var->dim !== null) {
                    $this->collectVarUses($node->var->dim, $vars, false);
                }
            }
            return;
        }

        // Function call: check for ref args
        if ($node instanceof Expr\FuncCall || $node instanceof Expr\MethodCall
            || $node instanceof Expr\StaticCall || $node instanceof Expr\NullsafeMethodCall) {
            foreach ($node->args as $arg) {
                if (isset($arg->value)) {
                    $this->collectVarUses($arg->value, $vars, false);
                }
            }
            if ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall) {
                $this->collectVarUses($node->var, $vars, false);
            }
            if ($node instanceof Expr\StaticCall && $node->class instanceof Expr) {
                $this->collectVarUses($node->class, $vars, false);
            }
            return;
        }

        // Simple variable
        if ($node instanceof Expr\Variable && is_string($node->name)) {
            $vars[] = $node->name;
            return;
        }

        // Recursively walk children
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->$subNodeName;
            if ($subNode instanceof Node) {
                $this->collectVarUses($subNode, $vars, false);
            } elseif (is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof Node) {
                        $this->collectVarUses($item, $vars, false);
                    }
                }
            }
        }
    }

    // =========================================================================
    // e-SSA: Pi Node Construction (Type Narrowing from Conditions)
    // =========================================================================

    /**
     * Build e-SSA pi constraints from instanceof and type-check conditions.
     *
     * When encountering a condition like `if ($x instanceof Foo)`:
     *   - In the TRUE branch, $x is narrowed to Foo (or subclass)
     *   - In the FALSE branch, $x is NOT Foo
     *
     * This method creates pi constraints on SSA variables at conditional branches.
     *
     * @param Node\Stmt\If_ $ifStmt The if statement to analyze
     * @return array{trueVars: array<string, PiConstraint>, falseVars: array<string, PiConstraint>}
     */
    public function buildPiConstraints(Stmt\If_ $ifStmt): array
    {
        $trueVars = [];
        $falseVars = [];

        $cond = $ifStmt->cond;

        // instanceof: $x instanceof ClassName
        if ($cond instanceof Expr\Instanceof_) {
            if ($cond->expr instanceof Expr\Variable && is_string($cond->expr->name)) {
                $varName = $cond->expr->name;
                if ($cond->class instanceof Node\Name) {
                    $className = $cond->class->toString();
                    $pi = new PiConstraint();
                    $pi->narrowedType = $className;
                    $pi->condition = $cond;
                    $pi->isInstanceof = true; // may be subclass
                    $trueVars[$varName] = $pi;

                    // FALSE branch: $x is NOT this type
                    $piFalse = new PiConstraint();
                    $piFalse->narrowedType = '!' . $className;
                    $piFalse->condition = $cond;
                    $piFalse->isInstanceof = false;
                    $falseVars[$varName] = $piFalse;
                }
            }
        }

        // is_* type checks: is_int($x), is_string($x), etc.
        if ($cond instanceof Expr\FuncCall && $cond->name instanceof Node\Name) {
            $funcName = $cond->name->toLowerString();
            $typeMap = [
                'is_int' => 'int', 'is_integer' => 'int', 'is_long' => 'int',
                'is_float' => 'float', 'is_double' => 'float', 'is_real' => 'float',
                'is_string' => 'string', 'is_bool' => 'bool', 'is_array' => 'array',
                'is_object' => 'object', 'is_null' => 'null', 'is_resource' => 'resource',
                'is_callable' => 'callable', 'is_iterable' => 'iterable',
            ];

            if (isset($typeMap[$funcName]) && !empty($cond->args)) {
                $arg = $cond->args[0]->value;
                if ($arg instanceof Expr\Variable && is_string($arg->name)) {
                    $varName = $arg->name;
                    $pi = new PiConstraint();
                    $pi->narrowedType = $typeMap[$funcName];
                    $pi->condition = $cond;
                    $pi->isInstanceof = false; // exact type, not subclass
                    $trueVars[$varName] = $pi;
                }
            }
        }

        // Negation: !$cond flips true/false branches
        if ($cond instanceof Expr\BooleanNot) {
            $inner = $cond->expr;
            // Recurse into inner condition, swapping true/false
            $dummyIf = new Stmt\If_($inner, ['stmts' => [], 'elseifs' => [], 'else' => null]);
            $innerResult = $this->buildPiConstraints($dummyIf);
            // Swap
            return [
                'trueVars' => $innerResult['falseVars'],
                'falseVars' => $innerResult['trueVars'],
            ];
        }

        // Conjunction: $a && $b — both must be true
        if ($cond instanceof Expr\BinaryOp\BooleanAnd || $cond instanceof Expr\BinaryOp\LogicalAnd) {
            $leftIf = new Stmt\If_($cond->left, ['stmts' => [], 'elseifs' => [], 'else' => null]);
            $rightIf = new Stmt\If_($cond->right, ['stmts' => [], 'elseifs' => [], 'else' => null]);
            $left = $this->buildPiConstraints($leftIf);
            $right = $this->buildPiConstraints($rightIf);
            return [
                'trueVars' => array_merge($left['trueVars'], $right['trueVars']),
                'falseVars' => [],
            ];
        }

        return ['trueVars' => $trueVars, 'falseVars' => $falseVars];
    }

    // =========================================================================
    // Debug / Dump
    // =========================================================================

    /**
     * Dump SSA information for debugging.
     */
    public function dump(): string
    {
        $out = "=== SSA Builder Dump ===\n";
        $out .= "Blocks: " . count($this->blocks) . "\n";
        $out .= "SSA Vars: " . count($this->ssaVars) . "\n";
        $out .= "Entry: B{$this->entryBlockId}, Exit: B{$this->exitBlockId}\n\n";

        foreach ($this->blocks as $block) {
            $out .= "--- Block B{$block->id} ---\n";
            if ($block->isGotoTarget) {
                $out .= "  Label: {$block->labelName} (goto target)\n";
            }
            $out .= "  Preds: [" . implode(', ', $block->predecessors) . "]\n";
            $out .= "  Succs: [" . implode(', ', $block->successors) . "]\n";
            $out .= "  Dom: B{$block->dominator}\n";
            $out .= "  DF: [" . implode(', ', $block->dominanceFrontier) . "]\n";

            if (!empty($block->phi)) {
                $out .= "  Φ functions:\n";
                foreach ($block->phi as $varName => $phiVar) {
                    $sources = [];
                    foreach ($phiVar->phiSources as $srcBlock => $srcSsaId) {
                        $sources[] = "B{$srcBlock}→s{$srcSsaId}";
                    }
                    $out .= "    \${$varName} = φ(" . implode(', ', $sources) . ") → s{$phiVar->id}\n";
                }
            }

            $out .= "  Stmts (" . count($block->stmts) . "):\n";
            foreach ($block->stmts as $i => $stmt) {
                $type = $stmt->getType();
                $defs = $this->getDefinedVars($stmt);
                $defStr = !empty($defs) ? ' [defs: $' . implode(', $', $defs) . ']' : '';
                $out .= "    [{$i}] {$type}{$defStr}\n";
            }

            if ($block->endsWithGoto) {
                $out .= "  Ends with goto {$block->gotoLabel}\n";
            }
            $out .= "\n";
        }

        $out .= "--- SSA Variables ---\n";
        foreach ($this->ssaVars as $ssaVar) {
            $flags = [];
            if ($ssaVar->flags & SsaFlags::REFERENCE) $flags[] = 'REF';
            if ($ssaVar->flags & SsaFlags::UNDEFINED) $flags[] = 'UNDEF';
            if ($ssaVar->flags & SsaFlags::ESCAPED) $flags[] = 'ESC';
            if ($ssaVar->flags & SsaFlags::PHI) $flags[] = 'PHI';
            if ($ssaVar->flags & SsaFlags::PARAM) $flags[] = 'PARAM';
            if ($ssaVar->flags & SsaFlags::KILLED) $flags[] = 'KILLED';
            $flagStr = !empty($flags) ? ' [' . implode('|', $flags) . ']' : '';
            $out .= "  s{$ssaVar->id} = \${$ssaVar->origName}{$flagStr}\n";
        }

        $out .= "\n--- Variable Def Blocks ---\n";
        foreach ($this->defBlocks as $varName => $blockIds) {
            $out .= "  \${$varName}: B" . implode(', B', $blockIds) . "\n";
        }

        return $out;
    }
}

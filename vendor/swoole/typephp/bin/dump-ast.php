#!/usr/bin/env php
<?php

/**
 * Dump the AST syntax tree structure of a PHP file.
 *
 * Usage: php bin/dump-ast.php <file.php>
 *
 * Examples:
 *   php bin/dump-ast.php examples/hello.php
 *   php bin/dump-ast.php src/compiler.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpParser\ParserFactory;
use PhpParser\Node;
use PhpParser\NodeAbstract;

// ─── Argument parsing ─────────────────────────────────────

if ($argc < 2) {
    fprintf(STDERR, "用法: php %s <file.php>\n", $argv[0]);
    exit(1);
}

$filePath = $argv[1];

if (!is_file($filePath)) {
    fprintf(STDERR, "错误: 文件不存在: %s\n", $filePath);
    exit(1);
}

if (!is_readable($filePath)) {
    fprintf(STDERR, "错误: 文件不可读: %s\n", $filePath);
    exit(1);
}

// ─── Parsing ──────────────────────────────────────────────

$code = file_get_contents($filePath);
if ($code === false) {
    fprintf(STDERR, "错误: 无法读取文件: %s\n", $filePath);
    exit(1);
}

$parserFactory = new ParserFactory();
$parser = $parserFactory->createForNewestSupportedVersion();

try {
    $stmts = $parser->parse($code);
} catch (\PhpParser\Error $e) {
    fprintf(STDERR, "解析错误: %s\n", $e->getMessage());
    exit(1);
}

// ─── AST structured output ────────────────────────────────

/**
 * Recursively convert an AST node into a readable array structure
 * (suitable for print_r / var_export output).
 */
function nodeToArray(NodeAbstract $node): array
{
    $result = [
        'type' => $node->getType(),
        'attributes' => [
            'startLine' => $node->getStartLine(),
            'endLine'   => $node->getEndLine(),
        ],
    ];

    // Child sub-nodes
    foreach ($node->getSubNodeNames() as $name) {
        $value = $node->$name;
        $result[$name] = valueToArray($value);
    }

    // Comments (if present)
    $comments = $node->getComments();
    if ($comments) {
        $result['attributes']['comments'] = [];
        foreach ($comments as $comment) {
            $result['attributes']['comments'][] = $comment->getText();
        }
    }

    return $result;
}

function valueToArray(mixed $value): mixed
{
    if ($value instanceof NodeAbstract) {
        return nodeToArray($value);
    }

    if (is_array($value)) {
        $arr = [];
        foreach ($value as $k => $v) {
            $arr[$k] = valueToArray($v);
        }
        // Determine whether the value is a list (consecutive integer keys)
        if (array_is_list($arr) && count($arr) <= 1) {
            return $arr;
        }
        // Non-lists or multi-element lists are kept as-is
        if (!array_is_list($arr)) {
            return $arr;
        }
        // Lists are returned as-is
        return $arr;
    }

    if (is_string($value)) {
        // Truncate overly long strings for display
        return mb_strlen($value) > 120 ? mb_substr($value, 0, 120) . '...' : $value;
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if ($value === null) {
        return 'null';
    }

    return $value;
}

// ─── Pretty printing ──────────────────────────────────────

/**
 * Print the AST structure with indentation, similar to JSON's visual style.
 */
function printAst(array $nodes, string $indent = ''): void
{
    foreach ($nodes as $i => $node) {
        if ($node instanceof NodeAbstract) {
            $arr = nodeToArray($node);
            printNodeArray($arr, $indent, $i);
        } elseif (is_array($node)) {
            echo $indent . "[$i] => array(\n";
            printAst($node, $indent . '    ');
            echo $indent . ")\n";
        } else {
            echo $indent . "[$i] => " . var_export($node, true) . "\n";
        }
    }
}

function printNodeArray(array $arr, string $indent, int|string $key): void
{
    $type = $arr['type'];
    $sl   = $arr['attributes']['startLine'];
    $el   = $arr['attributes']['endLine'];
    unset($arr['type'], $arr['attributes']);

    echo $indent . "[$key] => $type (L$sl-$el)\n";

    if (empty($arr)) {
        return;
    }

    foreach ($arr as $name => $value) {
        if ($value instanceof NodeAbstract) {
            $child = nodeToArray($value);
            echo $indent . "    $name => " . $child['type'] . " (\n";
            printNodeAttrs($child, $indent . '        ');
            echo $indent . "    )\n";
        } elseif (is_array($value)) {
            echo $indent . "    $name => array(" . (count($value) > 0 ? "\n" : ")\n");
            foreach ($value as $j => $item) {
                if ($item instanceof NodeAbstract || (is_array($item) && isset($item['type']))) {
                    $itemArr = $item instanceof NodeAbstract ? nodeToArray($item) : $item;
                    echo $indent . "        [$j] => " . $itemArr['type'] . " (\n";
                    printNodeAttrs($itemArr, $indent . '            ');
                    echo $indent . "        )\n";
                } else {
                    echo $indent . "        [$j] => " . var_export($item, true) . "\n";
                }
            }
            if (count($value) > 0) {
                echo $indent . "    )\n";
            }
        } else {
            echo $indent . "    $name => " . var_export($value, true) . "\n";
        }
    }
}

function printNodeAttrs(array $arr, string $indent): void
{
    $type = $arr['type'];
    $hasAttrs = !empty($arr['attributes']);
    $attrs    = $arr['attributes'] ?? [];
    unset($arr['type'], $arr['attributes']);

    echo $indent . "type: " . $type . "\n";
    echo $indent . "lines: " . $attrs['startLine'] . '-' . $attrs['endLine'] . "\n";

    if (isset($attrs['comments'])) {
        foreach ($attrs['comments'] as $c) {
            echo $indent . "comment: " . trim($c) . "\n";
        }
    }

    foreach ($arr as $name => $value) {
        if (is_array($value)) {
            echo $indent . "$name: " . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            echo $indent . "$name: " . var_export($value, true) . "\n";
        }
    }
}

// ──────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  文件: $filePath\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "AST_ROOT: Program (L1-" . count($stmts) . " statements)\n\n";

/**
 * Concise hierarchical output.
 */
function dumpNode(NodeAbstract $node, int $depth = 0): void
{
    $indent  = str_repeat('  ', $depth);
    $type    = $node->getType();
    $start   = $node->getStartLine();
    $end     = $node->getEndLine();
    $subInfo = '';

    // Extract key information for common node types.
    switch (true) {
        case $node instanceof Node\Expr\Variable:
            $subInfo = ' $' . ($node->name === null ? '(unset)' : (is_string($node->name) ? $node->name : '...'));
            break;
        case $node instanceof Node\Expr\FuncCall:
            $name = $node->name instanceof Node\Name ? implode('\\', $node->name->getParts()) : '...';
            $subInfo = " $name()";
            break;
        case $node instanceof Node\Expr\MethodCall:
            $name = is_string($node->name) ? $node->name : '...';
            $subInfo = " ->$name()";
            break;
        case $node instanceof Node\Expr\StaticCall:
            $class = $node->class instanceof Node\Name ? implode('\\', $node->class->getParts()) : '...';
            $name  = is_string($node->name) ? $node->name : '...';
            $subInfo = " $class::$name()";
            break;
        case $node instanceof Node\Expr\Assign:
            $subInfo = ' =';
            break;
        case $node instanceof Node\Scalar\String_:
            $val = mb_strlen($node->value) > 60 ? mb_substr($node->value, 0, 60) . '...' : $node->value;
            $subInfo = ' "' . $val . '"';
            break;
        case $node instanceof Node\Scalar\LNumber:
            $subInfo = ' ' . $node->value;
            break;
        case $node instanceof Node\Scalar\DNumber:
            var_dump($node->getAttribute('rawValue'));
            $subInfo = ' ' . $node->value;
            break;
        case $node instanceof Node\Expr\ConstFetch:
            $subInfo = ' ' . implode('\\', $node->name->getParts());
            break;
        case $node instanceof Node\Stmt\Function_:
            $subInfo = ' ' . $node->name->name . '()';
            break;
        case $node instanceof Node\Stmt\ClassMethod:
            $subInfo = ' ' . $node->name->name . '()';
            break;
        case $node instanceof Node\Stmt\Class_:
            $subInfo = ' ' . ($node->name?->name ?? '(anonymous)');
            break;
        case $node instanceof Node\Stmt\Namespace_:
            $subInfo = ' ' . implode('\\', $node->name->getParts());
            break;
        case $node instanceof Node\Stmt\If_:
            $subInfo = ' (cond)';
            break;
        case $node instanceof Node\Expr\BinaryOp\Concat:
            $subInfo = ' .';
            break;
        case $node instanceof Node\Expr\Array_:
            $subInfo = ' [' . count($node->items) . ' items]';
            break;
        case $node instanceof Node\Expr\ArrayDimFetch:
            $subInfo = '[]';
            break;
        case $node instanceof Node\Expr\PropertyFetch:
            $name = is_string($node->name) ? $node->name : '...';
            $subInfo = " ->$name";
            break;
        default:
            if ($node instanceof Node\Expr\BinaryOp) {
                $subInfo = ' (binop)';
            }
            break;
    }

    $line = $start === $end ? "L$start" : "L$start-$end";
    echo "{$indent}├── {$type} ({$line}){$subInfo}\n";

    foreach ($node->getSubNodeNames() as $name) {
        $value = $node->$name;
        if ($value instanceof NodeAbstract) {
            echo "{$indent}│   {$name}:\n";
            dumpNode($value, $depth + 2);
        } elseif (is_array($value) && !empty($value)) {
            $hasNode = false;
            foreach ($value as $item) {
                if ($item instanceof NodeAbstract) {
                    $hasNode = true;
                    break;
                }
            }
            if ($hasNode) {
                echo "{$indent}│   {$name}:\n";
                foreach ($value as $k => $item) {
                    if ($item instanceof NodeAbstract) {
                        dumpNode($item, $depth + 2);
                    }
                }
            }
        }
    }
}

foreach ($stmts as $i => $stmt) {
    dumpNode($stmt);
}

echo "\n";

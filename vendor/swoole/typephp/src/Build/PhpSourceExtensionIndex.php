<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;

final class PhpSourceExtensionIndex
{
    /** @var array<string, self> */
    private static array $instances = [];

    /** @var array<string, string> */
    private array $functions = [];

    /** @var array<string, string> */
    private array $classes = [];

    public static function forSource(string $sourceDirectory): self
    {
        return self::$instances[$sourceDirectory] ??= new self($sourceDirectory);
    }

    public function functionExtension(string $function): ?string
    {
        return $this->functions[strtolower(ltrim($function, '\\'))] ?? null;
    }

    public function classExtension(string $class): ?string
    {
        return $this->classes[strtolower(ltrim($class, '\\'))] ?? null;
    }

    private function __construct(string $sourceDirectory)
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        foreach (glob($sourceDirectory . '/ext/*') ?: [] as $extensionDirectory) {
            if (!is_dir($extensionDirectory)) {
                continue;
            }
            $extension = basename($extensionDirectory);
            foreach (glob($extensionDirectory . '/*.stub.php') ?: [] as $stub) {
                try {
                    $statements = $parser->parse((string) file_get_contents($stub));
                } catch (\Throwable) {
                    continue;
                }
                if (is_array($statements)) {
                    $this->indexStatements($statements, '', $extension);
                }
            }
        }
    }

    /** @param list<Node\Stmt> $statements */
    private function indexStatements(array $statements, string $namespace, string $extension): void
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Stmt\Namespace_) {
                $this->indexStatements(
                    $statement->stmts,
                    $statement->name?->toString() ?? '',
                    $extension,
                );
                continue;
            }
            if ($statement instanceof Stmt\Function_) {
                $this->functions[$this->qualified($namespace, $statement->name->toString())] = $extension;
                continue;
            }
            if ($statement instanceof Stmt\ClassLike && $statement->name !== null) {
                $this->classes[$this->qualified($namespace, $statement->name->toString())] = $extension;
                continue;
            }
            if (property_exists($statement, 'stmts') && is_array($statement->stmts)) {
                $this->indexStatements($statement->stmts, $namespace, $extension);
            }
        }
    }

    private function qualified(string $namespace, string $name): string
    {
        return strtolower($namespace === '' ? $name : $namespace . '\\' . $name);
    }
}

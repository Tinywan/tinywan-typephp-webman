--TEST--
Symfony Console TreeNode pattern: null coalesce assign and recursive iterable values
--FILE--
<?php

final class TreeNode
{
    private array $children = [];

    public function __construct(private string $value = 'root')
    {
    }

    public static function fromValues(iterable $nodes, ?self $node = null): self
    {
        $node ??= new self();

        foreach ($nodes as $key => $value) {
            if (is_iterable($value)) {
                $child = new self((string) $key);
                self::fromValues($value, $child);
                $node->addChild($child);
            } elseif ($value instanceof self) {
                $node->addChild($value);
            } else {
                $node->addChild(new self((string) $value));
            }
        }

        return $node;
    }

    public function addChild(self $child): void
    {
        $this->children[] = $child;
    }

    public function dump(int $level = 0): void
    {
        echo str_repeat('-', $level), $this->value, "\n";

        foreach ($this->children as $child) {
            $child->dump($level + 1);
        }
    }
}

function main(): void
{
    TreeNode::fromValues([
        'console' => ['input', 'output'],
        'http' => ['request' => ['query', 'headers']],
        new TreeNode('custom'),
    ])->dump();
}
?>
--EXPECT--
root
-console
--input
--output
-http
--request
---query
---headers
-custom

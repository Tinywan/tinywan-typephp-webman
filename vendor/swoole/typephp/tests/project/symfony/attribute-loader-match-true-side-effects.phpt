--TEST--
Symfony Serializer pattern: match true used for metadata side effects
--FILE--
<?php

class GroupsAttribute
{
    public function __construct(public array $groups)
    {
    }
}

class IgnoreAttribute
{
}

class MaxDepthAttribute
{
    public function __construct(public int $maxDepth)
    {
    }
}

class Metadata
{
    public array $groups = [];
    public bool $ignored = false;
    public ?int $maxDepth = null;

    public function addGroup(string $group): void
    {
        $this->groups[] = $group;
    }
}

function applyAttribute(object $attribute, Metadata $metadata): void
{
    match (true) {
        $attribute instanceof MaxDepthAttribute => $metadata->maxDepth = $attribute->maxDepth,
        $attribute instanceof IgnoreAttribute => $metadata->ignored = true,
        $attribute instanceof GroupsAttribute => array_map($metadata->addGroup(...), $attribute->groups),
        default => null,
    };
}

function main(): void
{
    $metadata = new Metadata();
    applyAttribute(new GroupsAttribute(['read', 'write']), $metadata);
    applyAttribute(new MaxDepthAttribute(3), $metadata);
    applyAttribute(new IgnoreAttribute(), $metadata);

    var_dump($metadata);
}
?>
--EXPECT--
object(Metadata)#1 (3) {
  ["groups"]=>
  array(2) {
    [0]=>
    string(4) "read"
    [1]=>
    string(5) "write"
  }
  ["ignored"]=>
  bool(true)
  ["maxDepth"]=>
  int(3)
}

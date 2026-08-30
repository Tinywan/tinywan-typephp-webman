--TEST--
Native class: isset and empty inspect native pointer slots without ZendVM
--FILE--
<?php

#[Native]
class NativeOptionalNode
{
    public ?NativeOptionalNode $next;
    public int $number = 0;
}

function main(): void
{
    $node = new NativeOptionalNode();
    var_dump(isset($node));
    var_dump(empty($node));
    var_dump(isset($node->next));
    var_dump(empty($node->next));
    var_dump(isset($node->next->next));
    var_dump(empty($node->next->next));
    var_dump(isset($node->number));
    var_dump(empty($node->number));

    $node->next = new NativeOptionalNode();
    var_dump(isset($node->next));
    var_dump(empty($node->next));
    var_dump(isset($node->next->next));
    var_dump(empty($node->next->next));

    $node = null;
    var_dump(isset($node));
    var_dump(empty($node));
}
?>
--EXPECT--
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)

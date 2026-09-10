--TEST--
std::object restores concrete object types and validates them at runtime
--FILE--
<?php

class StdObjectBase {
    public function name(): string {
        return 'base';
    }
}

class StdObjectChild extends StdObjectBase {
    public function childName(): string {
        return 'child';
    }

    public function fromSelf(mixed $value): self {
        return STD::ObJeCt($value, self::class);
    }

    public function fromParent(mixed $value): StdObjectBase {
        return std::object($value, parent::class);
    }
}

function restore_child(mixed $value): StdObjectChild {
    return std::object($value, StdObjectChild::class);
}

function restore_child_by_name(mixed $value): StdObjectChild {
    return std::object($value, 'StdObjectChild');
}

function main(): void {
    $child = new StdObjectChild();
    var_dump(restore_child($child)->childName());
    var_dump(restore_child_by_name($child)->childName());
    var_dump($child->fromSelf($child)->childName());
    var_dump($child->fromParent($child)->name());

    try {
        std::object(new stdClass(), StdObjectChild::class);
    } catch (TypeError $error) {
        echo $error::class, "\n";
    }

    try {
        std::object(42, StdObjectChild::class);
    } catch (TypeError $error) {
        echo $error::class, "\n";
    }
}
?>
--EXPECT--
string(5) "child"
string(5) "child"
string(5) "child"
string(4) "base"
TypeError
TypeError

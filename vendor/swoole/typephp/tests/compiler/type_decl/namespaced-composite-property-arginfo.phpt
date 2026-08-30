--TEST--
Namespaced union and intersection properties generate valid arginfo identifiers
--FILE--
<?php

namespace NamespacedArginfo {
    interface Left
    {
    }

    interface Right
    {
    }

    final class Both implements Left, Right
    {
    }

    final class Alternative
    {
    }

    final class Holder
    {
        public Left|Alternative $union;
        public Left&Right $intersection;

        public function __construct(Left|Alternative $union, Left&Right $intersection)
        {
            $this->union = $union;
            $this->intersection = $intersection;
        }
    }

    function run(): void
    {
        $both = new Both();
        $holder = new Holder(new Alternative(), $both);

        echo (new \ReflectionProperty(Holder::class, 'union'))->getType(), "\n";
        echo (new \ReflectionProperty(Holder::class, 'intersection'))->getType(), "\n";
        var_dump($holder->union instanceof Alternative);
        var_dump($holder->intersection instanceof Both);

        $holder->union = $both;
        var_dump($holder->union instanceof Both);
    }
}

namespace {
    function main(): void
    {
        \NamespacedArginfo\run();
    }
}
?>
--EXPECT--
NamespacedArginfo\Left|NamespacedArginfo\Alternative
NamespacedArginfo\Left&NamespacedArginfo\Right
bool(true)
bool(true)
bool(true)

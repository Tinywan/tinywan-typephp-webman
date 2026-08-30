<?php

namespace Demo\Dispatch {
    class ChildOverrideNs extends ParentBaseNs
    {
        public function bar(): void
        {
            echo "Child\n";
        }
    }

    class ParentBaseNs
    {
        public function run(): void
        {
            $this->bar();
        }

        public function bar(): void
        {
            echo "Parent\n";
        }
    }
}

namespace {
    function main(): void
    {
        $o = new Demo\Dispatch\ChildOverrideNs();
        $o->run();
    }
}

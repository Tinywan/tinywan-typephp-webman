<?php
// 子类先于父类定义，测试 classMethodOverride 的双向递归是否正确
class ChildOverride extends ParentBase {
    public function bar(): void {
        echo "Child\n";
    }
}

class ParentBase {
    public function run(): void {
        $this->bar();
    }

    public function bar(): void {
        echo "Parent\n";
    }
}

function main(): void {
    $o = new ChildOverride();
    $o->run();
}

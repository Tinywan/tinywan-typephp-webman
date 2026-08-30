<?php
class StackHolder
{
    public static array $stack = [];

    public static function push(string $item): void
    {
        self::$stack[] = $item;
    }

    public static function pop(): void
    {
        // 触发 bug: AOT 对 static 属性 array_pop 生成 php::toReference(v) 缺第 2 参数
        array_pop(self::$stack);
    }

    public static function count(): int
    {
        return count(self::$stack);
    }
}

// ── 对照：array_pop($localVar) 走不同代码生成路径，应该正常 ──
function local_pop_test(): string
{
    $arr = ['x', 'y', 'z'];
    array_pop($arr);

    if (count($arr) !== 2) {
        return 'FAIL: local_pop_test expected 2, got ' . count($arr);
    }
    if ($arr[0] !== 'x' || $arr[1] !== 'y') {
        return 'FAIL: local_pop_test wrong elements';
    }

    return 'OK';
}

function main(): int
{
    // 测试 static 属性（已知 bug，预期编译失败）
    StackHolder::push('a');
    StackHolder::push('b');
    StackHolder::pop();

    if (StackHolder::count() !== 1) {
        echo "FAIL: static_pop expected 1, got " . StackHolder::count() . "\n";
        return 1;
    }

    // 测试局部变量（应该正常）
    $result = local_pop_test();
    echo $result . "\n";
    if ($result !== 'OK') {
        return 1;
    }

    echo "ALL OK\n";
    return 0;
}
<?php

class UserTest {
    private string $name;

    #[Immutable]
    function foo(): void
    {
        // 不允许，方法是 Immutable 的，不可修改对象的属性
        $this->name = 'hello';
    }

    function bar(
        #[Immutable]
        string $name): void
    {
        // 允许，方法不是 Immutable 的
        $this->name = 'hello';
        // 不允许，$name 是 Immutable 的，不可修改
        $name = 'world';
    }
}
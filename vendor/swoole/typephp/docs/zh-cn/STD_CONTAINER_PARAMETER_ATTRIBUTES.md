# Std 容器参数类型注解

`StdArray`、`StdVector`、`StdMap`、`StdOrderedMap` 是 TypePHP 内置的 Box 容器类型注解。
它们描述容器种类和元素类型，并自动完成以前需要手写的 `toStd*()` 类型恢复。

本文记录现有 Box 容器参数实现。包含 `StdList` / `StdDict` 的统一规则见
[类型注解统一设计](TYPE_ANNOTATIONS.md)；拟议函数签名见 [StdFunc / StdArgInfo](STD_FUNC_DESIGN.md)。

```php
function append(#[StdVector(Type::Int)] $vec): void
{
    $vec[] = 42;
}

function update_matrix(#[StdArray(Type::Int, [2, 3])] box $matrix): void
{
    $matrix[1][2] = 42;
}

function update(#[StdMap(Type::String, User::class)] $users): void
{
    $users['alice'] = new User();
}

function visit(#[StdOrderedMap(Type::Int, Type::String)] $names): void
{
    foreach ($names as $key => $value) {
        echo $key, ':', $value, "\n";
    }
}
```

## 声明规则

- 一个参数只能有一个容器类型注解，不能重复或混用。
- 类型注解提供完整契约，PHP 参数类型可以省略或声明为兼容的 `box`；不能声明 `mixed`、`any`、`array`、Nullable、联合类型或其他不兼容类型。
- `StdArray` 接受叶子类型及单长度或外到内维度数组；`StdVector` 接受一个类型实参；`StdMap`、`StdOrderedMap` 接受 key/value 两个类型实参。
- 类型实参使用现有 std 工厂支持的 `Type::*` 或 `ClassName::class`；map 的 key 只支持 `Type::Int`、`Type::String`。
- 使用位置为具名函数和方法参数，包括接口、抽象方法和 trait 方法；支持 Attribute 别名导入。
- 首版不支持引用、可变、带默认值、构造器属性提升参数，以及 Closure、箭头函数和 Generator 参数。
- 容器内不能保存 Native 对象并通过 Box 参数边界传递；原有 Native 容器逃逸限制不变。
- 参数绑定不能换成其他 Box/值、`unset()` 或被 Closure 按引用捕获；同类型 std 容器赋值仍按现有规则复制内容。元素的读取、更新、追加和遍历继续遵循现有 std 容器规则。

以下声明会产生编译错误：

```php
function invalid(#[StdVector(Type::Int)] mixed $vec): void {}
```

## 调用和生命周期

函数的底层调用 ABI 仍为 `php::Var`，容器仍由 Box 管理生命周期。编译器在入口
检查 Box、容器种类及元素类型，取得具体 C++ 容器引用；不复制容器，也不逐个转换元素。
错误的容器类型、PHP 数组及其他非 Box 实参会产生 `TypeError`。

函数中修改容器内容时，调用者可观察到相同修改：

```php
$vec = std::vector(Type::Int);
append($vec);
var_dump($vec[0]); // int(42)
```

现有动态调用把已知 std 容器转换为 PHP 数组的规则不变。如果通过动态 callable
调用这类函数，需传入实际的 Box 值，而不是已经转换成 PHP 数组的值。
注解不会让普通 Zend PHP 获得 C++ 泛型容器支持，也不会把 C++ 模板类型变成 PHP 的原生参数类型。

容器契约保存在声明缓存和导出的 library stub 中。修改参数注解会使源文件及依赖它的
调用方重新转换；仅复用缓存时，入口类型恢复仍然保留。

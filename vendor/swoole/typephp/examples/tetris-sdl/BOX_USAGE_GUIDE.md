# Box 机制使用指南

## 📌 核心概念

Box 是 PHPX 编译器提供的 C++ 对象封装机制，允许将 C++ 对象安全地传递给 PHP 层使用。

## ✅ 正确的使用方法

### 1. C++ 类定义

**必须继承自 `Box` 类：**

```cpp
#include <phpx.h>

using namespace php;

class TetrisBox : public Box {
public:
    int board[20][10];
    int score;
    bool gameOver;
    
    TetrisBox() : score(0), gameOver(false) {
        memset(board, 0, sizeof(board));
    }
    
    void reset() {
        score = 0;
        gameOver = false;
        memset(board, 0, sizeof(board));
    }
};
```

### 2. 创建并返回 Box 对象

**使用 `{new ClassName()}` 语法：**

```cpp
var php_tetris_new() {
    return {new TetrisBox()};  // ✅ 正确：使用花括号包装 new 表达式
}
```

❌ **错误做法：**
```cpp
// 错误 1：直接使用 var() 包装指针
var php_tetris_new() {
    auto* state = new TetrisBox();
    return var(state);  // ❌ 这不是 Box 类型
}

// 错误 2：返回整数 ID
Int php_tetris_new() {
    return 1;  // ❌ 失去了 Box 的意义
}
```

### 3. 从 Variant 提取 Box 对象

**使用 `box.toBox<ClassName>()` 方法：**

```cpp
void php_tetris_reset(var box) {
    auto tetris = box.toBox<TetrisBox>();  // ✅ 正确：使用 toBox 模板方法
    tetris->reset();
}

Int php_tetris_get_score(var box) {
    auto tetris = box.toBox<TetrisBox>();  // ✅ 正确
    return tetris->score;
}
```

❌ **错误做法：**
```cpp
// 错误：直接使用 ptr() 获取指针
void php_tetris_reset(var box) {
    auto* state = (TetrisBox*)box.ptr();  // ❌ 不安全，不是正确的 Box 转换方式
    state->reset();
}
```

### 4. Stub 文件声明

**使用 `mixed` 类型表示 Box 对象：**

```php
<?php

// 返回 Box 对象的函数
function tetris_new(): mixed {}

// 接收 Box 对象的函数
function tetris_reset(mixed $game): void {}
function tetris_get_score(mixed $game): int {}
function tetris_is_game_over(mixed $game): bool {}
```

⚠️ **重要：** 
- C++ 的 `var` 类型在 stub 文件中必须声明为 `mixed`
- 不能使用 `object` 或其他类型

### 5. PHP 层使用

```php
<?php

class TetrisGame
{
    private mixed $game;  // ✅ 使用 mixed 类型存储 Box 对象
    
    public function __construct()
    {
        $this->game = tetris_new();  // 接收 Box 对象
    }
    
    public function getScore(): int
    {
        return tetris_get_score($this->game);  // 传递 Box 对象给 C++
    }
    
    public function reset(): void
    {
        tetris_reset($this->game);  // 传递 Box 对象给 C++
    }
}
```

## 🔑 关键要点总结

### 类型映射规则

| C++ 类型 | Stub 类型 | PHP 类型 | 说明 |
|---------|----------|---------|------|
| `var` | `mixed` | `mixed` | Box 对象或任意类型 |
| `Variant` | `mixed` | `mixed` | 同上（var 是 Variant 的别名） |
| `Int` | `int` | `int` | 整数 |
| `Bool` | `bool` | `bool` | 布尔值 |
| `String` | `string` | `string` | 字符串 |
| `Array` | `array` | `array` | 数组 |

### Box 使用三步曲

1. **定义类**：继承自 `Box`
   ```cpp
   class MyBox : public Box { ... };
   ```

2. **创建对象**：使用 `{new MyBox()}` 返回
   ```cpp
   var php_my_new() {
       return {new MyBox()};
   }
   ```

3. **提取对象**：使用 `box.toBox<MyBox>()`
   ```cpp
   void php_my_method(var box) {
       auto obj = box.toBox<MyBox>();
       obj->doSomething();
   }
   ```

## 📝 完整示例

### C++ 实现 (tetris.cc)

```cpp
#include <phpx.h>
#include <cstring>

using namespace php;

class TetrisBox : public Box {
public:
    int score;
    bool gameOver;
    
    TetrisBox() : score(0), gameOver(false) {}
    
    void reset() {
        score = 0;
        gameOver = false;
    }
};

// 创建游戏实例
var php_tetris_new() {
    return {new TetrisBox()};
}

// 重置游戏
void php_tetris_reset(var box) {
    auto tetris = box.toBox<TetrisBox>();
    tetris->reset();
}

// 获取分数
Int php_tetris_get_score(var box) {
    auto tetris = box.toBox<TetrisBox>();
    return tetris->score;
}

// 检查游戏结束
Bool php_tetris_is_game_over(var box) {
    auto tetris = box.toBox<TetrisBox>();
    return tetris->gameOver;
}
```

### Stub 文件 (tetris.stub.php)

```php
<?php

function tetris_new(): mixed {}
function tetris_reset(mixed $game): void {}
function tetris_get_score(mixed $game): int {}
function tetris_is_game_over(mixed $game): bool {}
```

### PHP 调用 (main.php)

```php
<?php

$game = tetris_new();  // 创建 Box 对象

echo "初始分数: " . tetris_get_score($game) . "\n";

// 游戏逻辑...
tetris_reset($game);  // 重置游戏

if (tetris_is_game_over($game)) {
    echo "游戏结束！\n";
}
```

## ⚠️ 常见错误

### 错误 1：忘记继承 Box

```cpp
// ❌ 错误
class TetrisBox {  // 没有继承 Box
    int score;
};

// ✅ 正确
class TetrisBox : public Box {
    int score;
};
```

### 错误 2：使用错误的返回语法

```cpp
// ❌ 错误
var php_tetris_new() {
    return new TetrisBox();  // 缺少花括号
}

// ✅ 正确
var php_tetris_new() {
    return {new TetrisBox()};  // 使用花括号
}
```

### 错误 3：Stub 类型不匹配

```php
// ❌ 错误
function tetris_new(): object {}  // 不能用 object
function tetris_reset(object $game): void {}

// ✅ 正确
function tetris_new(): mixed {}
function tetris_reset(mixed $game): void {}
```

### 错误 4：使用 ptr() 而非 toBox()

```cpp
// ❌ 错误
void php_tetris_reset(var box) {
    auto* tetris = (TetrisBox*)box.ptr();  // 不安全
}

// ✅ 正确
void php_tetris_reset(var box) {
    auto tetris = box.toBox<TetrisBox>();  // 安全的类型转换
}
```

## 🎯 最佳实践

1. **始终使用 `toBox<T>()`**：这是类型安全的转换方法
2. **Stub 中使用 `mixed`**：对应 C++ 的 `var`/`Variant` 类型
3. **PHP 中使用 `mixed` 类型提示**：保持类型一致性
4. **添加空指针检查**（可选）：
   ```cpp
   void php_tetris_reset(var box) {
       if (!box.isResource()) {
           throw Exception("Invalid game object");
       }
       auto tetris = box.toBox<TetrisBox>();
       tetris->reset();
   }
   ```

## 📚 参考资料

- `examples/prime` - Box 机制的标准示例
- `examples/tetris` - 本项目的完整实现
- PHPX 编译器文档

---

**记住：正确的 Box 使用方式是 `box.toBox<T>()`，而不是 `box.ptr()`！**

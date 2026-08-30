# PHP AOT 编译器测试运行指南

## 测试文件位置

所有测试文件位于 `tests/compiler/` 目录下，使用 `.phpt` 扩展名。

## 运行测试

### 运行单个测试

```bash
# 运行特定测试文件
php run-tests.php tests/compiler/test-name.phpt

# 示例：运行 match expression 测试
php run-tests.php tests/compiler/match-expression.phpt
```

### 运行多个测试

```bash
# 运行多个测试（使用通配符）
php run-tests.php tests/compiler/pattern*.phpt

# 运行整个目录
php run-tests.php tests/compiler/
```

### 常用选项

```bash
# 显示详细信息
php run-tests.php tests/compiler/test-name.phpt -v

# 并行运行测试（加快测试速度）
php run-tests.php tests/compiler/ -j4

# 只显示失败的测试
php run-tests.php tests/compiler/ -g FAIL

# 保持测试生成的文件
php run-tests.php tests/compiler/test-name.phpt --keep-all

# 显示内存使用情况
php run-tests.php tests/compiler/test-name.phpt -m
```

## 新增测试用例说明

本次新增的 16 个测试文件覆盖了以下 PHP 8.x 特性：

| 测试文件 | 测试内容 | PHP 版本 |
|---------|---------|---------|
| trait-basic.phpt | Traits 基础 | 7.4+ |
| generators.phpt | 生成器 | 7.4+ |
| attributes.phpt | 注解/属性 | 8.0+ |
| match-expression.phpt | Match 表达式 | 8.0+ |
| named-arguments.phpt | 命名参数 | 8.0+ |
| union-intersection-types.phpt | 联合类型 | 8.0+ |
| constructor-promotion.phpt | 构造函数提升 | 8.0+ |
| null-coalescing.phpt | 空合并运算符 | 7.4+ |
| array-spread.phpt | 数组展开 | 7.4+ |
| arrow-functions.phpt | 箭头函数 | 8.0+ |
| magic-methods.phpt | 魔术方法 | 7.4+ |
| iterators.phpt | 迭代器 | 7.4+ |
| anonymous-classes.phpt | 匿名类 | 7.4+ |
| type-declarations.phpt | 类型声明 | 7.4+ |
| late-static-binding.phpt | 后期静态绑定 | 7.4+ |
| variadic-functions.phpt | 可变参数函数 | 7.4+ |

## PHPT 文件格式

每个测试文件包含三个主要部分：

```php
--TEST--
测试描述

--FILE--
<?php
// PHP 测试代码
?>

--EXPECT--
期望的输出结果
```

## 编写新的测试

### 基本步骤

1. 在 `tests/compiler/` 目录创建新文件，如 `my-feature.phpt`

2. 添加测试头部：
   ```
   --TEST--
   My Feature Test
   ```

3. 添加测试代码：
   ```php
   --FILE--
   <?php
   // 你的测试代码
   function main() {
       // 测试逻辑
   }
   ?>
   ```

4. 添加期望输出：
   ```
   --EXPECT--
   期望的输出结果
   ```

### 注意事项

1. **main 函数**: 大多数测试需要在 `main()` 函数中执行代码
2. **输出匹配**: EXPECT 部分必须与实际输出完全匹配（包括空格和换行）
3. **var_dump**: 使用 `var_dump()` 来输出变量类型和值
4. **错误处理**: 对于预期会抛出异常的测试，使用 try-catch 包裹

### 示例模板

```php
--TEST--
Feature Name

--FILE--
<?php
// 定义类或函数
class MyClass {
    public function test() {
        return "result";
    }
}

function main() {
    $obj = new MyClass();
    var_dump($obj->test());
}
?>

--EXPECT--
string(6) "result"
```

## 调试失败的测试

### 查看差异

```bash
php run-tests.php tests/compiler/failed-test.phpt -v
```

输出会显示期望值和实际值的差异：
```
========DIFF========
Expected:
string(6) "result"

Actual:
string(7) "results"
```

### 查看生成的文件

```bash
# 保持生成的 C++ 文件
php run-tests.php tests/compiler/test-name.phpt --keep-all

# 查看生成的 C++ 代码
cat build/tests/compiler/test-name.cc
```

### 手动编译和运行

```bash
# 1. 生成 stub 文件
php bin/gen_stub.php -f tests/compiler/test-name.php

# 2. 转换为 C++
php bin/tpc.php tests/compiler/test-name.php

# 3. 编译 C++ 代码
cd build && make test-name

# 4. 运行编译后的程序
./build/test-name
```

## 测试覆盖率检查

覆盖清单直接从 PHPT 和相关 PHPUnit fixture 的源码生成：

```bash
# 查看带明确分母的摘要
php bin/analyze-test-coverage.php

# 生成 PHP 版本 × 特性 × 三类测试证据的完整矩阵
php bin/analyze-test-coverage.php \
  --format=markdown \
  --output=build/test-coverage.md

# CI 使用 JSON，并在解析问题或失效 fixture 引用时失败
php bin/analyze-test-coverage.php \
  --format=json \
  --output=build/test-coverage.json \
  --strict
```

报告分别计算 AST 节点、正向编译、运行语义和负向诊断覆盖率，并明确列出每个分母；不会生成含义不清的单一总百分比。分类规则及 JSON 字段说明见 [测试覆盖清单](../../docs/TEST_COVERAGE_ANALYZER.md)。

## 常见问题

### Q: 测试失败，显示 "Not implemented" 错误
A: 这说明该 PHP 特性尚未被 AOT 编译器支持。请查看编译器的 TODO 列表或提交 issue。

### Q: 字符串长度不匹配
A: PHP 8.x 的字符串输出可能会截断，调整 EXPECT 部分的字符串长度以匹配实际输出。

### Q: 如何跳过某些测试？
A: 在测试文件中添加 `--SKIPIF--` 部分：
```php
--SKIPIF--
<?php
if (!feature_supported()) {
    echo "skip Feature not supported";
}
?>
```

### Q: 如何测试性能？
A: 使用 `--TIMEOUT--` 设置超时时间，并在测试中包含性能基准：
```php
--TIMEOUT--
5
--FILE--
<?php
$start = microtime(true);
// 性能测试代码
$duration = microtime(true) - $start;
var_dump($duration < 1.0); // 应该在 1 秒内完成
?>
```

## 贡献测试

欢迎提交新的测试文件！请遵循以下准则：

1. **文件命名**: 使用小写字母和连字符，如 `new-feature.phpt`
2. **测试描述**: 在 TEST 部分清晰描述测试内容
3. **覆盖全面**: 测试正常情况、边界情况和错误情况
4. **代码简洁**: 保持测试代码简单明了
5. **输出准确**: 确保 EXPECT 部分与实际输出完全匹配

## 更多信息

- PHP 官方测试套件文档：https://github.com/php/php-src/blob/master/run-tests.php
- PHP AOT 编译器文档：查看项目根目录的 README
- 问题反馈：提交 issue 到项目仓库

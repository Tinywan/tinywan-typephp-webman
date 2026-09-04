# PHP AOT 编译器不支持语法测试标记说明

## 概述

本文档说明为什么某些测试文件被标记为 SKIP，以及如何识别这些测试。

---

## 🚫 已标记为 SKIP 的测试文件

### 1. generators.phpt
- **原因**: Generator yield 语法不支持
- **Skip 信息**: `skip Generator syntax not supported in AOT`
- **PHP 版本**: 5.5+
- **详细说明**: 生成器需要运行时协程支持，与 AOT 静态编译冲突

### 2. attributes.phpt  
- **原因**: 类的注解/属性语法不支持
- **Skip 信息**: `skip Attributes/Annotations not supported in AOT`
- **PHP 版本**: 8.0+
- **详细说明**: Attribute 需要完整的反射 API 和运行时元数据查询支持

### 3. trait-basic.phpt
- **原因**: Traits 尚未支持（计划中）
- **Skip 信息**: `skip Traits not yet supported in AOT`
- **PHP 版本**: 5.4+
- **详细说明**: Trait 的代码注入机制复杂，正在开发中

### 4. prop-001.phpt
- **原因**: 特定属性访问模式不支持
- **Skip 信息**: `skip: not supported`
- **详细说明**: 复杂的动态属性访问链不支持

### 5. innerHTML 相关测试
- **原因**: innerHTML DOM 操作不支持
- **Skip 信息**: `skip innerHTML and DOM manipulation not supported in AOT`
- **详细说明**: JavaScript 风格的 DOM 操作不是 PHP 原生功能

### 6. 游离代码测试
- **原因**: 全局可执行表达式不支持
- **Skip 信息**: `skip Free-floating code not allowed, must be in function/method`
- **详细说明**: 所有可执行表达式必须在函数或类的方法中

---

## 📋 Skip 标记格式

所有不支持的测试文件都在 `--FILE--` 之前添加了 `--SKIPIF--` 部分：

```php
--TEST--
测试名称

--SKIPIF--
<?php
echo "skip 不支持的原因";
?>

--FILE--
<?php
// 测试代码
?>

--EXPECT--
期望输出
```

---

## 🔍 如何识别 Skip 测试

### 方法 1: 查看文件头
检查测试文件是否包含 `--SKIPIF--` 部分

### 方法 2: 运行测试时观察
```bash
php run-tests.php tests/compiler/generators.phpt
```
输出会显示：
```
TEST /home/swoole/workspace/aot/tests/compiler/generators.phpt
SKIP Generator syntax not supported in AOT  [tests/compiler/generators.phpt]
```

### 方法 3: 统计 Skip 测试数量
```bash
grep -l "^--SKIPIF--" tests/compiler/*.phpt | wc -l
```

---

## 📊 Skip 测试统计

| 类别 | 测试文件 | Skip 原因 |
|------|---------|----------|
| Generator | generators.phpt | 运行时协程不支持 |
| Attributes | attributes.phpt | 反射 API 不支持 |
| Traits | trait-basic.phpt | 开发中，尚未完成 |
| Property Access | prop-001.phpt | 动态属性访问链不支持 |
| Closure Reference | ref-closure-param.phpt | 引用参数闭包不支持 |
| DOM Manipulation | innerHTML 相关测试 | JavaScript DOM API 不支持 |
| Free-floating Code | 游离代码测试 | 全局可执行表达式不支持 |
| **总计** | **7** | **-** |

---

## ⚠️ 注意事项

### 对于开发者
1. **不要删除 Skip 标记**: 这些标记说明该语法当前不被支持
2. **参考文档**: 查看 `docs/UNSUPPORTED_SYNTAX.md` 了解详细信息
3. **使用替代方案**: 文档中提供了每种不支持语法的替代方案

### 对于测试人员
1. **验证 Skip 原因**: 确保 skip 信息准确描述不支持的原因
2. **更新标记**: 当某个语法被支持后，及时移除 skip 标记
3. **报告问题**: 发现应该 skip 但没有标记的测试时及时报告

### 对于贡献者
1. **实现功能**: 完成不支持语法的实现后，移除对应的 skip 标记
2. **验证功能**: 移除 skip 后确保测试能够通过
3. **更新文档**: 同步更新 `docs/UNSUPPORTED_SYNTAX.md`

---

## 🔄 状态变更流程

### 从 Skip 到 Pass

当某个语法被支持后：

1. **移除 Skip 标记**
   ```php
   --TEST--
   Test Name
   
   - --SKIPIF--
   - <?php
   - echo "skip ...";
   - ?>
   --FILE--
   ```

2. **运行测试验证**
   ```bash
   php run-tests.php tests/compiler/test-name.phpt -v
   ```

3. **确认测试通过**
   ```
   TEST /path/to/test-name.phpt
   PASS Test Name
   ```

4. **更新文档**
   - 在 `docs/UNSUPPORTED_SYNTAX.md` 中将语法移到"已支持"部分
   - 更新统计信息

---

## 📝 添加新的 Skip 测试

如果发现新的不支持语法，按以下步骤添加 skip 标记：

1. **编辑测试文件**，添加 `--SKIPIF--` 部分
2. **说明 skip 原因**，使用清晰的描述
3. **更新本文档**，记录新的 skip 测试
4. **更新规范文档**，在 `docs/UNSUPPORTED_SYNTAX.md` 中添加说明

示例：
```php
--TEST--
New Feature Test

--SKIPIF--
<?php
echo "skip New feature not supported in AOT";
?>

--FILE--
<?php
// Test code
?>

--EXPECT--
Expected output
```

---

## 🔗 相关文档

- [语法支持规范](../docs/UNSUPPORTED_SYNTAX.md) - 详细的语法支持情况
- [测试运行指南](RUN_TESTS_GUIDE.md) - 如何运行和调试测试
- [测试覆盖总结](README_TEST_COVERAGE.md) - 整体测试覆盖情况

---

## ❓ 常见问题

### Q: 为什么要把不支持的测试放在这里？
A: 保留这些测试可以作为未来的开发参考，并且可以在功能实现后立即启用。

### Q: Skip 测试会影响测试结果吗？
A: 不会。Skip 测试会被正确识别并单独统计，不会影响 pass/fail 的比例。

### Q: 如何查看所有 skip 测试？
A: 运行 `php run-tests.php tests/compiler/ -g SKIP` 可以查看所有 skip 测试及其原因。

### Q: 什么时候会移除 skip 标记？
A: 当对应的语法被实现并通过测试验证后，会移除 skip 标记。

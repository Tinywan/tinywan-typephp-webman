# 测试覆盖清单

`bin/analyze-test-coverage.php` 从 PHPT 和编译器 PHPUnit fixture 的源码生成覆盖清单。它是静态测试意图分析工具，不替代测试执行。

## 使用

```bash
# 终端摘要
php bin/analyze-test-coverage.php

# 可审阅的完整矩阵
php bin/analyze-test-coverage.php \
  --format=markdown \
  --output=build/test-coverage.md

# 供 CI 或其他工具读取
php bin/analyze-test-coverage.php \
  --format=json \
  --output=build/test-coverage.json \
  --strict
```

默认扫描 `tests/compiler`、`phpunit/src` 和 `phpunit/code`。也可以在命令末尾传入一个或多个 PHPT 文件或目录；`--no-phpunit` 只分析 PHPT，`--php-versions=8.4,8.5` 设置矩阵的 PHP 版本列。

`--strict` 在存在非预期的源码解析失败或无法解析的 PHPUnit fixture 引用时返回非零状态。负向数据提供器中故意不能被 php-parser 接受的样本会单独记入 `expected_parser_diagnostics`，不会伪装成工具故障。

## 三类覆盖证据

每个适用的 `PHP 版本 × 特性` 行分别记录：

- `positive_compile`：有效 PHPT，或正向 PHPUnit 编译 fixture；
- `runtime_semantics`：含 `EXPECT`、`EXPECTF` 或 `EXPECTREGEX` 的有效 PHPT；
- `negative_diagnostic`：期待诊断的 PHPT，或明确期待失败的 PHPUnit 测试/数据提供器。

`XFAIL` 和无条件 `SKIPIF` 不计入任何证据轴。PHP 版本范围从测试标题、`SKIPIF` 中的 `PHP_VERSION_ID` 条件以及 PHPUnit 数据行中的版本字符串推断。

## 分母

报告只给出带明确分母的比率：

- AST 节点覆盖分母：当前安装的 `nikic/php-parser` 所提供的具体 AST 节点种类；用于错误恢复的 `Expr_Error` 不计入。
- 特性轴覆盖分母：特性目录中 `introduced <= 目标 PHP 版本` 的行数。每个正向编译、运行语义和负向诊断轴独立计算。

工具不会把不同含义的三个轴合成一个“项目总覆盖率”。完整 JSON 同时保留特性目录、逐项证据来源、矩阵、AST 节点出现次数、解析问题和排除原因，便于 CI 进一步检查。

## 分类边界

AST 节点由 parser 自动提取。无法只靠节点区分的语义特性（例如 DNF 出现位置、属性 hook 变体、`exit(message: ...)`）由分析器中的显式特性目录补充。新增语言特性时应同时登记其引入版本和检测规则，以维持版本矩阵的明确分母。

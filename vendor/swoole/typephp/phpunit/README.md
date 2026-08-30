# PHPUnit 单元测试指南

## 概述

本项目使用 PHPUnit 进行单元测试，覆盖重构的 Platform 和 Backend 层代码。

## 测试文件结构

```
phpunit/
├── bootstrap.php                    # 测试引导文件
├── src/                             # 测试文件目录
│   ├── Platform/
│   │   └── PlatformTest.php        # Platform 层测试 (283行)
│   ├── Backend/
│   │   └── BackendTest.php         # Backend 层测试 (427行)
│   ├── FactoryTest.php             # 工厂类测试 (176行)
│   └── CompilerBaseAdapterTest.php # CompilerBase 适配器测试 (174行)
└── code/                            # 测试用例代码目录
```

## 测试覆盖范围

### 1. Platform 层测试 (PlatformTest.php)

**Windows Platform:**
- ✅ 基本信息（名称、扩展名、路径分隔符）
- ✅ 包含路径格式化
- ✅ 库路径格式化
- ✅ 库文件格式化
- ✅ 路径规范化
- ✅ 路径组合
- ✅ 子系统选项
- ✅ CRT 配置
- ✅ 调试选项

**Linux Platform:**
- ✅ 基本信息
- ✅ 包含路径格式化
- ✅ 库路径格式化
- ✅ 库文件格式化（自动去除 lib 前缀和扩展名）
- ✅ RPATH 选项
- ✅ PIC 标志
- ✅ 共享库链接标志

**macOS Platform:**
- ✅ 基本信息
- ✅ install_name 选项
- ✅ 动态库链接标志

**通用测试:**
- ✅ 空数组处理

**总计：23 个测试方法**

### 2. Backend 层测试 (BackendTest.php)

**MSVC Backend:**
- ✅ 编译器基本信息
- ✅ 编译单个文件
- ✅ 链接对象文件
- ✅ 完整编译命令
- ✅ 完整链接命令
- ✅ 完整编译选项（含 ZTS、Sanitizer、警告屏蔽等）
- ✅ 调试模式编译选项
- ✅ 完整链接选项（含子系统、DLL等）

**GCC Backend:**
- ✅ 编译器基本信息
- ✅ 编译单个文件
- ✅ 完整编译选项（含优化、Sanitizer、PIC等）
- ✅ 调试模式编译选项
- ✅ 完整链接选项（含共享库、RPATH等）

**Clang Backend:**
- ✅ 编译器基本信息
- ✅ Windows 平台链接器检测
- ✅ Unix 编译选项
- ✅ Windows 编译选项（MSVC 兼容模式）
- ✅ Windows 链接选项
- ✅ Unix 链接选项

**通用测试:**
- ✅ 不同优化级别（O0-O3）
- ✅ 默认值测试

**总计：24 个测试方法**

### 3. 工厂类测试 (FactoryTest.php)

**PlatformFactory:**
- ✅ 自动检测当前平台
- ✅ 平台判断方法
- ✅ 获取平台名称

**CompilerFactory:**
- ✅ 自动创建编译器
- ✅ 按名称创建编译器（MSVC、GCC、Clang）
- ✅ 不支持的编译器错误处理
- ✅ 自动检测（带和不带指定编译器）
- ✅ 平台与编译器匹配（Windows+MSVC, Linux+GCC, macOS+Clang）
- ✅ 编译器获取平台实例

**总计：13 个测试方法**

### 4. CompilerBase 适配器测试 (CompilerBaseAdapterTest.php)

- ✅ CompilerBase 初始化新架构
- ✅ parseIncludes 使用新架构
- ✅ parseLdflags 使用新架构
- ✅ parseLibs 使用新架构
- ✅ 平台检测方法一致性
- ✅ 向后兼容性

**总计：6 个测试方法**

## 运行测试

### 运行所有测试

```bash
cd D:\workspace\compiler
php vendor/bin/phpunit phpunit/
```

### 运行特定测试文件

```bash
# 运行 Platform 测试
php vendor/bin/phpunit phpunit/src/Platform/PlatformTest.php

# 运行 Backend 测试
php vendor/bin/phpunit phpunit/src/Backend/BackendTest.php

# 运行工厂测试
php vendor/bin/phpunit phpunit/src/FactoryTest.php

# 运行适配器测试
php vendor/bin/phpunit phpunit/src/CompilerBaseAdapterTest.php
```

### 运行特定测试方法

```bash
# 运行 Windows 平台测试
php vendor/bin/phpunit --filter testWindowsBasic phpunit/src/Platform/PlatformTest.php

# 运行 MSVC 编译测试
php vendor/bin/phpunit --filter testMsvcCompileFile phpunit/src/Backend/BackendTest.php
```

### 生成代码覆盖率报告

```bash
php vendor/bin/phpunit --coverage-html coverage phpunit/
```

然后打开 `coverage/index.html` 查看详细的覆盖率报告。

## 测试统计

| 测试文件 | 测试方法数 | 代码行数 | 说明 |
|---------|-----------|---------|------|
| PlatformTest.php | 23 | 283 | Platform 层完整测试 |
| BackendTest.php | 24 | 427 | Backend 层完整测试 |
| FactoryTest.php | 13 | 176 | 工厂类测试 |
| CompilerBaseAdapterTest.php | 6 | 174 | 适配器层测试 |
| **总计** | **66** | **1,060** | **完整测试套件** |

## 测试原则

### 1. 独立性
每个测试方法都是独立的，不依赖其他测试的执行结果。

### 2. 可重复性
测试应该在任何环境下都能产生相同的结果。

### 3. 自包含
测试应该包含所有必要的设置和清理逻辑。

### 4. 清晰的断言
每个断言都应该有明确的目的，失败时能提供有用的信息。

## 最佳实践

### 1. 测试命名
- 使用描述性的测试方法名
- 格式：`test[功能][场景][预期结果]`
- 示例：`testWindowsIncludeFlags`, `testMsvcDebugCompileOptions`

### 2. 测试组织
- 按功能分组相关测试
- 使用注释分隔不同的测试组
- 保持测试方法的简洁性

### 3. 断言选择
- 使用最具体的断言方法
- 提供有意义的失败消息
- 避免过度断言

### 4. 测试数据
- 使用有意义的数据
- 覆盖边界情况
- 包括正常和异常情况

## 持续集成

### GitHub Actions 示例

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ${{ matrix.os }}
    
    strategy:
      matrix:
        os: [windows-latest, ubuntu-latest, macos-latest]
        php-version: ['8.4', '8.5']
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
    
    - name: Install dependencies
      run: composer install
    
    - name: Run tests
      run: php vendor/bin/phpunit phpunit/
```

## 常见问题

### Q: 测试失败怎么办？

A: 
1. 检查错误消息
2. 确认测试环境配置正确
3. 验证被测试代码的逻辑
4. 必要时更新测试用例

### Q: 如何添加新测试？

A:
1. 确定要测试的功能
2. 在相应的测试文件中添加测试方法
3. 遵循现有的测试风格和命名规范
4. 确保测试独立且可重复

### Q: 测试覆盖率目标是多少？

A:
- Platform 层：100%
- Backend 层：100%
- Factory 层：100%
- Adapter 层：80%+

## 下一步

1. ✅ 完成 Platform 层测试
2. ✅ 完成 Backend 层测试
3. ✅ 完成工厂类测试
4. ✅ 完成适配器测试
5. ⏳ 添加更多边缘情况测试
6. ⏳ 添加性能测试
7. ⏳ 集成到 CI/CD 流程

## 总结

本次创建的测试套件：
- ✅ 66 个测试方法
- ✅ 1,060 行测试代码
- ✅ 覆盖所有重构的核心功能
- ✅ 遵循 PHPUnit 最佳实践
- ✅ 支持跨平台测试

这是一个**生产就绪**的测试套件！🚀

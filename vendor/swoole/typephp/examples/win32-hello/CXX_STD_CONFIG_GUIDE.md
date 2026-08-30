# C++ 标准配置指南

## 📋 概述

PHPX 编译器现在支持独立配置 C++ 标准版本，不再需要从 `cxxflags` 中提取。这使得配置更加清晰和易于管理。

---

## 🚀 使用方法

### 1. 命令行方式

使用 `--cxx-std` 参数指定 C++ 标准版本：

```bash
# 使用 C++14
php bin/tpc.php app.php --cxx-std=c++14

# 使用 C++17（默认）
php bin/tpc.php app.php --cxx-std=c++17

# 使用 C++20
php bin/tpc.php app.php --cxx-std=c++20

# Windows MSVC
php bin/tpc.php app.php --cxx-std=c++17

# Linux/macOS GCC/Clang
php bin/tpc.php app.php --cxx-std=c++17
```

---

### 2. project.yml 配置文件方式

在 `project.yml` 中添加 `cxx_std` 配置项：

```yaml
name: my-app
type: bin

sources:
  - src/main.php
  - src/utils.php

# C++ 标准版本（独立配置）
cxx_std: c++17

# 其他编译选项（不包含 C++ 标准）
cxxflags:
  - -Wall
  - -Wextra

ldflags:
  - -lm
```

**注意：** 
- ✅ `cxx_std` 专门用于指定 C++ 标准
- ✅ `cxxflags` 用于其他编译选项（如警告、优化等）
- ❌ 不要在 `cxxflags` 中包含 `-std=c++XX` 或 `/std:c++XX`

---

## 📊 支持的 C++ 标准

| 标准 | MSVC 标志 | GCC/Clang 标志 | 说明 |
|------|----------|----------------|------|
| c++14 | `/std:c++14` | `-std=c++14` | 默认（Unix） |
| c++17 | `/std:c++17` | `-std=c++17` | **推荐**（Windows 默认） |
| c++20 | `/std:c++20` | `-std=c++20` | 最新标准 |
| c++23 | `/std:c++23` | `-std=c++23` | 实验性支持 |

---

## 💡 平台默认值

### Windows (MSVC)

```php
// CompilerBase.php
$this->cxxStd = 'c++17'; // MSVC 更好的支持 C++17
```

**原因：** MSVC 对 C++17 的支持更成熟，C++14 的部分特性在 MSVC 中实现不完整。

---

### Linux/macOS (GCC/Clang)

```php
// CompilerBase.php
$this->cxxStd = 'c++14';
```

**原因：** C++14 是最广泛支持的标准，兼容性最好。

---

## 🔧 优先级规则

C++ 标准的设置遵循以下优先级（从高到低）：

1. **命令行参数** `--cxx-std=XXX`（最高优先级）
2. **project.yml 配置** `cxx_std: XXX`
3. **平台默认值**（Windows: c++17, Unix: c++14）

**示例：**

```yaml
# project.yml
cxx_std: c++14
```

```bash
# 命令行覆盖配置文件
php bin/tpc.php project.yml --cxx-std=c++17
# 最终使用 c++17
```

---

## ⚠️ 注意事项

### 1. 不要在 cxxflags 中重复指定

❌ **错误做法：**
```yaml
cxx_std: c++17
cxxflags:
  - -std=c++17  # 重复指定！
```

✅ **正确做法：**
```yaml
cxx_std: c++17
cxxflags:
  - -Wall
  - -Wextra
```

---

### 2. cxxflags 中的标准会被忽略

如果 `cxxflags` 中包含了 `-std=` 或 `/std:`，编译器会使用 `$this->cxxStd` 的值，而不是 `cxxflags` 中的值。

**代码逻辑：**
```php
// Windows
if (!str_contains($this->cxxflags, '/std:')) {
    $cmd .= ' /std:' . $this->cxxStd;  // 使用 cxxStd
}

// Unix
if (!str_contains($this->cxxflags, ' -std=')) {
    $cmd .= ' -std=' . $this->cxxStd;  // 使用 cxxStd
}
```

---

### 3. 选择合适的 C++ 标准

| 场景 | 推荐标准 | 原因 |
|------|---------|------|
| 最大兼容性 | c++14 | 所有编译器都支持 |
| 现代特性 | c++17 | 结构化绑定、if constexpr 等 |
| 最新特性 | c++20 | concepts、coroutines、modules |
| Windows 项目 | c++17 | MSVC 支持更好 |
| Linux 项目 | c++14 或 c++17 | 根据需求选择 |

---

## 📝 完整示例

### 示例 1：基本项目

```yaml
# project.yml
name: hello-world
type: bin

sources:
  - src/main.php

cxx_std: c++17
```

编译命令：
```bash
php bin/tpc.php project.yml
```

生成的编译命令：
```bash
# Windows
cl /std:c++17 /O0 ... 

# Linux
g++ -std=c++17 -O0 ...
```

---

### 示例 2：带额外编译选项

```yaml
# project.yml
name: my-app
type: bin

sources:
  - src/*.php

cxx_std: c++17

cxxflags:
  - -Wall
  - -Wextra
  - -Wpedantic

ldflags:
  - -lm
  - -lpthread
```

---

### 示例 3：命令行覆盖

```yaml
# project.yml
name: my-app
cxx_std: c++14  # 配置文件中的默认值
```

```bash
# 使用 C++17 覆盖配置文件
php bin/tpc.php project.yml --cxx-std=c++17
```

---

## 🎯 迁移指南

如果您之前的 `project.yml` 中有这样的配置：

### ❌ 旧配置（不推荐）

```yaml
cxxflags:
  - -std=c++17
  - -Wall
  - -O2
```

### ✅ 新配置（推荐）

```yaml
cxx_std: c++17

cxxflags:
  - -Wall
  - -O2
```

**优势：**
- ✅ 配置更清晰
- ✅ 更容易维护
- ✅ 跨平台兼容更好
- ✅ 避免重复和冲突

---

## 🔍 验证方法

编译时查看输出，确认 C++ 标准是否正确设置：

```bash
php bin/tpc.php project.yml --verbose
```

应该看到类似这样的输出：

```
Compiling typephp_main.cc...
g++ -std=c++17 -O0 -Wall ...
```

或者 Windows：

```
cl /std:c++17 /O0 /Wall ...
```

---

## 🐛 常见问题

### Q1: 为什么我的 C++17 特性不起作用？

A: 检查是否正确设置了 `cxx_std`：

```yaml
cxx_std: c++17  # 确保是 c++17 而不是 c++14
```

或者使用命令行：

```bash
php bin/tpc.php app.php --cxx-std=c++17
```

---

### Q2: cxxflags 中的 -std= 会被忽略吗？

A: 是的，编译器会优先使用 `$this->cxxStd` 的值。建议在 `cxxflags` 中不要包含 `-std=` 或 `/std:`。

---

### Q3: 可以在不同文件中使用不同的 C++ 标准吗？

A: 不可以。C++ 标准是整个项目的统一配置，所有文件使用相同的标准。

---

### Q4: C++20 支持如何？

A: 
- **MSVC**: 需要 Visual Studio 2019 16.11+ 或 VS 2022
- **GCC**: 需要 GCC 10+
- **Clang**: 需要 Clang 10+

如果您的编译器不支持 C++20，请使用 C++17。

---

## 📚 相关资源

- [C++14 特性](https://en.cppreference.com/w/cpp/14)
- [C++17 特性](https://en.cppreference.com/w/cpp/17)
- [C++20 特性](https://en.cppreference.com/w/cpp/20)
- [MSVC 编译器选项](https://docs.microsoft.com/cpp/build/reference/std-specify-language-standard-version)
- [GCC C++ 标准](https://gcc.gnu.org/projects/cxx-status.html)

---

## 🎉 总结

通过将 C++ 标准从 `cxxflags` 中独立出来，我们实现了：

1. ✅ **配置更清晰** - `cxx_std` 专门用于标准版本
2. ✅ **更易维护** - 不需要在 `cxxflags` 中查找标准选项
3. ✅ **跨平台兼容** - 自动适配 MSVC 和 GCC/Clang 的标志
4. ✅ **灵活覆盖** - 命令行可以覆盖配置文件
5. ✅ **避免冲突** - 不会重复指定标准版本

希望这个指南能帮助您更好地使用 C++ 标准配置！

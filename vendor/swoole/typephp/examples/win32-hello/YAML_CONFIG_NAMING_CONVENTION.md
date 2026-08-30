# YAML 配置命名规范指南

## 📋 概述

PHPX 编译器的 YAML 配置文件统一使用**中横线（kebab-case）**命名约定，这符合现代配置文件的行业标准。

---

## 🎯 命名约定

### ✅ 推荐：中横线（kebab-case）

```yaml
# 所有配置项使用中横线
name: my-app
build-mode: bin
cxx-std: c++17
cxx-flags:
  - -Wall
ld-flags:
  - -lm
debug: true
no-console: true
```

**优势：**
- ✅ 符合 YAML/JSON 生态标准
- ✅ 与命令行参数一致（`--cxx-std`）
- ✅ Docker、Kubernetes、npm 等主流工具都使用
- ✅ URL 友好，可直接用于 API

---

### ⚠️ 兼容：下划线（snake_case）

为了向后兼容，解析器也支持下划线命名：

```yaml
# 这些也会被正确解析（但不推荐）
type: bin          # 等同于 build-mode
cxx_std: c++17     # 等同于 cxx-std
cxxflags:          # 等同于 cxx-flags
  - -Wall
```

**注意：** 新项目应该使用中横线，下划线仅用于兼容旧配置。

---

## 📊 配置项对照表

| 配置项（推荐） | 兼容写法（别名） | 说明 | 类型 |
|--------------|----------------|------|------|
| `name` | - | 项目名称 | string |
| `build-mode` | `type` | 构建模式（bin/ext） | string |
| `version` | - | 版本号 | string |
| `cxx-std` | `cxx_std` | C++ 标准版本 | string |
| `cxx-flags` | `cxxflags` | C++ 编译选项 | array/string |
| `ld-flags` | `ldflags` | 链接器选项 | array/string |
| `sources` | - | 源文件列表 | array |
| `ignore` | - | 忽略的文件/目录 | array |
| `debug` | - | 启用调试模式 | boolean |
| `no-console` | - | 隐藏控制台窗口 | boolean |

**注意：**
- ✅ **推荐使用中横线格式**（如 `build-mode`, `cxx-flags`）
- ⚠️ **别名仅用于向后兼容**（如 `type`, `cxxflags`）
- 📖 **使用手册中的示例可能使用别名**，两者都有效

---

## 💡 完整示例

### 示例 1：基本项目配置

```yaml
# project.yml - 推荐使用中横线
name: hello-world
build-mode: bin
version: 1.0.0

cxx-std: c++17

cxx-flags:
  - -Wall
  - -Wextra
  - -O2

sources:
  - src/main.php
  - src/utils.php
```

---

### 示例 2：扩展模块配置

```yaml
# project.yml
name: my-extension
build-mode: ext
version: 0.1.0

cxx-std: c++17

cxx-flags:
  - -Wall
  - -fPIC

ld-flags:
  - -shared

sources:
  - src/*.php
```

---

### 示例 3：复杂项目配置

```yaml
# project.yml
name: my-app
build-mode: bin
version: 2.0.0

# C++ 标准
cxx-std: c++20

# 编译选项
cxx-flags:
  - -Wall
  - -Wextra
  - -Wpedantic
  - -O3

# 链接选项
ld-flags:
  - -lm
  - -lpthread
  - -lssl

# 源文件
sources:
  - src/main.php
  - src/controllers
  - src/models
  - src/views

# 忽略的文件
ignore:
  - tests/
  - docs/
  - "*.md"
  - ext-json  # 忽略内置扩展
```

---

## 🔧 优先级规则

配置值的读取遵循以下优先级（从高到低）：

1. **命令行参数**（最高优先级）
   ```bash
   php bin/tpc.php project.yml --cxx-std=c++20
   ```

2. **YAML 配置文件**
   ```yaml
   cxx-std: c++17
   ```

3. **平台默认值**
   - Windows: `c++17`
   - Unix: `c++14`

---

## 🔄 兼容性说明

### 自动转换逻辑

解析器会自动处理以下情况：

```php
// Translator.php 中的逻辑

// build-mode / type 别名支持
$buildMode = $cfg['build-mode'] ?? $cfg['type'] ?? null;
if (!empty($buildMode)) {
    $this->setBuildMode($buildMode);
}

// cxx-flags / cxxflags 别名支持
$cxxflags = $cfg['cxx-flags'] ?? $cfg['cxxflags'] ?? null;
if (!empty($cxxflags)) {
    // 处理 cxxflags
}
```

**支持的写法：**
```yaml
# ✅ 推荐（中横线）
build-mode: bin
cxx-flags:
  - -Wall

# ⚠️ 兼容（别名，用于向后兼容使用手册）
type: bin
cxxflags:
  - -Wall

# 两者效果完全相同
```

**优先级：**
1. 如果同时指定了 `build-mode` 和 `type`，优先使用 `build-mode`
2. 如果同时指定了 `cxx-flags` 和 `cxxflags`，优先使用 `cxx-flags`
3. 建议只使用其中一种，避免混淆

---

### 废弃的写法

以下写法仍然有效，但**不推荐**：

```yaml
# ❌ 不推荐：在 cxx-flags 中包含 -std=
cxx-flags:
  - -std=c++17  # 应该使用 cxx-std
  - -Wall

# ✅ 推荐：分开配置
cxx-std: c++17
cxx-flags:
  - -Wall
```

---

## 📝 迁移指南

### 从旧配置迁移到新配置

#### ⚠️ 旧配置（使用手册中的示例，仍然有效）

```yaml
name: my-app
type: bin
version: 1.0.0

cxx_std: c++14

cxxflags: |
  -std=c++14
  -Wall
  -O2

ldflags: -lm -lpthread
```

**说明：**
- ✅ `type` 是 `build-mode` 的别名（向后兼容）
- ✅ `cxxflags` 是 `cxx-flags` 的别名（向后兼容）
- ✅ 这些配置**仍然完全有效**
- 📖 使用手册中的示例继续使用这些别名

---

#### ✅ 新配置（推荐）

```yaml
name: my-app
build-mode: bin
version: 1.0.0

cxx-std: c++14

cxx-flags:
  - -Wall
  - -O2

ld-flags:
  - -lm
  - -lpthread
```

**改进点：**
1. ✅ `type` → `build-mode`（更清晰的中横线命名）
2. ✅ `cxx_std` → `cxx-std`（中横线）
3. ✅ `cxxflags` → `cxx-flags`（中横线）
4. ✅ 移除 `-std=c++14`（使用独立的 `cxx-std`）
5. ✅ 数组格式更清晰

---

### 重要提示

**不需要立即迁移！**

- ✅ 旧配置（使用 `type`, `cxxflags`）**完全有效**
- ✅ 新配置（使用 `build-mode`, `cxx-flags`）**推荐使用**
- ✅ 两者可以混合使用（但不建议）
- 📖 使用手册中的示例保持不变

**建议：**
- 新项目 → 使用中横线格式
- 现有项目 → 可以继续使用别名，无需修改

---

## 🎨 最佳实践

### 1. 始终使用中横线

```yaml
# ✅ 好
cxx-std: c++17
build-mode: bin
debug: true

# ❌ 避免
cxx_std: c++17
build_mode: bin
debug_info: true
```

---

### 2. 使用数组而非多行字符串

```yaml
# ✅ 推荐：数组格式
cxx-flags:
  - -Wall
  - -Wextra
  - -O2

# ⚠️ 可用但不推荐：多行字符串
cxx-flags: |
  -Wall
  -Wextra
  -O2
```

---

### 3. 分离 C++ 标准和编译选项

```yaml
# ✅ 推荐
cxx-std: c++17
cxx-flags:
  - -Wall
  - -O2

# ❌ 避免
cxx-flags:
  - -std=c++17  # 不要在这里指定标准
  - -Wall
```

---

### 4. 添加注释说明

```yaml
name: my-app
build-mode: bin

# 使用 C++17 以获得更好的性能
cxx-std: c++17

# 启用所有警告
cxx-flags:
  - -Wall
  - -Wextra
  - -Wpedantic
```

---

## 🐛 常见问题

### Q1: 我可以使用 `type` 和 `cxxflags` 吗？

A: **可以！** 这些是官方支持的别名，用于向后兼容使用手册。

```yaml
# ✅ 完全有效（使用手册中的示例）
type: bin
cxxflags:
  - -Wall

# ✅ 同样有效（推荐的新格式）
build-mode: bin
cxx-flags:
  - -Wall
```

**建议：**
- 新项目 → 使用中横线格式
- 现有项目 → 可以继续使用别名

---

### Q2: `type` 和 `build-mode` 有什么区别？

A: **没有区别**，`type` 是 `build-mode` 的别名。推荐使用 `build-mode`。

```yaml
# 这两个是等价的
build-mode: bin  # ✅ 推荐
type: bin        # ⚠️ 别名（使用手册中的示例）
```

---

### Q3: 可以在 cxx-flags 中使用 `-std=` 吗？

A: 技术上可以，但**不推荐**。应该使用独立的 `cxx-std` 配置项。

```yaml
# ❌ 不推荐
cxx-flags:
  - -std=c++17
  - -Wall

# ✅ 推荐
cxx-std: c++17
cxx-flags:
  - -Wall
```

---

### Q4: 如何覆盖配置文件中的设置？

A: 使用命令行参数：

```bash
# 覆盖 cxx-std
php bin/tpc.php project.yml --cxx-std=c++20

# 覆盖 build-mode
php bin/tpc.php project.yml --mode=ext

# 启用调试信息
php bin/tpc.php project.yml --debug
```

---

## 📚 相关资源

- [YAML 官方规范](https://yaml.org/spec/)
- [Kubernetes 命名约定](https://kubernetes.io/docs/concepts/overview/working-with-objects/names/)
- [Docker Compose 文件参考](https://docs.docker.com/compose/compose-file/)
- [npm package.json 规范](https://docs.npmjs.com/cli/v9/configuring-npm/package-json)

---

## 🎉 总结

### 核心原则

1. ✅ **统一使用中横线**（kebab-case）
2. ✅ **分离关注点**（`cxx-std` vs `cxx-flags`）
3. ✅ **使用数组格式**（更易读）
4. ✅ **添加注释**（提高可维护性）
5. ✅ **保持向后兼容**（支持 `type`, `cxxflags` 别名）

### 别名说明

**为了兼容使用手册，以下别名仍然有效：**

| 推荐写法 | 别名（使用手册） | 状态 |
|---------|----------------|------|
| `build-mode` | `type` | ✅ 完全支持 |
| `cxx-flags` | `cxxflags` | ✅ 完全支持 |

**建议：**
- 📖 使用手册中的示例继续使用别名
- ✨ 新项目推荐使用中横线格式
- 🔄 现有项目无需修改，别名完全有效

### 快速参考

```yaml
# 标准模板（推荐）
name: my-project
build-mode: bin
version: 1.0.0

cxx-std: c++17

cxx-flags:
  - -Wall
  - -Wextra

ld-flags:
  - -lm

sources:
  - src/*.php
```

```yaml
# 使用手册中的示例（仍然有效）
name: my-project
type: bin
version: 1.0.0

cxx_std: c++17

cxxflags:
  - -Wall
  - -Wextra

ldflags:
  - -lm

sources:
  - src/*.php
```

遵循这些规范，您的配置文件将更加清晰、易读和易于维护！

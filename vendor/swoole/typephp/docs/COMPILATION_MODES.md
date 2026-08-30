# PHP AOT 编译器编译模式详解

## 📋 概述

PHP AOT 编译器支持两种编译模式，每种模式针对不同的使用场景。本文档详细说明两种模式的区别、使用方法和最佳实践。

---

## 🔹 扩展模式 (Extension Mode)

### 基本概念

扩展模式将 PHP 代码编译为 PHP 扩展文件（`.so` 或 `.dll`），可以作为标准 PHP 扩展加载到 php-fpm 中运行。

### 编译命令

```bash
php bin/tpc.php <source_dir> --mode=ext -o <output_name>
```

### 示例

```bash
# 编译 Coolify 项目
php bin/tpc.php projects/coolify/app/ --mode=ext -o coolify

# 输出文件
coolify.so  # Linux
coolify.dll # Windows
```

### 安装方法

#### 1. 临时加载（测试用）

```bash
php -d extension=./coolify.so -r "echo 'Extension loaded';"
```

#### 2. 永久加载（生产环境）

```bash
# 复制扩展文件到 PHP 扩展目录
sudo cp coolify.so $(php-config --extension-dir)/

# 创建配置文件
echo "extension=coolify" | sudo tee /etc/php/8.1/mods-available/coolify.ini

# 启用扩展
sudo phpenmod coolify

# 重启 PHP-FPM
sudo systemctl restart php8.1-fpm
```

### 代码结构

**不需要 `main()` 函数**

```php
<?php
// 定义类
class UserController {
    public function index() {
        return "User List";
    }
}

// 定义函数
function route_handler($path) {
    echo "Handling: {$path}";
}

// 扩展模式下，这些代码会在被 PHP 调用时执行
// 不需要 main() 函数
```

### 使用场景

✅ **适合**:
- Web 应用程序
- API 服务
- 需要与现有 PHP 框架集成
- 依赖 php-fpm 的生产环境
- SaaS 平台

❌ **不适合**:
- 命令行工具
- 独立运行的服务
- 长期驻留的守护进程

### 优点

| 优点 | 说明 |
|------|------|
| 🔒 安全性 | 源码被编译，不易泄露 |
| ⚡ 性能 | 比纯 PHP 快 3-10 倍 |
| 🔄 兼容性 | 完全兼容现有 PHP 生态 |
| 📦 易部署 | 标准的 PHP 扩展安装方式 |

### 缺点

| 缺点 | 说明 |
|------|------|
| 🔧 依赖 PHP | 需要 PHP 运行时环境 |
| 🌐 仅限 Web | 主要面向 Web 场景 |
| ⚙️ 配置复杂 | 需要配置 PHP 扩展 |

---

## 🔸 二进制模式 (Binary Mode)

### 基本概念

二进制模式将 PHP 代码编译为独立的可执行文件，不依赖 PHP 运行时环境。

### 编译命令

```bash
php bin/tpc.php <source_dir> -o <output_binary>
```

### 示例

```bash
# 编译 Workerman 项目
php bin/tpc.php projects/workerman/src/ -o workerman

# 输出文件
workerman  # Linux 可执行文件
```

### 运行方法

```bash
# 直接运行
./workerman start

# 后台运行
./workerman start -d

# 查看状态
./workerman status
```

### 代码结构

**必须有 `main()` 函数**

```php
<?php
// 类定义
class Application {
    public function run() {
        echo "Application running\n";
    }
}

// ✅ 必须定义 main() 函数
function main() {
    $app = new Application();
    $app->run();
}

// ✅ 或者带参数的 main()
function main(int $argc, array $argv) {
    echo "Arguments: " . implode(', ', $argv) . "\n";
    
    $app = new Application();
    $app->run();
}
```

### main() 函数签名

#### 方式一：无参数（默认）

```php
function main() {
    // 程序入口
}
```

#### 方式二：带命令行参数

```php
function main(int $argc, array $argv) {
    // $argc: 参数个数
    // $argv: 参数数组
    
    echo "Script: {$argv[0]}\n";
    if ($argc > 1) {
        echo "Arguments: " . implode(', ', array_slice($argv, 1)) . "\n";
    }
}
```

### 使用场景

✅ **适合**:
- 命令行工具（CLI）
- 长期运行的服务（如 Workerman）
- 微服务架构中的服务节点
- 独立应用程序
- 批处理任务

❌ **不适合**:
- Web 应用（无法在浏览器中访问）
- 需要与现有 PHP 代码混合运行的场景

### 优点

| 优点 | 说明 |
|------|------|
| 🚀 零依赖 | 无需安装 PHP |
| 📦 易分发 | 单个可执行文件 |
| 🔐 安全性 | 完全编译为机器码 |
| ⚡ 高性能 | 优化的原生代码 |

### 缺点

| 缺点 | 说明 |
|------|------|
| 🖥️ 平台相关 | 需要为不同系统编译 |
| 🔄 更新复杂 | 需要重新编译和替换 |
| 🌐 不支持 Web | 无法在 php-fpm 中使用 |

---

## 📊 模式对比

### 详细对比表

| 特性 | 扩展模式 (`--mode=ext`) | 二进制模式 (默认) |
|------|---------------------|------------------|
| **输出格式** | `.so` / `.dll` | 可执行文件 |
| **运行环境** | php-fpm / CLI | 独立运行 |
| **PHP 依赖** | ✅ 需要 | ❌ 不需要 |
| **main() 函数** | ❌ 不需要 | ✅ 必须 |
| **Web 访问** | ✅ 支持 | ❌ 不支持 |
| **CLI 运行** | ✅ 支持 | ✅ 支持 |
| **部署难度** | 中等 | 简单 |
| **性能提升** | 3-10 倍 | 5-20 倍 |
| **代码保护** | 中等 | 完全 |
| **适用场景** | Web 应用 | CLI 工具/服务 |

### 选择建议

```
需要运行在 Web 环境？
├─ 是 → 选择扩展模式
└─ 否 → 需要 main() 函数吗？
         ├─ 是 → 选择二进制模式
         └─ 是 → 选择扩展模式
```

---

## 🎯 实战示例

### 示例一：Web API（扩展模式）

**项目结构**:
```
api-project/
├── src/
│   ├── Controllers/
│   │   └── UserController.php
│   ├── Routes.php
│   └── index.php
```

**UserController.php**:
```php
<?php
namespace App\Controllers;

class UserController {
    public function list() {
        return [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
    }
}
```

**index.php**:
```php
<?php
// 扩展模式：不需要 main()
use App\Controllers\UserController;

$controller = new UserController();
$data = $controller->list();

header('Content-Type: application/json');
echo json_encode($data);
```

**编译**:
```bash
php bin/tpc.php api-project/src/ --mode=ext -o api_extension
```

**使用**:
```bash
# 在 php-fpm 中作为扩展加载
# 通过 Web 服务器访问
```

---

### 示例二：CLI 工具（二进制模式）

**项目结构**:
```
cli-tool/
├── src/
│   ├── Command.php
│   └── main.php
```

**Command.php**:
```php
<?php
class Command {
    public function execute($args) {
        echo "Executing with args: " . implode(' ', $args) . "\n";
    }
}
```

**main.php**:
```php
<?php
// 二进制模式：必须有 main()
function main(int $argc, array $argv) {
    $command = new Command();
    $command->execute(array_slice($argv, 1));
}
```

**编译**:
```bash
php bin/tpc.php cli-tool/src/ -o mytool
```

**使用**:
```bash
./mytool arg1 arg2 arg3
```

---

## 💡 最佳实践

### 扩展模式

1. **命名空间**: 使用唯一的命名空间避免冲突
   ```php
   namespace MyProject\Api;
   ```

2. **初始化**: 提供扩展初始化函数
   ```php
   function init_extension() {
       // 初始化逻辑
   }
   ```

3. **配置**: 支持通过 php.ini 配置
   ```php
   ini_set('my_extension.enabled', '1');
   ```

### 二进制模式

1. **错误处理**: 在 main() 中处理全局异常
   ```php
   function main() {
       try {
           // 主逻辑
       } catch (Throwable $e) {
           fwrite(STDERR, $e->getMessage());
           exit(1);
       }
   }
   ```

2. **信号处理**: 处理系统信号
   ```php
   function main() {
       pcntl_signal(SIGTERM, function() {
           echo "Shutting down...\n";
           exit(0);
       });
       
       // 主循环
   }
   ```

3. **日志记录**: 实现日志功能
   ```php
   function log_message($level, $message) {
       $timestamp = date('Y-m-d H:i:s');
       echo "[{$timestamp}] [{$level}] {$message}\n";
   }
   ```

---

## 🔍 故障排除

### 扩展模式问题

#### 问题：扩展加载失败

```bash
PHP Warning:  PHP Startup: Unable to load dynamic library
```

**解决方案**:
1. 检查文件权限：`chmod 644 coolify.so`
2. 验证 PHP 版本匹配：`php -v`
3. 检查依赖：`ldd coolify.so`

#### 问题：Segmentation Fault

**解决方案**:
1. 检查代码中是否使用了不支持的语法
2. 查看错误日志：`tail -f /var/log/php/error.log`
3. 使用 gdb 调试核心转储

### 二进制模式问题

#### 问题：Permission denied

```bash
bash: ./myapp: Permission denied
```

**解决方案**:
```bash
chmod +x myapp
```

#### 问题：找不到符号

```bash
error while loading shared libraries
```

**解决方案**:
```bash
# 设置库路径
export LD_LIBRARY_PATH=/path/to/libs:$LD_LIBRARY_PATH

# 检查实际链接路径和依赖
ldd ./app
```

---

## 📚 相关文档

- [快速入门指南](QUICKSTART.md) - 开始使用 AOT 编译器
- [兼容性清单](INCOMPATIBLE_PHP_FEATURES.md) - 了解当前限制
- [构建速度研究](AOT_BUILD_SPEED_RESEARCH.md) - 优化编译流程
- [兼容性分类](PHP_INCOMPATIBILITY_CLASSIFICATION.md) - 判断限制的性质和处理方向

---

**最后更新**: 2024 年 3 月 18 日  
**文档版本**: v1.0

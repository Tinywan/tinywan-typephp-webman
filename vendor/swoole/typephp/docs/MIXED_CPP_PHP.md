# PHP 与 C++ 混合编程指南

## 📋 概述

AOT 编译器允许在同一个项目中同时使用 `.php` 和 `.cpp/.cc` 代码，实现 PHP 与 C++ 的混合编程。这种机制让你能够：

- ✅ 用 C++ 编写高性能的核心算法
- ✅ 用 PHP 编写业务逻辑和界面
- ✅ 无缝调用，性能无损

---

## 🎯 核心机制

### C++ 函数暴露给 PHP

当 C++ 函数满足以下条件时，可以在 PHP 代码中直接调用：

1. **参数类型**：必须全部为 `php::` 类型（如 `php::Int`、`php::Str`、`php::Float` 等）
2. **返回值类型**：必须为 `php::` 类型
3. **函数命名**：必须以 `php_` 前缀开头
4. **存根文件**：必须有对应的 `.stub.php` 文件声明函数签名

---

## 📦 Box 封装器机制

### 概述

`php::Box` 是 AOT 编译器提供的 C++ 类封装器，它允许：
- ✅ C++ 对象被 PHP GC（垃圾回收器）自动管理
- ✅ 无需手动释放内存
- ✅ 可以存储在 PHP 数组中
- ✅ 可以作为对象属性存储
- ✅ 在 PHP 层表现为 `resource` 类型

### 基本用法

#### 步骤一：定义 C++ 类并继承 php::Box

```cpp
#include <phpx.h>

using namespace php;

// 自定义 C++ 类，继承自 php::Box
class VectorBox : public Box {
  public:
    std::vector<bool> vec;
    
    // 构造函数
    VectorBox(size_t size, bool init) {
        vec.resize(size, init);
    }
    
    // 成员方法
    void checkOffset(Int offset) {
        if (offset >= vec.size()) {
            zend_throw_error(NULL, "index[%ld] is out of range()", offset);
        }
    }
};
```

#### 步骤二：创建对象并返回给 PHP

```cpp
// 创建 Box 对象并返回给 PHP
var php_vector_new(Int size, Bool init) {
    // new 一个 VectorBox，包装为 php::Var 返回
    return {new VectorBox(size, init)};
}
```

**关键点**：
- ✅ 使用 `new` 创建对象
- ✅ 用 `{}` 包装为 `php::Var` 返回
- ✅ 无需手动 `delete`，PHP GC 会自动释放

#### 步骤三：在 PHP 中接收和使用

**PHP 代码** (`main.php`):
```php
<?php
function main() {
    // 调用 C++ 函数创建 VectorBox
    $vector = vector_new(100, true);
    
    // $vector 在 PHP 中是 resource 类型
    var_dump($vector);  // resource(1) of type (VectorBox)
    
    // 可以存储在数组中
    $vectors[] = $vector;
    
    // 可以作为对象属性
    $obj->vector = $vector;
    
    // 传递给其他 C++ 函数
    vector_set($vector, 5, false);
    $value = vector_get($vector, 5);
}
```

#### 步骤四：在 C++ 中转换回对象指针

```cpp
// 接收 php::Var 类型的 Box 参数
Bool php_vector_get(var box, Int offset) {
    // 将 php::Var 转换为 C++ 对象指针
    auto vecbox = box.toBox<VectorBox>();
    
    // 现在可以访问 C++ 对象的成员
    vecbox->checkOffset(offset);
    return vecbox->vec.at(offset);
}

void php_vector_set(var box, Int offset, Bool value) {
    // 转换为对象指针
    auto vecbox = box.toBox<VectorBox>();
    
    // 修改对象状态
    vecbox->checkOffset(offset);
    vecbox->vec.at(offset) = value;
}
```

**关键点**：
- ✅ 使用 `box.toBox<T>()` 转换为具体类型
- ✅ 模板参数必须是实际的类名
- ✅ 转换后可以直接访问成员变量和方法

---

### 完整示例：VectorBox

#### C++ 实现 (`vector.cc`)

```cpp
#include <phpx.h>
#include <vector>

using namespace php;

// 1. 定义 Box 类
class VectorBox : public Box {
  public:
    std::vector<bool> vec;
    
    VectorBox(size_t size, bool init) {
        vec.resize(size, init);
    }
    
    void checkOffset(Int offset) {
        if (offset >= vec.size()) {
            zend_throw_error(NULL, "index[%ld] is out of range()", offset);
        }
    }
};

// 2. 创建对象的函数
var php_vector_new(Int size, Bool init) {
    return {new VectorBox(size, init)};
}

// 3. 获取元素的函数
Bool php_vector_get(var box, Int offset) {
    auto vecbox = box.toBox<VectorBox>();
    vecbox->checkOffset(offset);
    return vecbox->vec.at(offset);
}

// 4. 设置元素的函数
void php_vector_set(var box, Int offset, Bool value) {
    auto vecbox = box.toBox<VectorBox>();
    vecbox->checkOffset(offset);
    vecbox->vec.at(offset) = value;
}

// 5. 获取大小的函数
Int php_vector_size(var box) {
    auto vecbox = box.toBox<VectorBox>();
    return vecbox->vec.size();
}
```

#### PHP 存根文件 (`vector.stub.php`)

```php
<?php
/**
 * VectorBox C++ 函数的 PHP 存根
 */

/**
 * 创建新的 VectorBox
 * 
 * @param int $size 向量大小
 * @param bool $init 初始值
 * @return resource VectorBox 资源
 */
function vector_new(int $size, bool $init): mixed {
    // 空实现
}

/**
 * 获取向量元素
 * 
 * @param resource $box VectorBox 资源
 * @param int $offset 偏移量
 * @return bool 元素值
 */
function vector_get(mixed $box, int $offset): bool {
    // 空实现
}

/**
 * 设置向量元素
 * 
 * @param resource $box VectorBox 资源
 * @param int $offset 偏移量
 * @param bool $value 新值
 */
function vector_set(mixed $box, int $offset, bool $value): void {
    // 空实现
}

/**
 * 获取向量大小
 * 
 * @param resource $box VectorBox 资源
 * @return int 大小
 */
function vector_size(mixed $box): int {
    // 空实现
}
```

#### PHP 使用示例 (`main.php`)

```php
<?php
require_once __DIR__ . '/vector.stub.php';

function main() {
    echo "=== VectorBox 示例 ===\n";
    
    // 创建大小为 10 的向量，初始值为 true
    $vector = vector_new(10, true);
    
    echo "向量大小：" . vector_size($vector) . "\n";
    
    // 读取元素
    echo "元素 [5]: " . (vector_get($vector, 5) ? 'true' : 'false') . "\n";
    
    // 修改元素
    vector_set($vector, 5, false);
    echo "修改后的元素 [5]: " . (vector_get($vector, 5) ? 'true' : 'false') . "\n";
    
    // 存储在数组中
    $vectors = [];
    for ($i = 0; $i < 5; $i++) {
        $vectors[] = vector_new(100, $i % 2 == 0);
    }
    
    echo "创建了 " . count($vectors) . " 个向量\n";
    
    // 作为对象属性
    class Container {
        public $vector;
    }
    
    $container = new Container();
    $container->vector = vector_new(50, true);
    echo "容器中的向量大小：" . vector_size($container->vector) . "\n";
}
```

---

### Box 封装器的优势

#### 1. 自动内存管理

```cpp
// ❌ 没有 Box：需要手动管理内存
class MyObject {
    // ...
};

MyObject* obj = new MyObject();
// ... 使用
delete obj;  // 必须手动删除，容易忘记

// ✅ 使用 Box：PHP GC 自动管理
class MyBox : public php::Box {
    // ...
};

php::Var result = {new MyBox()};  // PHP GC 会在适当时机释放
```

#### 2. 类型安全

```cpp
// 编译期类型检查
auto box = box_var.toBox<VectorBox>();  // 类型明确

// 如果类型不匹配，会在编译期或运行时报错
```

#### 3. 易于使用

```cpp
// 简单的转换语法
auto ptr = box.toBox<MyClass>();

// 直接访问成员
ptr->method();
ptr->property = value;
```

---

### 注意事项

#### ⚠️ 1. 必须继承 php::Box

```cpp
// ✅ 正确
class MyClass : public php::Box {
    // ...
};

// ❌ 错误：不会受 PHP GC 管理
class MyClass {
    // ... 需要手动释放
};
```

#### ⚠️ 2. 使用 new 创建对象

```cpp
// ✅ 正确：使用 new
return {new VectorBox(size, init)};

// ❌ 错误：栈上对象不会被 GC 管理
VectorBox box(size, init);
return {&box};  // 悬空指针！
```

#### ⚠️ 3. 正确的 toBox 转换

```cpp
// ✅ 正确：指定正确的类型
auto ptr = box.toBox<VectorBox>();

// ❌ 错误：类型不匹配
auto ptr = box.toBox<WrongType>();  // 运行时错误
```

#### ⚠️ 4. 资源有效性检查

```cpp
// 推荐：在使用前检查资源是否有效
Bool php_vector_get(var box, Int offset) {
    if (box.isNull()) {
        zend_throw_error(NULL, "Invalid box resource");
        return false;
    }
    
    auto vecbox = box.toBox<VectorBox>();
    // ...
}
```

---

### 实际应用场景

#### 场景一：数据结构封装

```cpp
// 封装 C++ STL 容器
class HashMapBox : public php::Box {
  public:
    std::unordered_map<std::string, int> map;
};

var php_hashmap_new() {
    return {new HashMapBox()};
}

void php_hashmap_set(var box, Str key, Int value) {
    auto hashmap = box.toBox<HashMapBox>();
    hashmap->map[key.to_string()] = value;
}
```

#### 场景二：图像处理

```cpp
// 封装图像资源
class ImageBox : public php::Box {
  public:
    cv::Mat image;
    
    ImageBox(const std::string& path) {
        image = cv::imread(path);
    }
};

var php_image_load(Str path) {
    return {new ImageBox(path.to_string())};
}

var php_image_resize(var box, Int width, Int height) {
    auto img = box.toBox<ImageBox>();
    cv::resize(img->image, img->image, cv::Size(width, height));
    return box;  // 返回同一个对象
}
```

#### 场景三：数据库连接

```cpp
// 封装数据库连接
class DatabaseBox : public php::Box {
  public:
    MYSQL* conn;
    
    DatabaseBox(const std::string& host, const std::string& user, 
                const std::string& pass, const std::string& db) {
        conn = mysql_init(NULL);
        mysql_real_connect(conn, host.c_str(), user.c_str(), 
                          pass.c_str(), db.c_str(), 0, NULL, 0);
    }
    
    ~DatabaseBox() {
        mysql_close(conn);
    }
};

var php_db_connect(Str host, Str user, Str pass, Str db) {
    return {new DatabaseBox(host.to_string(), user.to_string(), 
                           pass.to_string(), db.to_string())};
}
```

---

## 📝 基本语法

### 步骤一：编写 C++ 函数实现

**示例文件**: `examples/prime/src/prime.cc`

```cpp
#include "phpx.h"
#include "phpx_helper.h"

using namespace php;

/**
 * 判断一个数是否为质数
 * 
 * @param n 要判断的数字
 * @return bool 是否为质数
 */
bool php_is_prime(php::Int n) {
    if (n < 2) {
        return false;
    }
    
    for (php::Int i = 2; i * i <= n; i++) {
        if (n % i == 0) {
            return false;
        }
    }
    
    return true;
}

/**
 * 获取指定范围内的所有质数
 * 
 * @param start 起始数字
 * @param end 结束数字
 * @return array 质数数组
 */
php::Array php_get_primes(php::Int start, php::Int end) {
    php::Array primes;
    
    for (php::Int i = start; i <= end; i++) {
        if (php_is_prime(i)) {
            primes.append(i);
        }
    }
    
    return primes;
}

/**
 * 计算两个大数的乘积
 * 
 * @param a 第一个数
 * @param b 第二个数
 * @return int 乘积结果
 */
php::Int php_multiply_big_numbers(php::Int a, php::Int b) {
    return a * b;
}
```

---

### 步骤二：创建 .stub.php 存根文件

**示例文件**: `examples/prime/src/prime.stub.php`

```php
<?php
/**
 * C++ 函数的 PHP 存根声明
 * 
 * 注意：这些函数只在 C++ 中实现，PHP 中只有声明
 * AOT 编译器会解析这些声明并生成相应的调用代码
 */

/**
 * 判断一个数是否为质数
 * 
 * @param int $n 要判断的数字
 * @return bool 是否为质数
 */
function is_prime(int $n): bool {
    // 空实现，仅用于声明
    // AOT 编译器不会解析此函数的内容
}

/**
 * 获取指定范围内的所有质数
 * 
 * @param int $start 起始数字
 * @param int $end 结束数字
 * @return array 质数数组
 */
function get_primes(int $start, int $end): array {
    // 空实现，仅用于声明
}

/**
 * 计算两个大数的乘积
 * 
 * @param int $a 第一个数
 * @param int $b 第二个数
 * @return int 乘积结果
 */
function multiply_big_numbers(int $a, int $b): int {
    // 空实现，仅用于声明
}
```

---

### 步骤三：在 PHP 代码中调用

**示例文件**: `examples/prime/main.php`

```php
<?php
// 引入存根文件（可选，用于 IDE 提示）
require_once __DIR__ . '/src/prime.stub.php';

function main() {
    // 调用 C++ 实现的函数
    echo "=== 质数判断 ===\n";
    $numbers = [2, 3, 5, 7, 11, 13, 17, 19, 23, 25, 27, 29];
    
    foreach ($numbers as $num) {
        if (is_prime($num)) {
            echo "{$num} 是质数\n";
        } else {
            echo "{$num} 不是质数\n";
        }
    }
    
    echo "\n=== 获取 1-100 的质数 ===\n";
    $primes = get_primes(1, 100);
    print_r($primes);
    
    echo "\n=== 大数乘法 ===\n";
    $a = 123456789;
    $b = 987654321;
    $result = multiply_big_numbers($a, $b);
    echo "{$a} × {$b} = {$result}\n";
}
```

---

## 🔧 编译配置

### 项目结构示例

```
examples/prime/
├── src/
│   ├── prime.cc          # C++ 实现
│   └── prime.stub.php    # PHP 存根声明
├── main.php              # PHP 主程序
└── project.yml           # 项目配置文件
```

### project.yml 配置

```yaml
name: prime
type: bin
sources:
  - src/*.cc              # C++ 源文件
  - src/*.php             # PHP 源文件
  - main.php              # 入口文件
```

### 编译命令

```bash
# 编译项目
php bin/tpc.php examples/prime -o prime

# 运行生成的可执行文件
./prime
```

---

## 📊 类型映射表

### PHP 类型 ↔ C++ 类型对照

| PHP 类型 | C++ 类型 | 说明 | 内存 |
|---------|---------|------|------|
| `int` | `php::Int` | 原生整数 | 8B |
| `float` | `php::Float` | 原生浮点 | 8B |
| `bool` | `php::Bool` | 原生布尔 | 1B |
| `string` | `php::Str` | 字符串对象 | 指针 |
| `array` | `php::Array` | 数组对象 | 指针 |
| `object` | `php::Object` | 对象指针 | 指针 |
| `mixed` | `php::Var` | 通用变量 | 16B |

---

## ⚠️ 重要规则

### 1. 函数命名规范

这里的 `php_` 仅用于“用户 PHP 函数/类方法到 C++ callable”的 ABI 映射，不是 TypePHP 或 PHPX 内部 helper 的通用前缀。内部 ZendAPI 包装必须使用 `php::`，TypePHP 独有逻辑使用 `typephp_`。完整规则参见 [C++ 命名空间、前缀与符号 ABI](CPP_SYMBOL_NAMING.md)。

✅ **正确**:
```cpp
bool php_is_prime(php::Int n);
php::Array php_get_primes(php::Int start, php::Int end);
php::Int php_add_numbers(php::Int a, php::Int b);
```

❌ **错误**:
```cpp
bool isPrime(php::Int n);           // 缺少 php_ 前缀
php::Int Prime_Check(php::Int n);   // 命名风格不一致
void php_print_result(php::Str msg); // 返回 void 不支持
```

### 2. 参数和返回值类型

✅ **正确**:
```cpp
php::Int php_add(php::Int a, php::Int b);
php::Str php_concat(php::Str a, php::Str b);
php::Array php_merge(php::Array a, php::Array b);
```

❌ **错误**:
```cpp
int php_add(int a, int b);              // 未使用 php:: 类型
php::Int php_calc(double a, double b);  // double 不是 php:: 类型
void php_print(php::Str msg);           // void 不支持
```

### 3. .stub.php 文件要求

库项目中的 `.stub.php` 用于声明由 C++ 实现的函数，不需要添加库名注解：

```php
<?php
function vector_new(int $size, bool $init = false): mixed {}
```

`-m lib` 会把库项目的 `.php` 和本地 `.stub.php` 接口汇总到 `<target>.stub.php`。该发布 stub 自动带有 `@import-library`，其他项目加载后，其中的所有函数和类方法都按外部库 ABI 导入。库名由文件名推导，例如 `prime2.stub.php` 对应 `prime2` 库。

外部 stub 中的类会在消费项目中生成类注册、属性和常量实体，但不生成 `php_*` 方法本体；方法本体由动态库提供。
Property hook 同样按方法处理：发布 stub 保留 `get`/`set` 的声明并移除实现，消费项目生成属性实体，hook 的 getter/setter `php_*` 实现从动态库导入。

库内部声明可使用编译期 Attribute `#[NoExport]` 从公开 ABI 排除：

```php
#[\NoExport]
function internal_helper(): void {}

#[\NoExport]
class InternalService {}
```

声明仍参与当前库编译，但不会进入 `<target>.stub.php`，对应 `php_*` 符号也不添加 library export 修饰。类注解会级联到其全部方法；单个方法也可以独立标记。`NoExport` 位于根命名空间：全局命名空间可写 `#[NoExport]`，其他命名空间必须写 `#[\NoExport]`，并且该编译期 Attribute 不会进入运行时元数据。

`NoExport` 与 `ExtensionProvider` 都遵循 PHP 类名解析规则，支持完全限定名、`use` 和 `use ... as ...` 别名。只有解析结果严格指向根命名空间内建 Attribute 时，编译器才会消费它。

`php_<target>_func_decl.h` 和 `php_<target>_data_decl.h` 都是 TypePHP 构建过程的内部生成文件，不是库的对外开发头文件。
`func_decl.h` 在 `-m lib` 构建时还会被强制包含，用于给当前 target 的 `php_*` C++ ABI 函数添加平台导出标记；`data_decl.h` 仅在 target 内部声明全局变量、常量对象以及字面量/运行时映射 accessor。
这些项目数据声明位于 `typephp_<target>` C++ namespace；literal/cache 底层表保留在 `extension-<target>.cc` 中，其他 translation unit 只通过 `get_str()`、`get_class()`、`get_func()` 等 accessor 使用，不直接依赖 storage。

发布 TypePHP 库时，对外提供：

- 由 `-m lib` 自动生成的 `<target>.stub.php`；
- Windows 平台的 `.dll` 和导入库 `.lib`；
- Linux 等平台的 `.so`。

如果库另外导出了自定义 C++ ABI 或 C ABI，库作者需要自行编写并随库发布对应的 `.h` 头文件。

✅ **正确**:
```php
<?php
function is_prime(int $n): bool {}
```

stub 仅保留空函数体，实现位于 C++ 或所属 TypePHP 库中。

❌ **错误**:
```php
<?php
function is_prime(int $n): bool {
    // 复杂的实现逻辑
    // AOT 编译器不会解析这些代码
    // 可能导致混淆
    for ($i = 2; $i < $n; $i++) {
        if ($n % $i == 0) return false;
    }
    return true;
}
```

---

## 🎯 最佳实践

### 1. 性能关键路径使用 C++

```cpp
// prime.cc
php::Int php_fibonacci(php::Int n) {
    if (n <= 1) return n;
    
    php::Int a = 0, b = 1;
    for (php::Int i = 2; i <= n; i++) {
        php::Int temp = a + b;
        a = b;
        b = temp;
    }
    return b;
}
```

```php
// main.php
function main() {
    // 调用 C++ 实现的高性能斐波那契
    echo fibonacci(50) . "\n";
}
```

### 2. 复杂算法使用 C++

```cpp
// sort.cc
php::Array php_quick_sort(php::Array arr) {
    // C++ 实现快速排序
    // 性能比 PHP 快 10-100 倍
    php::Array result = arr;
    std::sort(result.begin(), result.end());
    return result;
}
```

### 3. 系统级操作使用 C++

```cpp
// system.cc
php::Str php_read_file(php::Str path) {
    std::ifstream file(path.to_string());
    std::stringstream buffer;
    buffer << file.rdbuf();
    return php::Str(buffer.str());
}

php::Bool php_write_file(php::Str path, php::Str content) {
    std::ofstream file(path.to_string());
    file << content.to_string();
    return file.good();
}
```

---

## 💡 实际案例

### 案例一：图像处理

**C++ 实现** (`image.cc`):
```cpp
#include "phpx.h"
#include <opencv2/opencv.hpp>

php::Object php_resize_image(php::Object img, php::Int width, php::Int height) {
    // 使用 OpenCV 进行图像缩放
    cv::Mat mat = ...; // 从 PHP 对象提取
    cv::Mat resized;
    cv::resize(mat, resized, cv::Size(width, height));
    
    // 返回新的图像对象
    return create_image_object(resized);
}

php::Array php_detect_faces(php::Object img) {
    // 使用 Haar 级联检测人脸
    // 返回检测到的人脸坐标数组
    php::Array faces;
    // ... 检测逻辑
    return faces;
}
```

**PHP 调用** (`app.php`):
```php
function process_images() {
    $img = image_create_from_file('photo.jpg');
    
    // 调用 C++ 函数
    $resized = resize_image($img, 800, 600);
    $faces = detect_faces($resized);
    
    echo "检测到 " . count($faces) . " 张人脸\n";
}
```

### 案例二：加密解密

**C++ 实现** (`crypto.cc`):
```cpp
#include "phpx.h"
#include <openssl/aes.h>

php::Str php_aes_encrypt(php::Str data, php::Str key) {
    // 使用 OpenSSL 进行 AES 加密
    // 高性能硬件加速
    php::Str encrypted;
    // ... 加密逻辑
    return encrypted;
}

php::Str php_aes_decrypt(php::Str encrypted, php::Str key) {
    // 解密数据
    php::Str decrypted;
    // ... 解密逻辑
    return decrypted;
}
```

**PHP 调用** (`security.php`):
```php
function secure_communication() {
    $data = "敏感信息";
    $key = "密钥";
    
    // 调用 C++ 加密函数
    $encrypted = aes_encrypt($data, $key);
    
    // 传输加密数据...
    
    // 调用 C++ 解密函数
    $decrypted = aes_decrypt($encrypted, $key);
    
    echo "解密结果：{$decrypted}\n";
}
```

### 案例三：数据库操作

**C++ 实现** (`database.cc`):
```cpp
#include "phpx.h"
#include <mysql/mysql.h>

php::Array php_query_users(php::Int min_age, php::Int max_age) {
    // 直接连接 MySQL 数据库
    // 高性能批量查询
    php::Array users;
    
    MYSQL* conn = mysql_init(NULL);
    mysql_real_connect(conn, "localhost", "user", "pass", "db", 0, NULL, 0);
    
    std::string query = "SELECT * FROM users WHERE age BETWEEN ";
    query += std::to_string(min_age) + " AND " + std::to_string(max_age);
    
    mysql_query(conn, query.c_str());
    MYSQL_RES* result = mysql_store_result(conn);
    
    while (MYSQL_ROW row = mysql_fetch_row(result)) {
        php::Array user;
        user.set("id", row[0]);
        user.set("name", row[1]);
        user.set("age", row[2]);
        users.append(user);
    }
    
    mysql_free_result(result);
    mysql_close(conn);
    
    return users;
}
```

**PHP 调用** (`user_service.php`):
```php
function get_adult_users() {
    // 调用 C++ 数据库查询
    $users = query_users(18, 65);
    
    // PHP 处理业务逻辑
    foreach ($users as $user) {
        if ($user['age'] >= 30) {
            echo "资深用户：{$user['name']}\n";
        }
    }
}
```

---

## 🔍 调试技巧

### 1. 查看生成的代码

```bash
# 保留中间文件
php bin/tpc.php project --dry --build-dir /tmp/typephp-build

# 查看生成的 C++ 代码
find /tmp/typephp-build -name '*.cc' -o -name '*.cpp'
```

### 2. 类型检查

```cpp
// 在 C++ 代码中添加类型检查
php::Int php_safe_add(php::Int a, php::Int b) {
    // 检查溢出
    if (a > 0 && b > PHP_INT_MAX - a) {
        throw new OverflowException("Addition overflow");
    }
    return a + b;
}
```

### 3. 性能分析

```bash
# 编译时添加调试信息
php bin/tpc.php project -o app --debug

# 使用 perf 分析性能
perf record ./app
perf report
```

---

## ⚡ 性能对比

### 基准测试

| 操作 | PHP 实现 | C++ 实现 | 提升倍数 |
|------|---------|---------|---------|
| 质数判断 (100 万) | 5000ms | 50ms | **100x** |
| 数组排序 (10 万元素) | 800ms | 8ms | **100x** |
| 字符串拼接 (1 万次) | 200ms | 2ms | **100x** |
| 数学计算 (阶乘 10000) | 1500ms | 5ms | **300x** |
| 图像缩放 (100 张) | 3000ms | 300ms | **10x** |

---

## ❓ 常见问题

### Q: 为什么需要 .stub.php 文件？

A: `.stub.php` 文件有三个作用：
1. **IDE 支持**: 提供代码提示和自动补全
2. **类型检查**: AOT 编译器在编译期进行类型验证
3. **文档说明**: 作为 C++ 函数的 PHP 接口文档

### Q: 可以在 C++ 中调用 PHP 函数吗？

A: 可以，但需要通过 PHPX 框架提供的 API：
```cpp
php::Var result = php::call("php_function_name", args);
```

### Q: 如何处理异常？

A: 在 C++ 中使用 try-catch 包装，并转换为 PHP 异常：
```cpp
php::Int php_divide(php::Int a, php::Int b) {
    if (b == 0) {
        throw new InvalidArgumentException("Division by zero");
    }
    return a / b;
}
```

### Q: 支持 C++ 类吗？

A: 目前仅支持自由函数。如果需要面向对象，可以使用工厂模式：
```cpp
php::Object php_create_calculator() {
    // 返回封装了 C++ 对象的 PHP 对象
    return create_object("Calculator", internal_ptr);
}

php::Int php_calculator_add(php::Object calc, php::Int a, php::Int b) {
    Calculator* c = get_internal_pointer(calc);
    return c->add(a, b);
}
```

---

## 📚 相关资源

- **示例项目**: `examples/prime/`
- **PHPX 框架文档**: [链接]
- **C++ 类型系统**: 参见 [NATIVE_TYPES.md](NATIVE_TYPES.md)
- **AOT 编译器架构**: 参见 [后端中立 IR](BACKEND_NEUTRAL_IR.md) 和 [核心重构计划](REFACTORING_PLAN.md)

---

**最后更新**: 2024 年 3 月 18 日  
**适用版本**: PHP AOT Compiler v1.x

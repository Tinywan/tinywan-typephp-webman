# Swoole AOT 编译器编程语言互调用机制实例：PHP 调用 JNI 接口实现调用任意 Java 类方法

## 概述
`Swoole AOT` 编译器是一个`PHP`的静态编译器，可以将`PHP` 项目或代码直接编译为二进制可执行文件。
`AOT`编译器基于`ABI`模式实现了`PHP`与`C/C++`的互操作性，这使得`PHP`获得了与其他编程语言的直接调用能力，而不需要借助`FFI`或者编写`PHP`扩展。

本文将介绍如何使用`Java`的`JNI`接口，实现在 `PHP` 代码中直接调用 `Java` 类库 —— 创建 `Java` 对象、调用方法、读写字段，就像在 `Java` 代码中操作一样自然。

1. 本实例程序的代码全部由 `DeepSeek-4-Pro` 生成，耗时约为`50分钟`
2. GitHub: <https://github.com/swoole/typephp/tree/main/examples/jni>

## 准备工作

1. **JDK**（示例使用 `OpenJDK 25`，路径 `/usr/lib/jvm/java-25-openjdk-amd64`）
2. **PHP 8.2+** 及 **Swoole-Compiler 0.2.0**
3. **编译命令**：
```bash
swoole_compiler examples/jni/project.yml
```

## 核心接口

参见：`php-src/jni.stub.php`文件

```php
<?php
function jni_init(string $classpath = "."): void {}
function jni_destroy(): void {}
function jni_find_class(string $className): mixed {}
function jni_find_method(mixed $objOrClass, string $methodName): mixed {}
function jni_find_field(mixed $objOrClass, string $fieldName): mixed {}
function jni_new_object(mixed $classHandle, array $args = []): mixed {}
function jni_call(mixed $objOrClass, mixed $method, array $args = []): mixed {}
function jni_get(mixed $objOrClass, mixed $field): mixed {}
function jni_set(mixed $objOrClass, mixed $field, mixed $value): void {}
```

- 使用`jni_init、jni_destroy` 初始化 `JVM` 和销毁 `JVM`
- 使用`jni_find_class` 查找 `Java` 类，返回 **JniClass** 句柄
- 使用`jni_find_method` 查找 `Java` 方法，返回 **JniMethod** 句柄
- 使用`jni_find_field` 查找 `Java` 类属性字段，返回 **JniField** 句柄
- 使用`jni_new_object` 创建 `Java` 对象，返回 **JniObject** 句柄
- 使用`jni_call` 调用 `Java` 方法，返回方法返回值
- 使用`jni_get` 读取 `Java` 类属性字段，返回字段值
- 使用`jni_set` 修改 `Java` 类属性字段，返回字段值

在`jni.cc`代码中，会自定调用`Java`反射`API`获取类、方法、属性的类型，
并存储起来，在后续的调用中会根据反射信息，实现`PHP`类型与`Java`类型的自动转换。

### 类型转换
- Java `String` → PHP string
- Java `int/long/short/byte` → PHP int
- Java `float/double` → PHP float
- Java `boolean` → PHP bool
- 其他 `Java` 对象 → `JniObject` 句柄
- `void` → `null`


| PHP 类型 | 目标 Java 类型 | 转换说明 |
|----------|---------------|---------|
| string | `java.lang.String` | 通过 `NewStringUTF` 创建 jstring |
| string | 其他对象类型 | 自动转换为 jstring（可用于需要 `CharSequence` 等接口的参数） |
| int | int / long / short / byte | 直接转换为对应整数类型 |
| int | float / double | 隐式转换为浮点数 |
| float | float / double | 直接转换 |
| bool | boolean | 直接转换 |
| JniObject | 对应的 Java 对象类型 | 提取原始 jobject 传递 |

## 第一步：编写 Java 类

在项目根目录（或 `classpath` 可访问的位置）创建一个 `Java` 类：

```java
// Hello.java
public class Hello {
    private String name;
    private int age;

    public Hello(String name, int age) {
        this.name = name;
        this.age = age;
    }

    public String greet(String greeting) {
        return greeting + ", I'm " + name + ", " + age + " years old";
    }
}
```

编译为字节码：

```bash
javac Hello.java
```

除了调用自定义类之外，也可以调用`Java`标准库中的类，如 `java.lang.StringBuilder`、`java.lang.String` 等，
或者其他第三方类库，如 `com.google.gson.Gson`、`org.apache.commons.lang3.StringUtils` 等，需要使用`maven`等包管理工具
引入，并使用 `maven` 构建项目。


## 第二步：配置项目

创建 `project.yml`，指定 `JNI` 头文件和 `JVM` 库的路径：

```yaml
name: jni-example
version: 0.0.1
cxxflags: |
  -std=c++17
  -I/usr/lib/jvm/java-25-openjdk-amd64/include
  -I/usr/lib/jvm/java-25-openjdk-amd64/include/linux
  -Wall
ldflags: |
  -L/usr/lib/jvm/java-25-openjdk-amd64/lib/server
  -ljvm
  -Wl,-rpath,/usr/lib/jvm/java-25-openjdk-amd64/lib/server
sources:
  - php-src
  - ./cpp-src
  - main.php
```

> **说明**：
> - `cxxflags` 中 `-I` 指向 `JDK` 的 `JNI` 头文件目录
> - `ldflags` 中 `-ljvm` 链接 `JVM` 动态库，`-Wl,-rpath` 确保运行时能找到 `libjvm.so`
> - `sources` 中 `./cpp-src` 指向 `C++` 实现目录（内含 `jni.cc`），`php-src` 指向 `PHP stub` 文件目录

## 第三步：编写 PHP 程序

```php
<?php
// main.php
function main()
{
    // 1. 初始化 JVM，传入 classpath
    jni_init(".");
    echo "JVM initialized.\n";

    // --------------------------------------------------
    // 2. 操作自定义 Hello 类
    // --------------------------------------------------

    // 查找类 —— 返回 JniClass 句柄
    $helloClass = jni_find_class("Hello");

    // 查找方法和字段 —— 触发 Java 反射，返回缓存的 JniMethod / JniField 句柄
    $greet = jni_find_method($helloClass, "greet");
    $nameField = jni_find_field($helloClass, "name");
    $ageField = jni_find_field($helloClass, "age");

    // 创建对象 —— 构造器由参数数量自动匹配
    $hello = jni_new_object($helloClass, ["Swoole", 8]);

    // 调用方法
    $msg = jni_call($hello, $greet, ["你好"]);
    echo $msg . "\n";  // 你好, I'm Swoole, 8 years old

    // 读取字段
    $name = jni_get($hello, $nameField);  // "Swoole"
    $age  = jni_get($hello, $ageField);   // 8

    // 修改字段
    jni_set($hello, $nameField, "PHP");
    jni_set($hello, $ageField, 10);

    // 再次调用验证
    echo jni_call($hello, $greet, ["Hi"]) . "\n";
    // Hi, I'm PHP, 10 years old

    // --------------------------------------------------
    // 3. 操作标准 Java 类 (StringBuilder)
    // --------------------------------------------------

    $sbClass = jni_find_class("java.lang.StringBuilder");
    $append = jni_find_method($sbClass, "append");
    $toString = jni_find_method($sbClass, "toString");

    $sb = jni_new_object($sbClass, ["Hello"]);
    jni_call($sb, $append, [" Java"]);
    jni_call($sb, $append, [" JNI"]);

    $str = jni_call($sb, $toString, []);
    echo $str . "\n";  // Hello Java JNI

    // 4. 销毁 JVM
    jni_destroy();
}
```

## 第四步：编译运行

编译项目：

```bash
swoole_compiler examples/jni/project.yml
```

运行编译产物：

```bash
cd jni_example
LD_LIBRARY_PATH=/opt/php-8.4/lib:/path/to/phpx/lib:/usr/lib/jvm/java-25-openjdk-amd64/lib/server ./jni_example
```

> 请注意务必将`Hello.class`文件放在与`jni_example`相同的目录下。

执行结果：
```bash
swoole@swoole-26:~/workspace/aot/compiler$ ./jni_example 
=== Step 1: Initialize JVM ===
JVM initialized.

=== Step 2: Dynamic Java Object (Hello) ===
Found class: Hello
Created Hello object.
greet() → 你好, I'm Swoole, 8 years old
name = Swoole, age = 8
After set: name = PHP, age = 10
greet() → Hi, I'm PHP, 10 years old

=== Step 3: Standard Java Class (StringBuilder) ===
Found class: java.lang.StringBuilder
Created StringBuilder.
toString() → Hello Java JNI

=== Step 4: Destroy JVM ===
JVM destroyed.
```

## 内部原理

### 反射与缓存

当首次对某个类调用 `jni_find_method` 或 `jni_find_field` 时，C++ 层会触发**惰性反射（lazy reflection）**：

1. 通过 JNI 调用 `Class.getDeclaredConstructors()` 获取所有构造器
2. 通过 JNI 调用 `Class.getMethods()` 获取所有 public 方法（含继承的）
3. 通过 JNI 调用 `Class.getDeclaredFields()` 获取所有字段（含 private）
4. 对每个方法/字段，获取其参数类型、返回类型，转换为 JNI 签名
5. 调用 `GetMethodID` / `GetFieldID` 获取 JNI ID
6. 全部信息缓存在 JniClass 内部的 C++ map 中

后续对同类的方法/字段查找直接命中缓存，无需再调 `JNI`。

### Box 类型体系

```
Box (phpx 基类)
 ├─ JniClass   — 包装 jclass 全局引用 + 反射缓存
 ├─ JniMethod  — 包装 jmethodID + 所有重载的签名信息
 ├─ JniField   — 包装 jfieldID + 字段类型签名 + 静态/实例标记
 └─ JniObject  — 包装 jobject 全局引用
```

每个 `Box` 子类在构造时设置 `type_info` 字段，使得 `C++` 层无需 `RTTI` 即可安全地区分不同句柄类型。

## 限制与注意事项

1. **JVM 单实例**：一个进程只能创建一个 `JVM` 实例，`jni_init` 不可重复调用
2. **classpath**：运行目录需能访问到 `.class` 文件（通过 `jni_init` 的参数指定）
3. **无序字段**：`Java` 反射不保证方法/字段的返回顺序，方法重载匹配不依赖顺序
4. **资源释放**：`JniClass` / `JniObject` 的全局引用在 `PHPX Box` 析构时自动释放（`DeleteGlobalRef`），JVM 销毁时一并清理
5. **线程安全**：当前实现未考虑多线程场景（`AOT` 编译的程序默认为单线程模型）

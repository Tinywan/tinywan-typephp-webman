# PHP AOT Compiler Quick Start Guide

## 🚀 5-Minute Quick Start

This guide will help you get started with the PHP AOT compiler within 5 minutes.

---

## 📦 Prerequisites

### System Requirements
- **Operating System**: Linux (Ubuntu 20.04+ recommended)
- **PHP Version**: PHP 8.0+
- **Compiler**: GCC 9.0+ or Clang 10.0+
- **Memory**: at least 2GB RAM
- **Disk Space**: at least 500MB

### Installing Dependencies

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y build-essential php-cli php-dev clang-format

# CentOS/RHEL
sudo yum install -y gcc gcc-c++ php php-devel clang-tools-extra
```

---

## 🔧 Installation Steps

### 1. Clone the Project

```bash
git clone https://github.com/your-org/php-aot-compiler.git
cd php-aot-compiler
```

### 2. Install Composer Dependencies

```bash
composer install
```

### 3. Verify Installation

```bash
php bin/tpc.php --help
```

If you see the help information, the installation was successful!

---

## 🎯 Basic Usage

### Example Project Structure

Suppose we have a simple PHP project:

```
my-project/
├── src/
│   ├── Calculator.php
│   └── main.php
```

**Calculator.php**:
```php
<?php
class Calculator {
    public function add($a, $b) {
        return $a + $b;
    }
    
    public function multiply($a, $b) {
        return $a * $b;
    }
}
```

**main.php**:
```php
<?php
require_once 'Calculator.php';

function main() {
    $calc = new Calculator();
    
    echo "5 + 3 = " . $calc->add(5, 3) . "\n";
    echo "4 * 7 = " . $calc->multiply(4, 7) . "\n";
}
```

---

## 📝 Compilation Modes

### Mode One: Binary Executable (recommended for beginners)

#### Compilation Command

```bash
php bin/tpc.php my-project/src/ -o my-app
```

#### Running the Program

```bash
./my-app
```

#### Output

```
5 + 3 = 8
4 * 7 = 28
```

✅ **Advantages**:
- Runs standalone without a PHP environment
- Simple deployment
- Better performance

⚠️ **Note**: a `main()` function is required

---

### Mode Two: PHP Extension

#### Compilation Command

```bash
php bin/tpc.php my-project/src/ --mode=ext -o calculator
```

#### Installing the Extension

```bash
# Copy the .so file to the PHP extension directory
sudo cp calculator.so $(php-config --extension-dir)/

# Add to php.ini
echo "extension=calculator" | sudo tee /etc/php/8.1/cli/conf.d/30-calculator.ini
```

#### Using the Extension

```bash
php -m | grep calculator  # verify the extension is loaded
```

✅ **Advantages**:
- Integrates with existing PHP projects
- Can be used in php-fpm
- Suitable for web applications

⚠️ **Note**: no `main()` function is needed

---

## 🎨 Code Conventions

### ✅ Correct Code Structure

```php
<?php
// Class and function definitions (allowed at the global scope)
class MyClass {
    public function doSomething() {
        return "Something";
    }
}

function helperFunction() {
    return "Helper";
}

const MY_CONSTANT = 'value';

// Executable code must be inside the main() function
function main() {
    $obj = new MyClass();
    echo $obj->doSomething();
    echo helperFunction();
    echo MY_CONSTANT;
}
```

### ❌ Incorrect Code Structure

```php
<?php
// ❌ Free-floating executable code (not allowed)
echo "Hello World";  // error!

some_function_call();  // error!

for ($i = 0; $i < 10; $i++) {  // error!
    echo $i;
}
```

---

## 🧪 Running Tests

### Run a Single Test

```bash
PHPT=1 php run-tests.php tests/compiler/arrow_fn/001.phpt
```

### Run All Tests

```bash
PHPT=1 php run-tests.php tests/compiler/
```

### Viewing Test Results

```
PASS Arrow Functions - PHP 8.1+ short closure syntax
FAIL Some test
=====================================================================
Number of tests :   100                100
Tests passed    :    95 ( 95.0%)
Tests failed    :     5 (  5.0%)
```

---

## 💡 Best Practices

### 1. Project Organization

```
project/
├── src/              # source code
│   ├── Classes/      # class files
│   ├── Functions/    # function library
│   └── main.php      # entry file
├── tests/            # test files
└── build/            # compilation output
```

### 2. Naming Conventions

- File names use lowercase, with words separated by underscores: `my_class.php`
- Class names use PascalCase: `MyClass`
- Function names use camelCase: `myFunction`

### 3. Performance Tips

- Avoid unnecessary object creation
- Use scalar type declarations
- Reduce the use of global variables
- Prefer arrays over object collections

---

## 🔍 FAQ

### Q: Compilation fails with "Not implemented"
**A**: First check the [Compatibility Checklist](INCOMPATIBLE_PHP_FEATURES.md) to determine whether it is a TypePHP design rule, partially supported, or not yet implemented.

### Q: How do I debug the compiled program?
**A**: Use `--dry` to generate only the intermediate code, and specify a directory with `--build-dir`.

```bash
php bin/tpc.php src/ --dry --build-dir /tmp/typephp-build
```

### Q: What should I do if compilation is slow?
**A**: Use the parallel compilation option `-j`:

```bash
php bin/tpc.php src/ -o app -j4  # use 4 processes
```

---

## 📚 Next Steps

After completing the quick start, the following are recommended reads:

1. **[Compatibility Checklist](INCOMPATIBLE_PHP_FEATURES.md)** - understand the current limitations
2. **[Compilation Modes Explained](COMPILATION_MODES.md)** - learn the two compilation modes in depth
3. **[Build Speed Research](AOT_BUILD_SPEED_RESEARCH.md)** - optimize the compilation flow

---

## 🆘 Getting Help

- 📖 View the full documentation: [docs/](.)
- 🐛 Report issues: [GitHub Issues]
- 💬 Community discussion: [forum/chat room link]

---

**Enjoy!** 🎉

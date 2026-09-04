# PHP AOT Compiler Compilation Modes

## 📋 Overview

The PHP AOT compiler supports two compilation modes, each targeting different use cases. This document details the differences, usage, and best practices of the two modes.

---

## 🔹 Extension Mode

### Basic Concepts

Extension mode compiles PHP code into a PHP extension file (`.so` or `.dll`), which can be loaded into php-fpm as a standard PHP extension.

### Compilation Command

```bash
php bin/tpc.php <source_dir> --mode=ext -o <output_name>
```

### Example

```bash
# Compile the Coolify project
php bin/tpc.php projects/coolify/app/ --mode=ext -o coolify

# Output files
coolify.so  # Linux
coolify.dll # Windows
```

### Installation

#### 1. Temporary Loading (for testing)

```bash
php -d extension=./coolify.so -r "echo 'Extension loaded';"
```

#### 2. Permanent Loading (production)

```bash
# Copy the extension file to the PHP extension directory
sudo cp coolify.so $(php-config --extension-dir)/

# Create the configuration file
echo "extension=coolify" | sudo tee /etc/php/8.1/mods-available/coolify.ini

# Enable the extension
sudo phpenmod coolify

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

### Code Structure

**No `main()` function required**

```php
<?php
// Define classes
class UserController {
    public function index() {
        return "User List";
    }
}

// Define functions
function route_handler($path) {
    echo "Handling: {$path}";
}

// In extension mode, this code runs when called by PHP
// No main() function required
```

### Use Cases

✅ **Suitable for**:
- Web applications
- API services
- Integration with existing PHP frameworks
- Production environments relying on php-fpm
- SaaS platforms

❌ **Not suitable for**:
- Command-line tools
- Standalone services
- Long-running daemons

### Advantages

| Advantage | Description |
|------|------|
| 🔒 Security | Source code is compiled, not easily leaked |
| ⚡ Performance | 3-10x faster than pure PHP |
| 🔄 Compatibility | Fully compatible with the existing PHP ecosystem |
| 📦 Easy deployment | Standard PHP extension installation |

### Disadvantages

| Disadvantage | Description |
|------|------|
| 🔧 Depends on PHP | Requires a PHP runtime environment |
| 🌐 Web only | Primarily oriented toward web scenarios |
| ⚙️ Complex configuration | Requires configuring the PHP extension |

---

## 🔸 Binary Mode

### Basic Concepts

Binary mode compiles PHP code into a standalone executable, independent of the PHP runtime environment.

### Compilation Command

```bash
php bin/tpc.php <source_dir> -o <output_binary>
```

### Example

```bash
# Compile the Workerman project
php bin/tpc.php projects/workerman/src/ -o workerman

# Output file
workerman  # Linux executable
```

### Running

```bash
# Run directly
./workerman start

# Run in the background
./workerman start -d

# Check status
./workerman status
```

### Code Structure

**Must have a `main()` function**

```php
<?php
// Class definition
class Application {
    public function run() {
        echo "Application running\n";
    }
}

// ✅ Must define a main() function
function main() {
    $app = new Application();
    $app->run();
}

// ✅ Or a main() with arguments
function main(int $argc, array $argv) {
    echo "Arguments: " . implode(', ', $argv) . "\n";
    
    $app = new Application();
    $app->run();
}
```

### main() Function Signature

#### Approach 1: No arguments (default)

```php
function main() {
    // program entry point
}
```

#### Approach 2: With command-line arguments

```php
function main(int $argc, array $argv) {
    // $argc: number of arguments
    // $argv: argument array
    
    echo "Script: {$argv[0]}\n";
    if ($argc > 1) {
        echo "Arguments: " . implode(', ', array_slice($argv, 1)) . "\n";
    }
}
```

### Use Cases

✅ **Suitable for**:
- Command-line tools (CLI)
- Long-running services (such as Workerman)
- Service nodes in microservice architectures
- Standalone applications
- Batch processing tasks

❌ **Not suitable for**:
- Web applications (cannot be accessed in a browser)
- Scenarios requiring mixed execution with existing PHP code

### Advantages

| Advantage | Description |
|------|------|
| 🚀 Zero dependencies | No PHP installation required |
| 📦 Easy distribution | A single executable file |
| 🔐 Security | Fully compiled to machine code |
| ⚡ High performance | Optimized native code |

### Disadvantages

| Disadvantage | Description |
|------|------|
| 🖥️ Platform-dependent | Must compile separately for different systems |
| 🔄 Complex updates | Requires recompilation and replacement |
| 🌐 No web support | Cannot be used in php-fpm |

---

## 📊 Mode Comparison

### Detailed Comparison Table

| Feature | Extension Mode (`--mode=ext`) | Binary Mode (default) |
|------|---------------------|------------------|
| **Output format** | `.so` / `.dll` | Executable |
| **Runtime environment** | php-fpm / CLI | Standalone |
| **PHP dependency** | ✅ Required | ❌ Not required |
| **main() function** | ❌ Not required | ✅ Required |
| **Web access** | ✅ Supported | ❌ Not supported |
| **CLI execution** | ✅ Supported | ✅ Supported |
| **Deployment difficulty** | Medium | Easy |
| **Performance gain** | 3-10x | 5-20x |
| **Code protection** | Medium | Full |
| **Applicable scenarios** | Web applications | CLI tools / services |

### Selection Guidance

```
Need to run in a web environment?
├─ Yes → choose Extension mode
└─ No → need a main() function?
         ├─ Yes → choose Binary mode
         └─ Yes → choose Extension mode
```

---

## 🎯 Practical Examples

### Example 1: Web API (Extension Mode)

**Project structure**:
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
// Extension mode: no main() required
use App\Controllers\UserController;

$controller = new UserController();
$data = $controller->list();

header('Content-Type: application/json');
echo json_encode($data);
```

**Compile**:
```bash
php bin/tpc.php api-project/src/ --mode=ext -o api_extension
```

**Use**:
```bash
# Load as an extension in php-fpm
# Access through the web server
```

---

### Example 2: CLI Tool (Binary Mode)

**Project structure**:
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
// Binary mode: must have main()
function main(int $argc, array $argv) {
    $command = new Command();
    $command->execute(array_slice($argv, 1));
}
```

**Compile**:
```bash
php bin/tpc.php cli-tool/src/ -o mytool
```

**Use**:
```bash
./mytool arg1 arg2 arg3
```

---

## 💡 Best Practices

### Extension Mode

1. **Namespaces**: use unique namespaces to avoid conflicts
   ```php
   namespace MyProject\Api;
   ```

2. **Initialization**: provide an extension initialization function
   ```php
   function init_extension() {
       // initialization logic
   }
   ```

3. **Configuration**: support configuration via php.ini
   ```php
   ini_set('my_extension.enabled', '1');
   ```

### Binary Mode

1. **Error handling**: handle global exceptions in main()
   ```php
   function main() {
       try {
           // main logic
       } catch (Throwable $e) {
           fwrite(STDERR, $e->getMessage());
           exit(1);
       }
   }
   ```

2. **Signal handling**: handle system signals
   ```php
   function main() {
       pcntl_signal(SIGTERM, function() {
           echo "Shutting down...\n";
           exit(0);
       });
       
       // main loop
   }
   ```

3. **Logging**: implement logging functionality
   ```php
   function log_message($level, $message) {
       $timestamp = date('Y-m-d H:i:s');
       echo "[{$timestamp}] [{$level}] {$message}\n";
   }
   ```

---

## 🔍 Troubleshooting

### Extension Mode Issues

#### Issue: Extension fails to load

```bash
PHP Warning:  PHP Startup: Unable to load dynamic library
```

**Solutions**:
1. Check file permissions: `chmod 644 coolify.so`
2. Verify PHP version match: `php -v`
3. Check dependencies: `ldd coolify.so`

#### Issue: Segmentation Fault

**Solutions**:
1. Check for unsupported syntax in the code
2. Check the error log: `tail -f /var/log/php/error.log`
3. Use gdb to debug the core dump

### Binary Mode Issues

#### Issue: Permission denied

```bash
bash: ./myapp: Permission denied
```

**Solution**:
```bash
chmod +x myapp
```

#### Issue: Symbols not found

```bash
error while loading shared libraries
```

**Solution**:
```bash
# Set the library path
export LD_LIBRARY_PATH=/path/to/libs:$LD_LIBRARY_PATH

# Check the actual link path and dependencies
ldd ./app
```

---

## 📚 Related Documents

- [Quick Start Guide](QUICKSTART.md) - Get started with the AOT compiler
- [Compatibility Checklist](INCOMPATIBLE_PHP_FEATURES.md) - Understand current limitations
- [Build Speed Research](AOT_BUILD_SPEED_RESEARCH.md) - Optimize the compilation pipeline
- [Compatibility Classification](PHP_INCOMPATIBILITY_CLASSIFICATION.md) - Determine the nature and resolution direction of limitations

---

**Last updated**: March 18, 2024  
**Document version**: v1.0

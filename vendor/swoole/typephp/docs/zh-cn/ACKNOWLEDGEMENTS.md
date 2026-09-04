# 致谢

TypePHP 建立在语言设计者、编译器工程师、运行时开发者、标准委员会成员、维护者
以及开源社区数十年的工作成果之上。没有他们建立并持续完善的基础设施，就不会有
TypePHP。

## 项目开发者与贡献者

### 核心开发

- **[韩天峰（matyhtf）](https://github.com/matyhtf)** —— 主要开发者和维护者，负责
  编译器整体架构、PHP 到 C++ 的降级转换、类型系统、运行时集成、构建系统及项目
  方向。

### Git 历史中记录的贡献者

以下名单根据仓库中的 Git 作者信息整理，同一人的不同历史署名已经合并。名单仅按
仓库所记录的贡献活跃度排列，以便保持一致；这并不代表对贡献价值的排名。

| 贡献者 | 主要贡献领域 |
| --- | --- |
| **[Yurun](https://github.com/Yurunsoft)** | PHP 语义兼容、类型与引用正确性、Trait、Generator、类行为、Windows 支持及编译诊断 |
| **[NathanFreeman](https://github.com/NathanFreeman)** | 严格类型、Zend VM 调用安全、请求初始化、动态调用校验及控制流正确性 |
| **[hafung](https://github.com/hafung)** | 优化器正确性、内置函数严格参数语义及复合数组下标操作的安全求值 |
| **[Lucas Raineri Giandon（Giandonn）](https://github.com/Giandonn)** | 参数展开、`count()`、`intval()`、`class_exists()` 等路径的优化安全与 PHP 兼容行为 |
| **[Alessio Giacobbe](https://github.com/AlessioGiacobbe)** | Parser、名称解析、控制流、Trait 组合及表达式副作用保留 |
| **[yangweijie](https://github.com/yangweijie)** | 编译缓存与性能优化、构建改进、文档及真实项目示例 |
| **[Lorenzo Dessimoni（FunkyOz）](https://github.com/FunkyOz)** | PHP 有意不兼容特性的英文文档 |
| **[Pratik Bhujel](https://github.com/prateekbhujel)** | 可移植浮点数字面量生成，包括 `INF` 和 `NAN` 处理 |
| **[原点（yuan-dian）](https://github.com/yuan-dian)** | `gen_stub` 联合类型名称生成及 Zend 类型元数据正确性 |

Git 作者信息无法涵盖所有贡献形式。随着致谢记录继续完善，我们还会补充代码审查者、
Issue 报告者、测试者、文档作者及社区参与者。

### 社区与生态贡献者

对 TypePHP 的贡献并不局限于代码提交。使用真实项目参与测试、报告和复现 Bug、开发
衍生开源项目、撰写技术内容，以及向更多开发者介绍 TypePHP，都在推动项目不断成长。
我们同样感谢：

- **夏枫**
- **[Tinywan](https://github.com/Tinywan)**（[开源技术小栈](https://www.tinywan.com/)）
- **A000001**
- **青青子衿**
- **大星**
- **[原点](https://github.com/yuan-dian)**
- **Elijah**
- **小尹**
- **[杨维杰](https://github.com/yangweijie)**
- **[Albert Chen](https://github.com/albertcht)**
- **[Nuno Maduro（`nunomaduro`）](https://x.com/enunomaduro)** —— 感谢其通过
  Twitter/X 向更广泛的 PHP 社区介绍 TypePHP。

本节中的部分贡献者也出现在 Git 贡献者名单中；这里再次列名，是为了单独感谢他们在
测试、社区、生态建设或项目传播方面的贡献。

## 基础项目与标准

我们特别感谢：

1. **[GCC（GNU Compiler Collection）](https://gcc.gnu.org/)**

   GCC 在 GNU/Linux 及其他受支持的目标平台上编译和优化 TypePHP 生成的 C++
   代码。其成熟的优化器、链接器集成、平台支持和诊断能力，是 TypePHP AOT
   工具链的重要基础。

2. **[Clang/LLVM](https://llvm.org/)**

   LLVM 和 Clang 提供了现代 C++ 编译基础设施、高质量诊断、优化技术及配套工具，
   支撑 TypePHP 的多个目标平台，包括 macOS 和 WebAssembly 相关工具链。

3. **[Microsoft Visual C++（MSVC）](https://visualstudio.microsoft.com/vs/features/cplusplus/)**

   MSVC 提供原生 C++ 编译器、链接器、运行时库及 Windows SDK 集成，使 TypePHP
   工具链及其生成的应用程序能够运行于 Windows 平台。

4. **[ISO C++ 标准委员会（WG21）](https://www.open-std.org/jtc1/sc22/wg21/)**

   TypePHP 生成可移植的现代 C++ 代码。感谢 WG21 的成员和所有参与者持续制定、
   审议并推动 C++ 语言及标准库的发展；TypePHP 生成的代码和 PHPX 抽象均建立在
   这些标准之上。

5. **[PHP 语言](https://www.php.net/)及 [PHP 内核开发组](https://www.php.net/credits.php)**

   PHP 定义了 TypePHP 所实现的语言语义。PHP 内核开发者维护 Zend Engine、运行时
   API、标准库、兼容行为、源代码和测试套件，它们是 TypePHP 实现 PHP 兼容性的
   权威参考。

6. **[PHP-Parser](https://github.com/nikic/PHP-Parser) 及其创建者 [Nikita Popov](https://github.com/nikic)**

   PHP-Parser 为 TypePHP 提供可靠的 PHP 解析器和抽象语法树，是语义分析、校验、
   降级转换及 C++ 代码生成流程的基础。感谢 Nikita Popov，以及 PHP-Parser 的每一位
   贡献者和维护者。

7. **[GMP（GNU Multiple Precision Arithmetic Library）](https://gmplib.org/)**

   GMP 提供高效的任意精度整数运算，是 TypePHP `BigInt` 及相关高精度整数运算的
   底层基础。

8. **[GNU MPFR](https://www.mpfr.org/)**

   MPFR 提供舍入行为定义明确的可靠多精度浮点运算，是 TypePHP `BigFloat` 的数值
   运算基础。

9. **[mpdecimal / libmpdec](https://www.bytereef.org/mpdecimal/)**

   mpdecimal 提供正确舍入的任意精度十进制运算，其 C 与 C++ 库是 TypePHP
   `Decimal` 的数值运算基础。

## 辅助库与开发工具

TypePHP 的编译器、命令行工具、构建流程和质量保障体系还使用了许多职责明确的
开源项目：

- **[PHPX](https://github.com/swoole/phpx)** 提供 Zend API 的 C++ 抽象，是生成程序
  和 TypePHP 运行时集成的基础。
- **[Composer](https://getcomposer.org/)** 提供依赖管理、自动加载、软件包分发以及
  `vendor/bin` 编译器入口。
- **[CLImate](https://github.com/thephpleague/climate)** 提供结构清晰、易于阅读的
  命令行输出和编译诊断。
- **[TopSort](https://github.com/marcj/topsort.php)** 提供拓扑排序，用于解析声明及
  依赖关系的顺序。
- **[Symfony YAML](https://symfony.com/components/Yaml)** 负责解析 TypePHP 项目配置和
  WASI 构建配置文件。
- **[Symfony VarDumper](https://symfony.com/components/VarDumper)** 在开发和诊断过程中
  帮助清晰地检查编译器内部数据结构。
- **[AnsiKit](https://github.com/ajaxray/AnsiKit)** 为编译器输出提供终端样式和进度显示
  组件。
- **[PHPUnit](https://phpunit.de/)** 提供单元测试及编译器代码生成测试框架。
- **[PHPStan](https://phpstan.org/)** 对编译器的 PHP 实现执行静态分析。
- **[PHP CS Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer)** 帮助项目保持一致的
  PHP 代码风格。

工具链中使用的 PHP DOM 和 PCNTL 扩展，已包含在前文对 PHP 内核及扩展维护者的
整体致谢中。

我们同样感谢上述项目中所有未能逐一列名的贡献者。正是他们对开放标准、可移植
工具链、语言兼容性和开源软件的长期投入，使 TypePHP 成为可能。

上述名称和商标归各自权利人所有。本致谢仅用于表达感谢，不表示任何项目、组织或
贡献者对 TypePHP 的认可、担保或背书。

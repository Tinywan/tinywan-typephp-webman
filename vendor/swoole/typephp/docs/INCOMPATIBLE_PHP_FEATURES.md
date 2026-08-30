# AOT 与 PHP 不兼容特性清单

本文档只记录当前 AOT 编译器与标准 PHP 不兼容或受限的关键特性。

## 程序结构

- 全局作用域不允许可执行语句；只允许声明、`use`、`declare`、常量定义等静态结构。
- 函数和方法内部不允许声明函数。
- 函数和方法内部不允许声明具名类。
- 二进制模式必须定义全局 `main()`。
- `main()` 只允许无参数，或 `(int $argc, array $argv)`。
- `main()` 必须返回 `void`。

## 声明与类型

- 不支持可变变量 `$$var`。
- 暂不支持 PHP 8.5 `#[NoDiscard]`。
- 支持 PHP 8.5 `(void)` 显式丢弃语句；操作数仍会求值并保留副作用，不能在赋值、返回、参数或条件等值上下文中使用。
- PHP 8.5 `clone()` / clone-with 依赖实际链接的 `libphp` 版本不低于 8.5。公开、动态、private/protected/readonly 和 property hook 属性，以及调用顺序、错误传播和 callable 路径均有 PHPT 覆盖。
- PHP 8.4 property hooks 会编译为 AOT getter/setter，并注册对应的 Zend hook 元数据；直接属性读写、Reflection 和对象遍历均受支持。当前不支持对 hook 属性取引用。
- PHP 8.4 Reflection Lazy Object 不能用于 TypePHP AOT 类。AOT 类以 persistent internal class 注册，而 Zend 的 `zend_object_make_lazy()` 明确拒绝 internal class；运行时动态加载的 ZendPHP user class 不受此限制。
- 支持 `private(set)` 与 `protected(set)` 非对称属性可见性，包括 constructor property promotion；Zend-backed 对象通过 PHP 8.4+ 类级 object handler 执行作用域检查，并保留 promoted/set visibility/implicit final 反射标志；Native 对象通过编译期访问检查执行同等作用域规则。
- 支持 final constructor property promotion，但 TypePHP 要求同时显式声明 `public`、`protected` 或 `private`；不接受 PHP 8.5 的 `final int $value` 隐式 public promotion 写法。该语法作为 TypePHP 扩展不受所链接 `libphp` 的源码语法版本限制，使用 PHP 8.4 `libphp.so` 时仍然可用。
- TypePHP 禁止在全局或命名空间常量声明上使用 attributes；PHP 8.5 global constant attributes 不在支持范围内。class constant attributes 不受此限制。
- 不支持闭包或箭头函数按引用返回。
- 暂不支持 PHP 8.5 在全局常量、类常量、参数默认值或属性默认值中使用 `static function`；初始化表达式内嵌套的闭包同样会在编译期被拒绝。
- `__construct()` 不允许返回值。
- 参数默认值不允许出现在必填参数之前（`PHP`允许，但会直接丢弃此默认参数）。
- 不支持引用可变参数 `&...$args`。
- 联合类型、交叉类型、`nullable` 类型仍以 `mixed/any` 作为 C++ 表示，但静态阶段会利用已知表达式类型提前拒绝确定不兼容的参数、返回值和属性赋值；动态值仍保留运行时 type check。
- 局部变量类型一旦被静态推断为具体 native 类型，不支持在同一作用域内重新赋值为不兼容类型。

## declare

- 不支持 `declare(ticks=...)`。
- `declare(encoding=...)` 只允许 `UTF-8`。
- `declare(strict_types=...)` 只允许 `strict_types=1`。
- 不支持其他 `declare` 指令。

## 调用与引用

- `exit(message: $value)` 可作为 TypePHP named-argument 扩展使用；它与位置参数 `exit($value)` 进入同一退出路径。
- TypePHP 使用严格参数数量规则：非 variadic 函数不接受声明范围之外的额外参数；`func_get_args()` 不会隐式放宽签名。
- 已知签名的普通函数、普通方法和 native 直调支持引用参数及写回；不要把编译器内部跨 Trait 动态分派的限制误写成“TypePHP 不支持引用参数”。
- 闭包和箭头函数不支持引用参数。
- 引用赋值不支持从复杂静态属性表达式建立引用。
- 动态调用、闭包调用等编译期无法确定参数签名的调用，不能自动转换引用参数；需要显式使用 `refval()` 或等价关键词方法 `toRef()`。
- `refval()` / `toRef()` 只接受变量、数组元素或对象属性。
- 带 unpack 且尾部追加 named arguments 的调用会退化为动态调用，不能使用 native call。

## 对象模型

- `toInt()`、`toString()`、`toArray()` 等保留关键词方法先于普通对象方法解析；需要参数的同名业务方法不按普通对象方法语义调用。
- `toAny()` 和 `toRef()` 是不可覆盖的 TypePHP 关键词方法，普通 class-like 声明不得定义同名方法（方法名按 PHP 规则大小写不敏感）。Native class 仅可显式定义返回 `mixed/any` 的 `toAny()` 转换方法，不提供隐式转换；Native class 不支持 `toRef()`。
- 固定值类型属性未显式初始化时使用类型零值，不保留 ZendPHP 的完整 uninitialized 状态；因此 `??` 等依赖 uninitialized 状态的表达式可能不同。
- 禁止子类用同名 `private` 属性隐藏父类私有属性；`public` / `protected` 同名声明视为同一个继承 property slot，仍须满足类型、可见性和 `readonly` 兼容性要求。
- 为避免 typed property 写入路径引入额外动态检查，native typed property 在右值类型不确定或与属性类型不一致时会退化为 `setProperty()`；部分标量赋值可能遵循 Zend 弱类型转换，而不是 AOT 默认 strict 语义。

## 表达式与控制流

- `match` 的 arm condition 不能是 `match` 表达式。
- `foreach` by reference 的 value 只能是变量。
- `foreach` list destructuring 不支持按引用绑定元素。
- `std::vector`、`std::map`、`std::ordered_map` 在 `foreach` 期间禁止追加、插入、`unset()` 或整体替换；已有元素的非结构性更新仍可使用赋值运算符完成。
- 固定 native typed object property 不允许按 PHP 未初始化语义自由 `unset()`。
- native 类型变量执行 `unset()` 不会产生标准 PHP 的变量删除语义。

## 运行时动态能力

- `ClassName::class` 只支持字符串字面量或可静态解析的类名。
- `static::class` 在需要编译期常量类名的位置不支持。
- `__CLASS__` 只允许在 `class` 定义的代码段中使用（`PHP`允许，返回空字符串）。
- `__TRAIT__` 只允许在 `trait` 定义的代码段中使用（`PHP`允许，返回空字符串）。
- 动态属性链、动态类名、动态函数名和动态回调会统一走 Zend runtime fallback，不保证 native 优化；动态调用的引用参数仍需显式使用 `refval()` 或 `toRef()`。
- `Closure::bind()` 绑定静态闭包访问私有成员时，当前行为与标准 PHP 不完全一致。
- first-class callable 存入 typed nullable `Closure` 属性后，当前存在运行时稳定性限制。
- 所有源文件必须是 `UTF-8` 编码。

## 编译器自举与内部重构约束

本节描述编译器自身使用 TypePHP 编译时的约束，不是面向用户代码新增的 PHP 语义差异。

- 重构前，同一核心类内可静态解析的 `$this->method()` 会生成 native C++ 直调。引用参数会直接映射为 `php::Ref` 或 C++ 引用，写回语义正常。
- 将调用方和被调用方拆到不同 Trait 后，单独编译 Trait 本体时无法从 Trait 的 `$this` 确定最终宿主类。当前方法解析器可能将跨 Trait 调用降级为 Zend method call，例如生成 `this_.call(..., php::ArgList{value})`。
- 动态 method call 的 `ArgList` 不会仅凭被调 wrapper 的 arginfo 自动把普通实参升级为引用。若被调方法声明 `&$value`，wrapper 会通过 `getCallArgByRef()` 取参；调用方传入的却是普通值，结果是 `must be passed by reference` 警告，并且被调方修改无法写回调用方。
- 因此，编译器内部跨 Trait API 禁止使用引用输出参数和“修改传入标量/数组后由调用方读取”的协议。应返回结果值、元组数组或 DTO，例如用 `[$type, $class] = resolveTypeDecl(...)` 代替 `parseTypeDecl(..., &$class)`。
- 对字符串累加、数组排序、解析结果输出等内部 helper，优先设计为纯返回值：`$code .= format(...)`、`$files = sort(...)`。只有确认调用会保持 native 直调时，才允许依赖引用写回。
- 每次移动方法到 Trait、父类或独立组件后，必须使用自举产物重新编译至少一个覆盖该调用的测试；仅使用 `bin/tpc.php` 运行测试不能发现“源编译器正常、自举编译器退化”的问题。

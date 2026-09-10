# 强类型原生引用设计

本文描述 TypePHP 0.8 的强类型引用模型。这里的“强类型引用”是 C++ 引用，
不是 `php::Ref` 的另一种名字。

## 目标

TypePHP 默认把 `int`、`string`、`float`、`bool`、`array` 保存为固定 C++
类型。可静态证明不逃逸的原生引用不得迫使这些变量退化为 `php::Var`，也不得
允许动态代码静默改变它们的类型。Closure 的 `use (&$var)` 是例外：函数体生成
前的捕获分析会把对应局部变量加入退化表，使其从首次赋值起使用 `php::Var`；若捕获
目标是强类型参数，则保留函数 ABI，并在入口建立同名 `php::Var` 本地槽位。

```php
$value = 100;
$alias =& $value;
```

上述变量的逻辑类型分别为 `int` 和 `int&`。在可证明安全的原生路径中，生成
等价的 C++ 引用，而不是创建 Zend reference：

```cpp
php::Int value = 100;
php::Int &alias = value;
```

## 类型系统

新增五种引用类型：

| TypePHP 类型 | 引用目标 | C++ ABI |
| --- | --- | --- |
| `INT_REF` | `int` | `php::Int &` |
| `STR_REF` | `string` | `php::Str &` |
| `FLOAT_REF` | `float` | `php::Float &` |
| `BOOL_REF` | `bool` | `php::Bool &` |
| `ARRAY_REF` | `array` | `php::Array &` |

`REF` 继续专指动态 PHP 引用，C++ 存储为 `php::Ref`。不能把 typed-ref 与
`REF` 合并，否则编译器会丢失引用目标类型，赋值、运算和静态调用都将退化为
动态 `zval` 操作。

以下帮助函数必须成为唯一的类型转换入口：

- `isTypedRefType($type)`：是否为五种 typed-ref；
- `getReferenceType($type)`：值类型转为 typed-ref；
- `getReferencedType($type)`：typed-ref 转为值类型；
- `isAnyRefType($type)`：typed-ref 或动态 `REF`。

SSA 只能保留或缩窄引用目标类型，不得把 typed-ref 提升成 `VAR/REF`。

类型系统必须区分三个概念：

- **值类型**：表达式参与运算、普通赋值和按值传参时看到的 `INT/STR/...`；
- **绑定类型**：变量槽位是普通值、typed-ref，还是动态 `REF`；
- **引用根**：多个 typed-ref 最终指向的同一个固定存储变量。

例如 `$d =& $b` 中，`$b/$d` 的值类型都是 `INT`，绑定类型都是 `INT_REF`，
引用根都是 `$a`。绝大多数表达式只查询值类型；引用赋值、ABI、生命周期和移动优化
必须查询原始绑定类型，不能用解除引用后的类型代替。

建议在函数上下文中保存：

```text
localVars[$name]       = INT | INT_REF | REF | ...
typedRefRoots[$name]   = $rootName
typedRefBindings[$name] = $targetName
```

`getVarType()` 返回表达式值类型，`getRawVarType()` 返回绑定类型。

## 参数 ABI

带有五种明确类型的引用参数使用 C++ 引用：

```php
function increment(int &$value): void
{
    $value++;
}
```

```cpp
void php_increment(php::Int &value);
```

无类型、`mixed`、`any`、union 或运行时才能确定的引用参数继续使用
`php::Ref`。对象、resource、stream、Native Object、typed object、Box 和
`std` container 不支持 typed-ref；这些值本来就使用句柄、指针或共享对象语义，
再对变量槽位取引用没有明确收益，反而允许调用方替换其静态类型。

## 属性和数组元素

普通对象属性及静态属性必须通过 Zend 属性 API 取得 `php::Ref`。Typed
Property 创建 reference 时，Zend 会把 `zend_property_info` 附加为 type
source，后续写入仍受属性声明类型约束。不能把属性读成临时值后再创建引用，
否则会丢失该 type source。

PHP 数组元素是动态 `zval` 槽位，引用统一使用 `php::Ref`。数组元素也可能与
Typed Property 共享同一个 `zend_reference`，因此桥接层必须保留原始 reference，
不得复制或解除引用。

当 typed-ref 参数接收属性或数组元素时，由 `php::RefWrap<T>` 在栈上把
`php::Ref` 转成 `T&`：

```cpp
php::Ref property = /* attrRef() */;
php::RefWrap<php::Str> bridge(property);
php_update_name(bridge.typed());
```

构造时必须验证当前值类型。跨 Zend 边界的性能不属于 typed-ref 的零成本目标，因此
五种类型统一使用隔离代理，不直接暴露 `zend_reference` 内部 payload。这样即使原始
reference 在 typed-ref 函数重入 ZendVM 时被其他动态别名改成另一种类型，也不会让
`T&` 指向已经失效的 zval union member。

调用结束时比较入口快照、原始 reference 和代理：只有一侧发生修改时执行安全写回；
双方发生不同修改时抛出冲突错误，不能静默覆盖。`Array` 还必须比较 HashTable 身份，
因为“返回数组元素引用”会改变引用拓扑，即使数组值比较结果完全相同。数组代理使用
独立副本，避免 persistent/default HashTable 与 request 写路径混用；字符串代理也必须
持有 request-owned 存储，不能原地修改 persistent string。

## typed-ref 转换为动态 PHP 引用

动态函数、动态方法和 Closure 的引用签名在编译期不可确定。调用者显式使用
`std::ref()` 时，`php::RefWrap<T>` 为 typed local 提供真实的 `php::Ref`：

```cpp
php::Int value = 1;
php::RefWrap<php::Int> bridge(value);
php::call(callback, php::VarList{&bridge.ref()});
bridge.commit();
```

`commit()` 必须检查动态调用后的 zval 仍与 `T` 一致，然后写回 typed local；
类型不一致时抛出统一的 TypeError。不能依赖一个可能抛异常的析构函数完成检查。
这是明确的动态慢路径，可以继续按调用创建 Zend reference，不为减少分配引入函数级
缓存、复用状态或额外的引用身份管理。

桥接生命周期必须是“一次调用表达式”，不能是整个 PHP 语句。嵌套调用中，内层
调用返回后，必须在求值外层调用的下一个实参之前完成 `commit()`：

```php
outer(dynamicCall(std::ref($value)), nextArgument());
```

这里 `dynamicCall()` 的写回必须先于 `nextArgument()`。生成器可以使用立即执行
lambda，也可以捕获并在独立 C++ block 中输出该内层调用的
`beforeStmtLines/afterStmtLines`；关键是 wrapper、调用和成功/异常写回必须属于同一个
求值边界。多个 typed-ref 实参若指向同一变量，必须复用同一个 bridge，不能创建内容
互相覆盖的临时 reference。

调用抛出异常时，桥接层仍要按 PHP 的可观察语义处理已经发生的引用修改。生成代码
必须在重新抛出原异常前执行非破坏性的写回；若写回又发现类型错误，以类型错误替换
或链接到原异常的具体策略应由一个 PHPX helper 统一实现，不能在每个调用点复制
Zend 异常处理代码。

### `RefWrap` 的两种方向

`RefWrap<T>` 有两种互斥状态：

1. `T& -> php::Ref`：为动态调用创建 Zend reference，调用结束后验证并写回；
2. `php::Ref -> T&`：为静态 typed-ref 参数提供原生引用视图。

内部只区分 `T& -> php::Ref` 与 `php::Ref -> T&` 两种方向；成功提交后必须进入
`committed` 状态，保证显式 `commit()` 与 `noexcept` 析构不会重复写回。

| `T` | `php::Ref -> T&` 代理 | 额外要求 |
| --- | --- | --- |
| `Int` | 栈上 `Int` | 精确 `IS_LONG` |
| `Float` | 栈上 `Float` | 精确 `IS_DOUBLE`；固定 `Int&` 不隐式改成 `Float&` |
| `Bool` | 栈上 `Bool` | `IS_TRUE/IS_FALSE` |
| `Str` | request-owned `Str` | 禁止原地修改 persistent string |
| `Array` | 独立 `Array` 副本 | 同时检测内容与 HashTable/引用拓扑变化 |

`Bool` 不能直接映射的原因来自 Zend ABI：布尔值由 zval 的 `IS_TRUE/IS_FALSE`
类型标签表达，没有独立的 `bool` payload。

“存在可寻址 payload”不代表任何 reference 都可以无条件直连。普通 PHP reference
可能还有其他动态别名；typed 函数内部若发生重入调用，动态代码可能把同一个 refval
改成其他类型。随后继续通过 `Int&/Float&` 访问已经不再活动的 union member 是不安全的。

因此 `php::Ref -> T&` 不按 type source 选择不同内存模型。Typed Property 的 type
source 仍由 `Reference::operator=()` 在提交时执行 Zend 类型约束，但它不成为暴露裸
payload 的理由。统一代理牺牲的是动态桥接路径的少量性能，换取更简单、可验证的生命
周期规则；静态 TypePHP `T& -> T&` 主路径完全不经过这里。

### 调用表达式边界

C++17 对普通函数参数只保证参数之间不会交错求值，不保证从左到右：

```cpp
outer(first(), second()); // first/second 谁先执行未指定
```

而 braced initializer list 保证从左到右。TypePHP 固定参数容器已经使用
`php::VarList{...}`，typed-ref bridge 必须继续保留这一性质。实测 GCC 会先执行上述
示例的第二个参数，不能依赖某个编译器当前的选择。

临时对象通常在包含它的 full-expression 结束时析构。若把 `RefWrap` 临时对象直接
放入外层调用参数，它可能直到外层调用返回后才析构。正确结构是让每个需要桥接的
内层调用拥有独立的立即执行作用域：

```cpp
auto result = [&]() {
    php::RefWrap<php::Int> bridge(value);
    auto retval = dynamic_call(bridge.ref());
    bridge.commit();
    return retval;
}();
```

正常返回由显式 `commit()` 完成全部校验；若调用抛出异常，C++ 栈展开会在离开 lambda
前执行 `noexcept` 析构，只提交类型兼容的既有修改并保留原异常。`commitAfterException()`
属于 `RefWrap` 私有实现，生成代码不直接调用它。这里 `commit()` 和 bridge 的析构都在
lambda 产生结果之前。使用生成语句与局部 block 时必须保持相同边界。外层
`VarList{inner(), next()}` 才能保证 `inner()` 写回先于 `next()`；不能依赖外层
full-expression 的析构时机。

### 异常路径

动态调用在修改引用后抛出异常时，PHP 会保留已经发生的修改。桥接也必须在重新抛出
原异常之前写回类型兼容的值：

```php
function mutateThenThrow(int &$value): never
{
    $value = 99;
    throw new RuntimeException();
}
```

若临时 reference 被改成不兼容类型，则不能写入固定存储。无异常返回时抛出 TypeError；
已有异常正在传播时，bridge 保留原异常并丢弃不兼容写回，避免在异常展开期间再次抛出
C++ 异常。这个规则必须集中在 helper 中，析构函数始终保持 `noexcept`。

### 别名与逃逸

同一次动态调用中，如果实参最终拥有相同引用根，必须复用一个 `RefWrap`：

```php
$b =& $a;
$d =& $b;
$callback(std::ref($b), std::ref($d));
```

否则两个临时 reference 会各自保存一份值，提交顺序将覆盖修改，也破坏 PHP 的别名
身份。编译器应按 `typedRefRoots` 对 bridge 去重。

动态 PHP 代码还可能把引用保存到全局、对象属性或 Closure 中，使其超过本次调用。
栈上 native storage 无法安全支持这种逃逸。调用参数容器销毁后，`RefWrap` 应检查
`zend_reference` 是否仍有额外持有者；检测到逃逸时抛出明确错误。该 reference 内只
保存 native 值的副本，不保存 native 地址，因此不能形成悬空指针。需要长期 PHP 引用
身份的变量必须从一开始使用 `std::any()`/`php::Ref`。

`Args`、位置参数 Array 与 named-argument Array 也会暂时增加 reference 引用计数。
成功路径必须先清理这些编译器自有容器，再执行 `commit()`/逃逸检查；异常展开按 C++
逆序析构，自有参数容器同样必须先于较早构造的 `RefWrap` 释放。

## 局部引用约束

固定局部变量之间的一次性引用绑定可生成 C++ 引用：

```php
$source = 1;
$alias =& $source;
```

引用传播遵循 PHP 的值/引用上下文区别：

```php
$a = 100;
$b =& $a; // $b: INT_REF，引用 $a
$c = $b;  // $c: INT，按值复制并解除引用
$d =& $b; // $d: INT_REF，与 $b 一样引用 $a
```

因此，对 typed-ref 再取引用仍得到同一目标的 typed-ref；typed-ref 出现在普通赋值
右侧或普通值参数位置时必须物化为值。移动/last-use 优化不得消费 typed-ref 所指向的
原变量，尤其不能对 `Str&`、`Array&` 生成 `takeValue()` 或 `std::move()`。

### typed-ref 普通赋值

普通赋值修改引用目标，不会重新绑定引用：

```php
$a = 100;
$intRef =& $a;
$intRef = 101;   // 修改 $a
$intRef = '100'; // 编译期错误
```

检查规则如下：

- RHS 类型已知且不满足引用目标类型：TypePHP 编译期 fatal error；
- RHS 类型已知且兼容：按目标值类型生成赋值；
- RHS 为 `VAR/REF`，无法静态确定：只求值一次，执行运行时严格类型检查后赋值；
- 禁止让不兼容表达式穿透到 C++ 编译阶段形成难以理解的运算符/模板错误。

`$c = $intRef` 属于值上下文，`$c` 推导为 `INT`；`$d =& $intRef` 属于引用上下文，
`$d` 推导为 `INT_REF` 并继承相同引用根。

由于 C++ 引用不能重新绑定，初版作如下限制：

- typed-ref 只允许在两个函数局部变量之间建立；LHS 必须是尚未声明的新变量，RHS
  必须是已经存在的固定值 local 或 typed-ref；
- typed-ref 必须在编译器可证明的单一绑定点创建；
- 禁止在分支、循环中首次绑定 typed-ref；
- 禁止把已有 typed-ref 重新绑定到另一个变量；
- 禁止 `unset()` typed-ref；
- 当一个 fixed local 已成为其他 typed-ref 的引用根时，也禁止 `unset()` 或把该根
  重新绑定为另一个引用；
- `$array[] =& $typedLocal`、`$object->prop =& $typedLocal`、`global/static` 等会让引用
  超过 native local 生命周期的写法一律禁止；
- `$alias =& $object->typedProperty`、`$alias =& $array[$key]` 继续产生动态 `REF`，
  不产生长期持有 `RefWrap` 的 C++ 引用；
- 禁止返回指向函数局部存储的 typed-ref；
- Closure 按引用捕获局部变量时，不建立指向 native local 的 typed-ref；该变量在函数
  解析前自动退化为 `php::Var`，逃逸 Closure 使用 Zend reference；
- 参数 typed-ref 可以继续传给静态可解析的 typed-ref 参数。

Generator、可挂起 Fiber 回调以及任何可能超过调用者栈帧的执行体，不得持有指向调用者
native local 的 `T&`。初版应禁止这些函数使用 typed-ref 参数，而不是尝试推断实际
挂起路径。

返回引用暂不使用 `T&` ABI。对象属性、静态属性和数组元素的返回引用需要保留 Zend
reference/type source；局部 `T&` 又不能安全逃出当前栈帧。因此 `function &name()`
继续返回 `php::Ref`，并禁止返回 typed local/typed-ref 参数形成的临时 bridge。

无法证明稳定绑定时，用户必须显式使用 `std::any()` 选择动态 `php::Ref` 语义。

## 不变量

实现与优化器必须共同保持以下不变量：

1. **固定类型不变**：typed-ref 的目标在整个生命周期内始终是同一个 C++ 类型；
2. **绑定不变**：一个 C++ reference 构造后永不重新绑定；
3. **根唯一**：编译期已知的 typed-local 引用链被压缩到唯一 root，同一次调用中一个
   已知 root 只创建一个 bridge；
4. **只求值一次**：属性 receiver、数组 key、动态 callable 和每个参数表达式都不得重复求值；
5. **PHP 求值顺序**：参数及嵌套调用按照 PHP 的从左到右可观察顺序完成；
6. **调用级提交**：bridge 在本次调用完成后、下一个表达式开始前提交；
7. **不逃逸**：指向 native stack storage 的 Zend reference 不得在调用结束后存活；
8. **析构不抛异常**：所有失败通过显式 `commit()`/调用 helper 报告；
9. **值上下文解除引用**：普通赋值、按值参数、返回值和数组插入必须取得值快照；
10. **优化不得消费 root**：last-use、move、multi-return 和参数转发不得移动 typed-ref 的目标。

任何无法证明这些不变量的路径都应退回动态 `REF/VAR`（仅在源程序已经选择动态存储时），
或给出 TypePHP fatal error；不得生成“可能正确”的 C++。

## 语法到存储的决策表

| PHP 写法 | 结果 |
| --- | --- |
| 新 local `$b =& $a`，`$a` 是五种 fixed local | 对应的 `*_REF`，C++ `T&` |
| 新 local `$d =& $b`，`$b` 是 typed-ref | 相同 `*_REF`，引用根继承自 `$b` |
| `$c = $b`，`$b` 是 typed-ref | 对应值类型，按值复制 |
| `$b = $value`，`$b` 是 typed-ref | 检查后写入 root，不重新绑定 |
| `$b =& $other`，`$b` 已存在 | fatal error |
| `$b =& $property` / `$b =& $array[$key]` | 动态 `php::Ref` |
| `$property =& $typedLocal` / `$array[] =& $typedLocal` | fatal error，禁止 native 引用逃逸 |
| 精确 `int/string/float/bool/array &$arg` | native ABI `T&` |
| nullable/union/mixed/untyped/defaulted/variadic `&$arg` | Zend ABI `php::Ref` |
| object/resource/stream/container/typed-object `&$arg` | fatal error |
| 动态调用 `std::ref($typedLocal)` | 调用级 `RefWrap<T>` |
| 动态调用 `std::ref($property/arrayItem)` | 直接传原始 Zend reference |

## 调用分类

| 实参 | 静态 typed-ref 调用 | 动态调用/Closure |
| --- | --- | --- |
| typed local | 直接传 `T&` | `RefWrap<T>`，要求显式 `std::ref()` |
| typed-ref local/parameter | 直接传 `T&` | `RefWrap<T>`，要求显式 `std::ref()` |
| Typed Property | `Ref` → `RefWrap<T>::typed()` | 直接传原始 `php::Ref` |
| PHP 数组元素 | `Ref` → `RefWrap<T>::typed()` | 直接传原始 `php::Ref` |
| `php::Var/php::Ref` | 运行时校验后经 `RefWrap<T>` | 直接传 `php::Ref` |

## 性能模型

性能要求只覆盖编译期可确定的 TypePHP 原生调用与 typed-ref。不能把正确性建立在
“GCC 可能优化掉”之上：该主路径在生成结构上就必须没有装箱、Zend reference 和堆
分配，编译器优化只负责消除 C++ 引用别名并内联函数。`var-ref`、动态函数/方法和
Closure 调用继续沿用现有 Zend 动态分配路径，不为这些慢路径增加复杂缓存。

例如下面的调用必须直接生成：

```cpp
php::Int value = 1;
php::Int &alias = value;
php_increment(alias); // void php_increment(php::Int &)
```

即使使用 `-O0`，它也只传递一个地址，不申请堆内存；在函数体可见或启用 LTO 时，
GCC 通常会内联 `php_increment()` 并把 alias 完全消除。`php::Str&`、`php::Array&`
同样只传现有 wrapper 的地址，不产生 zval copy 或引用计数操作。

| 路径 | 额外堆分配 | 预期开销 |
| --- | --- | --- |
| typed local -> TypePHP typed-ref 参数 | 0 | 直接 `T&`，与普通 C++ 调用一致 |
| typed-ref local/参数 -> TypePHP typed-ref 参数 | 0 | 直接转发相同 `T&` |
| local typed alias | 0 | C++ reference，优化后通常不占独立存储 |
| Zend reference -> typed-ref | 不设零分配目标 | 隔离代理及安全提交；String/Array 可触发正常 COW |
| property/array item 首次变成 Zend reference | 通常 1 | Zend 建立 `zend_reference`，PHP 自身也需要 |
| typed local -> 动态 Zend 引用调用 | 每次调用、每个引用根通常 1 | 继续使用真实 `zend_reference`，明确慢路径 |

最后一项不能被 GCC 消除，也不能用栈上的伪造 `zend_reference` 替代。ZendVM、用户函数或扩展可能增加
引用计数并暂存该 reference；把 refcounted 对象放在 C++ 栈上会产生释放非法地址或悬空
引用。该分配只在用户显式 `std::ref($typedLocal)` 进入动态调用时发生，不得污染普通
原生调用；动态路径不承诺零分配。

### GCC 可优化边界

函数体可见或启用 LTO 时，以下开销可由 GCC/Clang 在 `-O2/-O3` 下完整消除：

- `T&` alias 本身；
- 只捕获引用的立即执行 lambda；
- 固定大小 `php::VarList/std::array` 的栈对象；
- inline 的 `typed()`、无分支的静态调用转发；
- 未取地址、未逃逸的小型代理对象。

这些对象均不得被装入 `std::function`，也不得通过 `new`、`shared_ptr` 或动态容器保存；
否则 lambda/类型擦除可能引入堆分配，GCC 也不能保证消除。

下列开销具有 Zend 可观察语义，不能假定编译器会优化掉：

- `ZVAL_NEW_REF`/`zend_reference` 分配与释放；
- String/Array 写入触发的 COW；
- zval 引用计数增减；
- 动态调用、type source 和逃逸检查；
- 异常实际发生时创建的 PHP/C++ 异常对象。

因此“零成本”只承诺静态 typed-ref 主路径。动态桥接不要求退化成裸 C++ 引用，也不为
减少其分配而牺牲现有 PHPX 封装。`try/catch` 和 lambda 只出现在确实需要桥接的路径；
公共异常/逃逸处理应下沉到 out-of-line 冷函数，避免无谓扩大生成代码。

### 生成代码约束

- `RefWrap` 是 header-only、非虚类，不使用 `std::function` 或 type erasure；
- 静态 typed-ref 主路径只使用 C++ `T&`，不得创建 wrapper 或 Zend reference；
- 动态/var-ref 路径可继续使用既有 `Args`、Zend reference 与动态内存分配，不为其性能
  引入函数级缓存、跨调用复用或裸指针旁路；
- 同一调用中可静态证明同根的 typed-local 实参按引用根去重并共享 reference 身份；
- 成功主路径不创建错误消息或临时字符串，异常检查放入 `UNEXPECTED` 冷分支；
- 调用参数容器销毁后立即 `commit()`，不跨调用缓存或延迟同步值；
- 每次提交后验证没有逃逸；
- 普通静态 typed-ref 调用不得生成方向判断、commit 或 refcount 操作；
- `Bool` 代理只用于 Zend bridge，native `Bool& -> Bool&` 仍是直接调用。

### 验证标准

重构完成后不仅运行功能测试，还要检查生成物：

1. 对纯 native typed-ref micro benchmark 生成 `-O3 -S` 汇编，确认 wrapper 和 lambda
   不存在、调用可正常 inline；
2. 统计静态 typed-ref 调用循环的额外 `malloc/emalloc` 次数，必须为 0；动态
   `std::ref()` 只记录基线，不设零分配目标；
3. 分别 benchmark `Int/Float/Bool/Str/Array` 的直接调用、property/array bridge 和动态
   `std::ref()`；
4. 比较 `-O0` 生成 C++ 的尺寸，避免每个调用点展开大段异常处理；公共慢路径放在 PHPX
   非模板 helper 中；
5. 使用 ASan/Valgrind 验证异常、逃逸检测和多别名调用没有泄漏或悬空引用。
6. 同时比较 `-O0` 与 `-O3`：正确性和分配上限不能依赖优化级别，`-O3` 只负责消除
   栈上胶水；使用 `-fno-inline` 时也不得出现额外堆分配。

目标是保证零成本静态主路径。动态 Zend bridge 保持现有、安全、易维护的 PHPX 路径，
不将它的性能问题扩大为 typed-ref 设计复杂度。

## 实施顺序

1. 增加五种类型及类型帮助函数，但暂不改变生成代码；
2. 将明确类型的引用参数 ABI 改为 `T&`，补齐 wrapper 入口；
3. 支持 typed local 的静态直接调用；
4. 实现属性、静态属性、数组元素到 `T&` 的 `RefWrap`；
5. 实现 typed local 到动态 `php::Ref` 的 `RefWrap` 与异常安全写回；
6. 最后支持受限的一次性局部 alias，并让 SSA/控制流检查拒绝重绑定。

每一步都必须同时覆盖命名函数、方法、构造函数、named arguments、静态调用、
动态调用、Closure、异常路径，以及实例/静态 Typed Property 和数组元素。

## PHP 8.5 与 C++17 验证结果

实际探针得到以下结论：

- PHP 按从左到右顺序求值调用参数；内层引用调用的修改在下一个参数求值前可见；
- 引用调用修改值后再抛异常，修改仍然保留；
- `$a/$b/$d` 在 `$b =& $a; $d =& $b` 后拥有相同的 `ReflectionReference` ID；
- `$c = $b` 得到独立值，后续修改 `$c` 不影响 `$a/$b/$d`；
- PHP 严格模式允许把普通 `int` 变量传给 `float&`，并把调用方变量改成 `float`。
  这与固定 `Int` 存储冲突，TypePHP typed-ref 应拒绝这种跨类型引用转换；
- Typed Property 创建的 reference 保留类型约束，不兼容写入立即抛出 TypeError；
- C++17 普通参数顺序未指定；列表初始化从左到右；放在外层参数中的临时 bridge
  可能直到外层函数返回后才析构；
- zval 的 `Int/Float` 有可寻址 payload，`Bool` 没有。

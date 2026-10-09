# PHP builder

在 `mode: bin` 下，TypePHP 将产物类型与 PHP SAPI 分开配置：

```yaml
mode: bin
sapi: [embed, cli, fpm]
php-builder:
  extensions: [swoole, mongodb]
  zts: on
```

`sapi` 用于选择一个或多个进程接口，默认值是 `embed`。`cli` 和 `fpm` 不是 mode；
由于宿主 PHP 安装不提供这些目标所需的静态构建输入，它们始终强依赖
`php-builder`。

`php-builder` 表示 TypePHP 从官方 php-src 归档构建私有 PHP 运行时。产物不依赖
宿主机 PHP 运行时，但会使用操作系统提供的开发库。官方 php-src 缓存不会被修改；
外部 PECL 扩展会注入派生的源码目录。显式 `extensions`、项目源码、YAML 依赖和
内嵌 Composer 元数据中的扩展需求会合并，并自动转换为 PHP configure 参数。

等价的命令行为：

```bash
bin/tpc.php project.yml \
  --sapi=embed,cli,fpm \
  --php-builder='extensions: [swoole, mongodb]; zts: on'
```

`zts` 接受 `on` 或 `off`。`php-builder.sapi` 是非法配置；SAPI 始终通过独立的
顶层 `sapi` 选项指定。

## 默认 Embed 行为

未配置 `php-builder` 时，`mode: bin` 加 `sapi: embed` 保持原有行为，链接宿主机
Embed 库（`libphp.so` 或 `libphp.dylib`）。Linux 或 macOS 上缺少该库时，交互式
运行会询问是否启用 `php-builder`。非交互环境必须显式选择：

```bash
bin/tpc.php project.yml \
  --php-builder='extensions: []; zts: off'
```

若拒绝提示，则没有可用的 Embed 运行时，构建会停止。

## 缓存与代理

下载的源码和私有运行时缓存在 `~/.typephp`，兼容的运行时会在不同应用构建之间复用。
`--proxy` 是全局网络设置，不属于 `php-builder`；PHP、PECL 元数据与归档，以及
TypePHP 的其他网络传输，都会使用指定的 HTTP(S) 或 SOCKS 代理。

```bash
bin/tpc.php project.yml --proxy=socks5h://127.0.0.1:1080 \
  --php-builder='extensions: []; zts: off'
```

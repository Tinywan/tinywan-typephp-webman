# PHP builder

For `mode: bin`, TypePHP separates the artifact type from the PHP SAPI:

```yaml
mode: bin
sapi: [embed, cli, fpm]
php-builder:
  extensions: [swoole, mongodb]
  zts: on
```

`sapi` selects one or more process interfaces. `embed` is the default. `cli`
and `fpm` are not modes and always require `php-builder` because a host PHP
installation does not provide the static build inputs needed by these targets.

`php-builder` means that TypePHP builds a private PHP runtime from an official
php-src archive. The result does not depend on the host PHP runtime, but it does
use development libraries supplied by the operating system. The original
php-src cache is left unchanged; external PECL extensions are injected into a
derived source tree. Extension requirements are merged from the explicit
`extensions` list, project sources, YAML dependencies, and embedded Composer
metadata, and then translated to PHP configure options.

The command-line equivalent is:

```bash
bin/tpc.php project.yml \
  --sapi=embed,cli,fpm \
  --php-builder='extensions: [swoole, mongodb]; zts: on'
```

`zts` accepts `on` or `off`. `php-builder.sapi` is invalid; SAPI selection is
always the independent top-level `sapi` option.

## Default Embed behavior

Without `php-builder`, `mode: bin` plus `sapi: embed` preserves the traditional
behavior and links the host Embed library (`libphp.so` or `libphp.dylib`). If it
is unavailable on Linux or macOS, an interactive invocation asks whether to
enable `php-builder`. In a non-interactive environment, choose explicitly:

```bash
bin/tpc.php project.yml \
  --php-builder='extensions: []; zts: off'
```

Declining the prompt leaves no usable Embed runtime and stops the build.

## Cache and proxy

Downloaded sources and private runtimes are cached under `~/.typephp`. Compatible
runtimes are reused across application builds. `--proxy` is a global network
setting rather than a `php-builder` field; PHP and PECL metadata and archives,
and any other TypePHP network transfers, use the configured HTTP(S) or SOCKS
proxy.

```bash
bin/tpc.php project.yml --proxy=socks5h://127.0.0.1:1080 \
  --php-builder='extensions: []; zts: off'
```

# TypePHP userspace runtime

This directory contains the TypePHP-OS platform layer shared by every Ring-3
program, whether its application entry point is compiled from C or TypePHP.
The top-level Makefile compiles it, the kernel ABI shims, and OpenLibm into:

```text
build/libtypephp-os.a
```

Build the archive once with:

```shell
make user-runtime
```

A TypePHP project should contain only its own application sources and link the
shared archive through its project file:

```yaml
link-paths:
  - ../../build
link-libs:
  - typephp-os
  - compiler-rt-builtins
```

The archive owns the sole userspace `_start`, PHP Nano host entry, syscall
bridge, POSIX/libc and C++ ABI shims, buffered `dirent` implementation, and
math implementation. C programs link the same archive directly; the linker
extracts only the members they actually reference, so they do not pull in the
PHP Nano host or its larger compatibility layer. Application projects must not
compile private copies of these files. This allows any number of projects
under `user/` to reuse one platform archive and keeps their generated object
directories independent.

Selected LLVM compiler-rt 128-bit integer helpers are built separately as
`build/libcompiler-rt-builtins.a`. Consumers link it after `typephp-os`. The
Ring-3 `builtins` command deliberately generates signed and unsigned
division/remainder helper calls and verifies the results at runtime.

Toybox is currently an integration probe rather than a runtime dependency.
See `THIRDPARTY.md` for the tested boundary and generated ABI report.

The archive and each consumer must use the same freestanding ABI flags and
PHP Nano/PHPX feature definitions. Those shared settings are maintained in the
top-level Makefile and the consumer project file respectively.

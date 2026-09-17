# TypePHP OS roadmap

This directory is a long-running experiment. Every milestone must remain
bootable in QEMU and must not change ordinary TypePHP, PHPX, or PHP Nano
behavior.

## Architecture rules

- The project is an ordinary `tpc --nano` consumer. tpc has no kernel option,
  and PHP Nano has no kernel source profile.
- PHP and Composer remain build-time tools; the kernel never links `libphp`.
- Keep copied php-src C/H files unchanged and compile the complete Nano source
  manifest. TypePHP OS owns the required libc/POSIX and C++ ABI boundary.
- Missing ABI operations must be linkable panic stubs. Replace each stub with
  a real kernel service before exposing the corresponding PHP capability.
- Cross-project changes must use generally useful portability contracts such
  as `PHP_NANO_NO_LIBC`, `PHPX_NO_EXCEPTION`, and `PHPX_NO_RTTI`; product-specific
  conditionals are not allowed in PHP Nano or PHPX.
- Ordinary kernel sources live under `kernel/`; the TypePHP entry files are at
  that directory's top level and the native implementation is under
  `kernel/core/`. Same-ABI source files and flags belong in `project.yml`.
  Startup sources live under `boot/` and are built by Make; compatible objects
  may be linked through tpc's generic `objects` facility. Architecture
  packaging and ELF/binary conversion happen after tpc emits the 64-bit ELF.
- Every completed milestone must pass the serial-output QEMU smoke test and
  leave the 64-bit payload with no undefined symbols.
- TypePHP-OS is a single-task system by design. `fork`, `clone`, `execve`,
  `wait`, pipes, threads, scheduling signals, and job control will not be
  implemented. The shell may only launch one synchronous foreground ELF
  through the private TypePHP-OS service.
- Keep `ioctl` request support deliberately narrow. The fixed serial/VGA
  console provides `TIOCGWINSZ`; a complete termios subsystem remains outside
  the current single-task shell requirements.

## Milestones

1. **Boot and scalar AOT — complete.** Multiboot v1 bootstrap, x86_64 long
   mode, VGA/COM1 output, TypePHP scalars, functions, and control flow.
2. **Memory and Zend containers — complete.** Physical arena, TypePHP OS ABI
   layer, original `zend_alloc`, `zend_gc`, `zend_string`, `zend_hash`, zval
   destruction, and generated TypePHP arrays/strings through PHPX.
3. **Zend object model — complete for the current demo.** Original object
   store, class entries, inheritance, interfaces, handlers, PHP exception
   classes, generated user classes, construction, properties, and destruction.
4. **Time service — complete.** CMOS wall clock, monotonic host hook, built-in
   date formatting, and the standard extension's `sleep()` path.
5. **Filesystem — fourth slice complete.** ATA PIO, a persistent FAT16 image,
   TypePHP-owned cluster/directory/file logic, file descriptors, POSIX
   directory/stat operations, and PHP's local file stream API. DOS 8.3 path
   lookup, file reads, directory enumeration, file/directory mutation, and
   same-parent rename traverse nested cluster chains. Directories grow by
   allocating and linking additional clusters. A 32 MiB direct-mapped
   read/write-through cache covers the current disk image while preserving
   synchronous persistence. A general VFS page cache, cross-directory rename,
   replacement semantics, and long filenames are next.
6. **Single-task userspace — fifth slice complete.** Disk-backed ELF64
   validation/loading from FAT16,
   GDT/TSS, Ring-3 entry, synchronous
   native x86-64 `SYSCALL` entry, COM1 standard I/O, saved parent context, shared
   syscall definitions, complete `argv[]`
   delivery, and independent freestanding C shell/command programs. The first
   Linux-compatible file syscalls cover `openat`, `read`, `write`, `lseek`,
   `close`, `mkdir`, `rmdir`, `unlink`, the stat/access families, synchronous
   persistence, file truncation, directory descriptors, and `getdents64`;
   `ls` now uses the standard buffered `dirent` API rather than a private
   directory-list syscall. Flag-oriented `fcntl` operations preserve
   close-on-exec, append, and nonblocking descriptor state, with append
   enforced by the write path; descriptor
   duplication and record locking remain future work. `cat`, `write`, `touch`, and the
   directory commands exercise persistent FAT16 changes. The `mv` command
   exposes same-parent rename through a private syscall until full Linux
   rename semantics are implemented. The resident shell
   and every command are independent files under `/BIN` rather than byte arrays embedded in
   the kernel, and command discovery no longer uses a compiled-in whitelist.
   Each process now has its own CR3 and recyclable 4 KiB physical pages. ELF
   segments retain `RX`/`RW` permissions, stacks have an unmapped guard, and
   NX plus supervisor write protection are enabled. Linux-compatible `brk`,
   anonymous private `mmap`, `mprotect`, and `munmap` provide the first real
   userspace memory-management ABI. Ring-3 CPU exceptions terminate a
   short-lived command and restore the saved shell; `fault.elf`, `vmfault.elf`,
   and `wrfault.elf` cover invalid-opcode, isolation, and write-protection
   recovery, while page accounting checks reclamation. The
   programs use standard C `main(argc, argv)` behind a shared crt0 and receive
   a Linux-style initial stack. A full PHP Nano/PHPX executable is now produced
   by the ordinary tpc pipeline and runs in Ring 3 with working TypePHP
   `argc/argv`; standard `php_uname()` reaches the userspace `uname()` ABI and
   reports TypePHP-OS. All shared userspace startup, syscall, POSIX/libc,
   C++ ABI, and math support is compiled once into `libtypephp-os.a`; both the
   C commands and multiple tpc projects link the same platform archive.
   Unavailable ABI functions remain explicit panic stubs.
   Fixed-console `ioctl(TIOCGWINSZ)` reports the VGA dimensions while other
   terminal requests return `ENOTTY`; a complete termios subsystem is not implied.
   A private memory-information snapshot now supports a tpc-built TypePHP
   `free` command with kernel Zend-arena, page-pool, reservation, and block-cache
   accounting. Release builds use `-O2` across the kernel and userspace.
   Selected upstream LLVM compiler-rt builtins now provide 128-bit integer
   helper symbols. A five-applet Toybox build is pinned as a porting probe but
   does not yet replace the working userspace commands.
   Single-task identity calls report PID/TID 1 for the resident shell, 2 for
   its synchronous foreground command, and root UID/GID. Linux-compatible
   wall-clock time uses the RTC while monotonic time and blocking sleeps use a
   100 Hz PIT. The `systest` ELF exercises this complete syscall slice.
   Syscall evolution follows the glibc migration rules in `user/README.md`.
7. **Native Class memory.** Exercise Wren GC through Zend MM and verify tracing
   of PHPX fields under sustained allocation.
8. **Kernel services.** The legacy PIC, a 100 Hz PIT clock, interrupt-driven
   COM1 input, blocking console reads, and blocking `nanosleep` are implemented.
   Higher-level VM region management, Zend-chunk reclamation, and a
   capability-oriented native API remain future work.
9. **Packaging and CI.** Automate the two-stage ELF32/ELF64 build and QEMU boot
   smoke test in GitHub Actions.

Later architectures may provide different bootstraps and host ABI adapters.
Generated TypePHP code and PHPX values remain 64-bit on every target.

Pure policy and data-processing work should continue moving to TypePHP. C is
kept for php-src reuse and narrow ABI/hardware adapters; assembly is kept only
where the CPU contract requires it. Suitable next migrations include shell
tokenization/dispatch and higher-level VFS/page-cache policy after the required
stream and console primitives are exposed to TypePHP userspace.

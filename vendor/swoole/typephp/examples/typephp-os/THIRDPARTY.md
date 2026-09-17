# Third-party source policy

The generated `thirdparty/` directory is intentionally ignored by Git. Every
third-party source dependency must instead be declared by a script under
`tools/` with all of the following information:

- an immutable upstream version or commit;
- the canonical download URL;
- a SHA-256 checksum for every downloaded archive or individual source file;
- an explicit list of files copied into the build tree, or an explicitly
  documented complete release tree for source-port candidates;
- the upstream license file.

Run the common fetch entry point before compiling:

```shell
./tools/fetch-thirdparty.sh
```

`make` invokes this command automatically. Each dependency has a dedicated
`fetch-*.sh` script, while `fetch-thirdparty.sh` is only the common entry point.
A matching local version marker makes repeated invocations offline and
effectively free. Third-party source must not be committed directly.

Run the upstream integration probes separately:

```shell
make thirdparty-smoke
make network-thirdparty-smoke
```

This target builds a deliberately small hosted Toybox and audits its undefined
ABI. It is not installed into the TypePHP-OS disk image by this probe.

The network probe builds a static, protocol-minimal dependency stack under
`build/network-thirdparty/install`. It verifies that libcurl exposes exactly
`HTTP` and `HTTPS`, with HTTP/2 supplied by nghttp2. This hosted build locks
down the upstream feature selection while the same sources are being ported to
the freestanding TypePHP-OS userspace ABI.

## lwIP

- Upstream: <https://github.com/lwip-tcpip/lwip>
- Version: `STABLE-2_2_1_RELEASE`
- Archive SHA-256: `ce0b7461c0ad9602c376f0bf07c5eb7253b48c7bf66f011c6bf3e2a96731c539`

The complete release tree is fetched, but `project.yml` selects only the raw
IPv4/TCP, ARP, ICMP, UDP/DNS, timeout, and Ethernet sources used by the kernel.
The lwIP socket-compatibility and netconn layers are not compiled.

## HTTPS and HTTP/2 userspace stack

- OpenSSL `3.5.8` LTS, archive SHA-256
  `a8f84a39918ec6415ce765d9b429d313ba97b8143169c172e734b9514464f5b2`
- nghttp2 `1.70.0`, archive SHA-256
  `e05cb1388eaca3830aded4ccf20044b6e1ac1a61411dcca11b0437c4285c8bc2`
- curl `8.22.0`, archive SHA-256
  `f7ef3ae8a22e521f289803fe93543eb64c329b58aa73a9e224dfd915a2a5f4f7`

All three are static userspace libraries. OpenSSL keeps TLS 1.2/1.3, X.509,
and the modern algorithms needed for public HTTPS servers, while omitting its
apps, tests, dynamic modules, legacy provider, QUIC/DTLS, and old TLS protocol
versions. nghttp2 builds only its C library. Only libcurl is built; the curl
command-line tool is excluded. libcurl enables only HTTP/HTTPS and
HTTP/2; FTP, FILE, IPFS, mail, LDAP, SMB, MQTT, WebSocket, proxy, compression,
IDN, PSL, and other optional dependencies are disabled. Redirect handling,
cookies, MIME, headers, and ordinary HTTP authentication remain available.

## OpenLibm

- Upstream: <https://github.com/JuliaMath/openlibm>
- Version: `v0.8.7`
- Commit: `9fbeafcd4f1b6ef6aa3946c1c8faead50f38a94d`
- Archive SHA-256: `e328a1d59b94748b111e022bca6a9d2fc0481fb57d23c87d90f394b559d4f062`
- Selected files: [`tools/openlibm-files.txt`](tools/openlibm-files.txt)

Only the architecture/compatibility headers and C sources required by
TypePHP-OS's current x86_64 double-precision math ABI are installed. The
selection keeps unused implementations out of the freestanding payload.

## LLVM compiler-rt builtins

- Upstream: <https://github.com/llvm/llvm-project/tree/llvmorg-23.1.1/compiler-rt/lib/builtins>
- Version: `23.1.1` (`llvmorg-23.1.1`)
- Selected files and per-file SHA-256 values:
  [`tools/compiler-rt-builtins-files.sha256`](tools/compiler-rt-builtins-files.sha256)

The source selection provides the x86-64 128-bit integer shift, multiply,
divide, and remainder helpers. It is compiled into
`build/libcompiler-rt-builtins.a` and linked after every kernel, bootstrap C,
and TypePHP Nano userspace object set. The `builtins.elf` smoke command forces
signed and unsigned 128-bit division so the archive is tested as a real linker
dependency instead of merely being compiled.

## Toybox

- Upstream: <https://codeberg.org/landley/toybox>
- Version: `0.8.14`
- Commit: `b7ec52ac35e075caffca5d330995d44e8dbfc8c3`
- Archive SHA-256: `827e4cdfd69f5da973e00e2a59b30b3c9857fb7fae74c362fd0b4f96be7929b0`
- Selected applets: [`tools/toybox-miniconfig`](tools/toybox-miniconfig)

The hosted probe enables only `cat`, `date`, `echo`, `pwd`, and `uname`; it
does not enable Toybox's shell or process-management commands. The undefined
host ABI is written to `build/thirdparty-smoke/toybox-undefined-symbols.txt`.
`ioctl` is a system-call API used by Toybox's shared C support library, not a
Toybox command. None of those five applet source files calls it directly, but
the common terminal/daemon helpers still leave an `ioctl` reference in the
hosted binary. TypePHP-OS now exposes the Linux-numbered syscall and implements
`TIOCGWINSZ` for its fixed console. Toybox still needs a freestanding build and
ABI audit before it can replace any existing command.

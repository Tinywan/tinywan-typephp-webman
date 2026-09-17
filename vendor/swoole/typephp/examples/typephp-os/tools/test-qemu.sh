#!/usr/bin/env bash

set -euo pipefail

kernel=${1:?kernel ELF is required}
disk=${2:?FAT16 disk image is required}
log=${3:?log path is required}

qemu_disk=$(mktemp /tmp/typephp-os-qemu.XXXXXX.img)
http_log="${log}.http-server"
python3 -m http.server 18080 --bind 127.0.0.1 \
    --directory "$(dirname -- "$0")" >"${http_log}" 2>&1 &
http_pid=$!
cleanup() {
    kill "${http_pid}" 2>/dev/null || true
    wait "${http_pid}" 2>/dev/null || true
    rm -f "${qemu_disk}"
}
trap cleanup EXIT
cp "${disk}" "${qemu_disk}"

sleep 0.2
if ! kill -0 "${http_pid}" 2>/dev/null; then
    cat "${http_log}"
    echo "failed to start the host HTTP smoke server" >&2
    exit 1
fi

if timeout 60 qemu-system-x86_64 \
		-cpu max \
		-m 512M \
        -kernel "${kernel}" \
        -drive file="${qemu_disk}",format=raw,if=ide,index=0 \
        -netdev user,id=net0 \
        -device e1000,netdev=net0 \
        -display none \
        -serial stdio \
        -monitor none \
        -no-reboot \
        -no-shutdown \
        -device isa-debug-exit,iobase=0xf4,iosize=0x04 \
        >"${log}" 2>&1 \
        <<< $'\ndate\nuname\nuname -a\nfree\necho Hello TypePHP userspace\nhello Dynamically loaded\ntnhello alpha beta\nsystest\nnettest\nbuiltins\nhttps\nmissing\nbad\nls /BIN\ncat HELLO.TXT\ntouch /EXPAND/F62.TXT\nls /EXPAND\nwrite NOTE.TXT Hello from Ring 3\ncat NOTE.TXT\ntouch EMPTY.TXT\nmkdir TMP\nls\nrm NOTE.TXT\ncat NOTE.TXT\nrmdir TMP\ncd TMP\nmkdir WORK\ncd WORK\nwrite NOTE.TXT Nested directory write\ncat NOTE.TXT\nmkdir SUB\ncd SUB\nwrite DEEP.TXT Deep directory write\nmv DEEP.TXT MOVED.TXT\ncat MOVED.TXT\ncat DEEP.TXT\npwd\ncd ..\nrmdir SUB\nrm SUB/MOVED.TXT\nrmdir SUB\nls\nrm NOTE.TXT\ncd ..\nrmdir WORK\ncd WORK\nmemtest\nfault\nvmfault\nwrfault\ndate\npwd\ncd BIN\npwd\nls\ncd ..\ncd DOCS\npwd\nls\ncd ..\nls\n'; then
    status=0
else
    status=$?
fi

if [[ ${status} -ne 124 ]]; then
    cat "${log}"
    echo "QEMU exited with ${status}; expected the resident kernel loop to reach the timeout" >&2
    exit 1
fi

grep -q "TypePHP OS POC" "${log}"
grep -q "Int bits: 64" "${log}"
grep -Eq '^RAM MiB: [1-9][0-9]*' "${log}"
grep -q "Zend MiB: 2" "${log}"
grep -q "Zend string/array: OK" "${log}"
grep -q "Kernel is!" "${log}"
grep -q "OpenLibm math: OK" "${log}"
grep -q "FAT16 file: Hello from TypePHP FAT16!" "${log}"
grep -q "FAT16 root: BIN/" "${log}"
grep -q "FAT16 sector cache: OK" "${log}"
grep -q "PHP file stream: PHP stream through TypePHP FAT16" "${log}"
grep -q "PHP directory scan: ., ..," "${log}"
grep -q "Calculate primes: 0-100" "${log}"
grep -q "Prime count: 25" "${log}"
grep -q "Prime list: 2, 3, 5, 7, 11, 13, 17, 19, 23, 29, 31, 37, 41, 43, 47, 53, 59, 61, 67, 71, 73, 79, 83, 89, 97" "${log}"
grep -q "Process 1: sh.elf (Ring 3)" "${log}"
grep -q "TypePHP-OS user shell" "${log}"
grep -q "Ring 3 confirmed" "${log}"
grep -q "Commands: ls, cd, pwd, date, uname, free, cat, echo, write, touch, mkdir, rm, rmdir, mv, memtest, systest, nettest, builtins, tnhello, https, fault, vmfault, wrfault" "${log}"
grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} UTC' "${log}"
grep -Fq $'TypePHP-OS\r' "${log}"
grep -Fq $'TypePHP-OS typephp-os 0.1 TypePHP Nano user mode x86_64\r' "${log}"
grep -q '^TypePHP-OS memory' "${log}"
grep -q '^RAM: total=.*used=.*free=' "${log}"
grep -q '^Kernel Zend arena: total=.*used=.*free=' "${log}"
grep -q '^Physical page pool: total=.*used=.*free=' "${log}"
grep -q '^ATA block cache: ' "${log}"
grep -q '^Hello TypePHP userspace' "${log}"
grep -q '^Dynamically loaded' "${log}"
grep -Fq 'Hello World!string(6) "8.4.14"' "${log}"
grep -Fq 'string(4) "nano"' "${log}"
grep -q '^bool(false)' "${log}"
grep -q '^int(3)' "${log}"
grep -Fq 'string(7) "tnhello"' "${log}"
grep -Fq 'string(5) "alpha"' "${log}"
grep -Fq 'string(4) "beta"' "${log}"
grep -Fq 'string(55) "TypePHP-OS typephp-os 0.1 TypePHP Nano user mode x86_64"' "${log}"
if [[ $(grep -c '^bool(true)' "${log}") -lt 3 ]]; then
    echo "the Nano smoke program did not complete its file and directory checks" >&2
    exit 1
fi
grep -q '^basic syscalls: OK' "${log}"
grep -q '^TCP HTTP socket: OK' "${log}"
grep -q '^lwIP DNS: OK' "${log}"
grep -q '^CSPRNG: OK' "${log}"
grep -q '^GET https://httpcan.org/get' "${log}"
grep -q '^HTTP status: 200' "${log}"
grep -q '^HTTP version: 2' "${log}"
grep -q '^TLS backend: OpenSSL/' "${log}"
grep -q '^OpenSSL SHA-256: ' "${log}"
grep -q '^compiler-rt builtins: OK' "${log}"
if grep -q 'unsupported TypePHP-OS user ABI' "${log}"; then
    echo "the Nano smoke program reached an unsupported userspace ABI" >&2
    exit 1
fi
if grep -q 'general protection (#13)' "${log}"; then
    echo "a userspace command caused an unexpected general-protection fault" >&2
    exit 1
fi
grep -q '^sh: missing: No such file or directory' "${log}"
grep -q '^sh: bad: Exec format error' "${log}"
grep -q '^/BIN' "${log}"
grep -q '^Hello from TypePHP FAT16!' "${log}"
grep -q '^F62.TXT' "${log}"
grep -q '^Hello from Ring 3' "${log}"
grep -q '^EMPTY.TXT' "${log}"
grep -q '^TMP' "${log}"
grep -q '^cat: No such file or directory' "${log}"
grep -q '^cd: no such directory' "${log}"
grep -q '^Nested directory write' "${log}"
grep -q '^Deep directory write' "${log}"
grep -q '^typephp-os:/WORK/SUB\$ cat DEEP.TXT' "${log}"
grep -q '^/WORK/SUB' "${log}"
grep -q '^rmdir: Directory not empty' "${log}"
grep -Fq 'typephp-os:/WORK$ ' "${log}"
grep -q '^brk/mmap: OK' "${log}"
grep -q 'User process 2 fault: invalid opcode (#6)' "${log}"
grep -q 'sh: fault: Input/output error' "${log}"
grep -q 'User process 2 fault: page fault (#14) .*address 0x0000000040000000' "${log}"
grep -q 'sh: vmfault: Input/output error' "${log}"
grep -q 'User process 2 fault: page fault (#14) .*address 0x000000004feff000' "${log}"
grep -q 'sh: wrfault: Input/output error' "${log}"
grep -Fq 'typephp-os:/$ ' "${log}"
grep -Fq 'typephp-os:/DOCS$ ' "${log}"
grep -Fq 'typephp-os:/BIN$ ' "${log}"
grep -q '^/DOCS' "${log}"
grep -q '^HELLO.TXT' "${log}"
cat "${log}"

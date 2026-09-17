#!/usr/bin/env bash

set -euo pipefail

project_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
kernel="${project_dir}/build/typephp-os.elf"
disk="${project_dir}/build/typephp-os.img"

if ! command -v qemu-system-x86_64 >/dev/null 2>&1; then
    echo "run.sh: qemu-system-x86_64 is not installed" >&2
    exit 1
fi

if [[ ! -r /dev/kvm || ! -w /dev/kvm ]]; then
    echo "run.sh: /dev/kvm is unavailable or is not accessible by the current user" >&2
    exit 1
fi

if [[ ! -f "${kernel}" ]]; then
    echo "run.sh: kernel image not found: ${kernel}" >&2
    exit 1
fi

if [[ ! -f "${disk}" ]]; then
    echo "run.sh: disk image not found: ${disk}" >&2
    exit 1
fi

qemu-system-x86_64 \
    -enable-kvm \
    -cpu host \
    -m 512M \
    -kernel "${kernel}" \
    -drive "file=${disk},format=raw,if=ide,index=0" \
    -netdev user,id=net0 \
    -device e1000,netdev=net0 \
    -display none \
    -serial stdio \
    -monitor none \
    -no-reboot \
    -no-shutdown \
    "$@"

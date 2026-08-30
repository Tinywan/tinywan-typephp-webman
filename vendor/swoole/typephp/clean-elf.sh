#!/bin/bash
#
# 删除根目录下编译临时产生的 ELF 可执行文件
#

DRY_RUN=false

if [ "$1" = "--dry-run" ] || [ "$1" = "-n" ]; then
    DRY_RUN=true
    echo "==> DRY RUN MODE (不会实际删除) <=="
fi

count=0
deleted=0

while IFS=: read -r path type; do
    case "$type" in
        *ELF*executable*)
            count=$((count + 1))
            if $DRY_RUN; then
                echo "  [DRY RUN] 将删除: $path"
            else
                rm -f "$path" && deleted=$((deleted + 1))
                echo "  已删除: $path"
            fi
            ;;
    esac
done < <(find "$(dirname "$0")" -maxdepth 1 -type f -exec file {} \; 2>/dev/null)

if $DRY_RUN; then
    echo "==> 共发现 $count 个 ELF 可执行文件（未实际删除）。运行 ./clean-elf.sh 执行删除。"
else
    echo "==> 共删除 $deleted 个 ELF 可执行文件。"
fi

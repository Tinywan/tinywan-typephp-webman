#!/usr/bin/env bash

set -euo pipefail

readonly MIN_AGE_MINUTES=60

dry_run=false
tmp_root=/tmp

usage() {
    cat <<'EOF'
Usage: ./cleanup-typephp-tmp.sh [options]

Remove inactive TypePHP temporary files and directories from /tmp.
An entry is skipped when it or any of its descendants was modified or
metadata-changed during the last 60 minutes.
Recognized prefixes: typephp-, typephp_, utils_test_, and phpx-windows.

Options:
  -n, --dry-run       Show what would be removed without deleting anything
      --tmp-dir DIR   Use another temporary directory (primarily for testing)
  -h, --help          Show this help
EOF
}

while (($# > 0)); do
    case "$1" in
        -n | --dry-run)
            dry_run=true
            ;;
        --tmp-dir)
            if (($# < 2)); then
                echo "Error: --tmp-dir requires a directory." >&2
                exit 2
            fi
            tmp_root=$2
            shift
            ;;
        -h | --help)
            usage
            exit 0
            ;;
        *)
            echo "Error: unknown option: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
    shift
done

if [[ ! -d "$tmp_root" ]]; then
    echo "Error: temporary directory does not exist: $tmp_root" >&2
    exit 1
fi

tmp_root=$(realpath -e -- "$tmp_root")
if [[ -z "$tmp_root" || "$tmp_root" == / ]]; then
    echo "Error: refusing to use an unsafe temporary directory." >&2
    exit 1
fi

readonly tmp_root
readonly owner_uid=${SUDO_UID:-$(id -u)}

format_size() {
    local kib=$1
    awk -v kib="$kib" 'BEGIN {
        if (kib >= 1048576) {
            printf "%.2f GiB", kib / 1048576
        } else if (kib >= 1024) {
            printf "%.2f MiB", kib / 1024
        } else {
            printf "%d KiB", kib
        }
    }'
}

entry_size_kib() {
    local output
    output=$(du -sk -- "$1" 2>/dev/null) || {
        printf '0'
        return
    }
    printf '%s' "${output%%$'\t'*}"
}

has_recent_entry() {
    local candidate=$1
    local recent

    # Check the complete tree. Looking only at the top-level directory mtime
    # would miss writes to an existing file in a nested build directory.
    if ! recent=$(find -P "$candidate" -xdev \
        \( -mmin "-${MIN_AGE_MINUTES}" -o -cmin "-${MIN_AGE_MINUTES}" \) \
        -printf '1' -quit 2>/dev/null); then
        return 0
    fi

    [[ -n "$recent" ]]
}

matched_count=0
removed_count=0
skipped_recent_count=0
skipped_error_count=0
total_kib=0

while IFS= read -r -d '' candidate; do
    ((matched_count += 1))

    # Keep the target constrained to one direct child of the selected root.
    if [[ "$candidate" != "$tmp_root"/* || "${candidate%/*}" != "$tmp_root" ]]; then
        echo "[skip unsafe] $candidate" >&2
        ((skipped_error_count += 1))
        continue
    fi

    if has_recent_entry "$candidate"; then
        echo "[skip recent] $candidate"
        ((skipped_recent_count += 1))
        continue
    fi

    size_kib=$(entry_size_kib "$candidate")
    size=$(format_size "$size_kib")

    # The size scan can take noticeable time for a large build tree. Recheck
    # freshness immediately before acting in case a compiler started using it.
    if has_recent_entry "$candidate"; then
        echo "[skip recent] $candidate"
        ((skipped_recent_count += 1))
        continue
    fi

    if $dry_run; then
        echo "[would remove] $size  $candidate"
        ((removed_count += 1))
        ((total_kib += size_kib))
        continue
    fi

    if [[ -d "$candidate" && ! -L "$candidate" ]]; then
        if rm -rf --one-file-system -- "$candidate"; then
            echo "[removed] $size  $candidate"
            ((removed_count += 1))
            ((total_kib += size_kib))
        else
            echo "[skip error] failed to remove: $candidate" >&2
            ((skipped_error_count += 1))
        fi
    elif rm -f -- "$candidate"; then
        echo "[removed] $size  $candidate"
        ((removed_count += 1))
        ((total_kib += size_kib))
    else
        echo "[skip error] failed to remove: $candidate" >&2
        ((skipped_error_count += 1))
    fi
done < <(
    find -P "$tmp_root" -mindepth 1 -maxdepth 1 -uid "$owner_uid" \
        \( -name 'typephp-*' -o -name 'typephp_*' \
        -o -name 'utils_test_*' -o -name 'phpx-windows*' \) -print0
)

if $dry_run; then
    action='would remove'
else
    action='removed'
fi

printf 'Summary: matched %d, %s %d (%s), skipped recent %d, errors %d.\n' \
    "$matched_count" "$action" "$removed_count" "$(format_size "$total_kib")" \
    "$skipped_recent_count" "$skipped_error_count"

if ((skipped_error_count > 0)); then
    exit 1
fi

#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

export PHPRC="$SCRIPT_DIR"
export LD_LIBRARY_PATH="$SCRIPT_DIR:$SCRIPT_DIR/lib:$LD_LIBRARY_PATH"

if [ ! -f "$SCRIPT_DIR/webman-server" ]; then
    echo "[ERROR] webman-server executable not found in $SCRIPT_DIR!"
    exit 1
fi

chmod +x "$SCRIPT_DIR/webman-server" 2>/dev/null || true
exec "$SCRIPT_DIR/webman-server" "$@"

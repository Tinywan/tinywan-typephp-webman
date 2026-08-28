#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

export PHPRC="$SCRIPT_DIR"
export LD_LIBRARY_PATH="$SCRIPT_DIR:$LD_LIBRARY_PATH"

exec "$SCRIPT_DIR/webman-server" "$@"

#!/usr/bin/env bash

# Keep per-invocation compiler metrics separate from the compiler's output so
# run-tests.php sees exactly the same stdout, stderr, and exit status.

set -u

script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
project_dir=$(cd "${script_dir}/../.." && pwd)
metrics_file=${TYPEPHP_TPC_METRICS_FILE:-${project_dir}/build/phpt-metrics/tpc-invocations.tsv}

exec /usr/bin/time -q -a -o "${metrics_file}" \
    -f $'%x\t%e\t%U\t%S\t%P\t%M\t%F\t%R\t%I\t%O\t%c\t%w\t%C' \
    "${project_dir}/tpc" "$@"

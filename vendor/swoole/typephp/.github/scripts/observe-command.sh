#!/usr/bin/env bash

# Collect low-frequency host and process metrics while preserving the observed
# command's stdout, stderr, and exit status.

set -u

if [[ $# -lt 3 ]]; then
    echo "Usage: $0 <output-directory> -- <command> [arguments ...]" >&2
    exit 2
fi
if [[ $2 != "--" ]]; then
    echo "Usage: $0 <output-directory> -- <command> [arguments ...]" >&2
    exit 2
fi

output_dir=$1
shift 2
mkdir -p "${output_dir}"

metadata_file="${output_dir}/environment.txt"
samples_file="${output_dir}/resources.log"
processes_file="${output_dir}/processes.log"
memory_maps_file="${output_dir}/tpc-memory.log"
time_file="${output_dir}/command-time.txt"
disk_file="${output_dir}/disk-usage.txt"

cgroup_dir=/sys/fs/cgroup
if [[ -r /proc/self/cgroup ]]; then
    cgroup_v2_path=$(awk -F: '$1 == "0" { print $3; exit }' /proc/self/cgroup)
    if [[ -n ${cgroup_v2_path} && -d /sys/fs/cgroup${cgroup_v2_path} ]]; then
        cgroup_dir=/sys/fs/cgroup${cgroup_v2_path}
    fi
fi

write_environment() {
    {
        echo "timestamp_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
        echo "runner_image=${ImageOS:-unknown}"
        echo "runner_image_version=${ImageVersion:-unknown}"
        echo "runner_arch=${RUNNER_ARCH:-unknown}"
        echo "workspace=${GITHUB_WORKSPACE:-$PWD}"
        echo
        uname -a
        echo
        lscpu
        echo
        free -b
        echo
        df -B1 -T . /tmp
        echo
        ulimit -a
        echo
        cat /proc/self/cgroup
        echo
        echo "cgroup_directory=${cgroup_dir}"
        for source in \
            "${cgroup_dir}/cpu.max" \
            "${cgroup_dir}/cpuset.cpus.effective" \
            "${cgroup_dir}/memory.max" \
            "${cgroup_dir}/memory.swap.max" \
            "${cgroup_dir}/pids.max"; do
            if [[ -r ${source} ]]; then
                echo "${source}=$(cat "${source}")"
            fi
        done
        echo
        sed -n -E '/^(MemTotal|MemFree|MemAvailable|Buffers|Cached|SwapCached|SwapTotal|SwapFree|Dirty|Writeback|AnonPages|Mapped|Slab|SReclaimable|PageTables):/p' /proc/meminfo
    } >"${metadata_file}" 2>&1
}

write_binary_details() {
    local binary=${TYPEPHP_OBSERVED_BINARY:-./tpc}
    local details_file="${output_dir}/binary.txt"

    {
        echo "path=${binary}"
        stat "${binary}"
        echo
        file "${binary}"
        echo
        size -A "${binary}"
        echo
        readelf -lW "${binary}"
        echo
        ldd "${binary}"
    } >"${details_file}" 2>&1 || true
}

write_resource_sample() {
    local label=$1
    local source

    {
        echo "===== ${label} $(date -u +%Y-%m-%dT%H:%M:%SZ) ====="
        echo "loadavg=$(cat /proc/loadavg)"
        sed -n '1p' /proc/stat
        free -b
        df -B1 . /tmp

        for source in /proc/pressure/cpu /proc/pressure/memory /proc/pressure/io; do
            if [[ -r ${source} ]]; then
                echo "--- ${source}"
                cat "${source}"
            fi
        done

        echo "--- /proc/meminfo"
        sed -n -E '/^(MemFree|MemAvailable|Buffers|Cached|SwapCached|SwapFree|Dirty|Writeback|AnonPages|Mapped|Slab|SReclaimable|PageTables):/p' /proc/meminfo

        echo "--- /proc/vmstat"
        sed -n -E '/^(pgfault|pgmajfault|pswpin|pswpout|pgscan_[^ ]+|pgsteal_[^ ]+|workingset_refault_[^ ]+|nr_dirty|nr_writeback) /p' /proc/vmstat

        for source in \
            "${cgroup_dir}/cpu.stat" \
            "${cgroup_dir}/cpu.pressure" \
            "${cgroup_dir}/memory.current" \
            "${cgroup_dir}/memory.peak" \
            "${cgroup_dir}/memory.events" \
            "${cgroup_dir}/memory.pressure" \
            "${cgroup_dir}/io.stat" \
            "${cgroup_dir}/io.pressure"; do
            if [[ -r ${source} ]]; then
                echo "--- ${source}"
                cat "${source}"
            fi
        done
    } >>"${samples_file}" 2>&1

    {
        echo "===== ${label} $(date -u +%Y-%m-%dT%H:%M:%SZ) ====="
        ps -eo pid,ppid,etimes,pcpu,pmem,rss,vsz,min_flt,maj_flt,stat,comm --sort=-rss | head -n 31
    } >>"${processes_file}" 2>&1
}

write_tpc_memory_sample() {
    local label=$1
    local pid

    {
        echo "===== ${label} $(date -u +%Y-%m-%dT%H:%M:%SZ) ====="
        while read -r pid; do
            [[ -n ${pid} ]] || continue
            echo "--- pid=${pid}"
            sed -n -E '/^(Name|Pid|PPid|VmPeak|VmSize|VmRSS|RssAnon|RssFile|RssShmem|VmSwap):/p' "/proc/${pid}/status" 2>/dev/null || true
            sed -n -E '/^(Rss|Pss|Pss_Dirty|Shared_Clean|Shared_Dirty|Private_Clean|Private_Dirty|Anonymous|Swap):/p' "/proc/${pid}/smaps_rollup" 2>/dev/null || true
        done < <(pgrep -x tpc 2>/dev/null || true)
    } >>"${memory_maps_file}" 2>&1
}

write_disk_usage() {
    local label=$1

    {
        echo "===== ${label} $(date -u +%Y-%m-%dT%H:%M:%SZ) ====="
        if [[ -e ./tpc ]]; then
            stat --format='tpc bytes=%s blocks=%b block_size=%B' ./tpc
        fi
        df -B1 -T . /tmp
    } >>"${disk_file}" 2>&1
}

observed_pid=''
interrupted=0

forward_signal() {
    interrupted=1
    if [[ -n ${observed_pid} ]]; then
        kill -TERM "${observed_pid}" 2>/dev/null || true
    fi
}

trap forward_signal INT TERM

write_environment
write_binary_details
write_disk_usage before
write_resource_sample before

/usr/bin/time -v -o "${time_file}" -- "$@" &
observed_pid=$!

# Poll once per second so completion is delayed by at most one second. The
# expensive snapshot is taken only once every 20 seconds.
sample_count=0
while kill -0 "${observed_pid}" 2>/dev/null; do
    sleep 1
    sample_count=$((sample_count + 1))
    if ((sample_count % 20 == 0)) && kill -0 "${observed_pid}" 2>/dev/null; then
        write_resource_sample "sample-${sample_count}s"
    fi
    if ((sample_count % 60 == 0)) && kill -0 "${observed_pid}" 2>/dev/null; then
        write_tpc_memory_sample "sample-${sample_count}s"
    fi
done

wait "${observed_pid}"
status=$?
observed_pid=''

write_resource_sample after
write_disk_usage after

echo "Observed command resource summary:"
cat "${time_file}"
echo "Detailed samples: ${output_dir}"

if ((interrupted != 0)) && ((status == 0)); then
    status=143
fi

exit "${status}"

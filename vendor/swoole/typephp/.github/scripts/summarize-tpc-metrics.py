#!/usr/bin/env python3

"""Summarize the append-only GNU time records produced by observe-tpc.sh."""

from __future__ import annotations

import csv
import math
import pathlib
import statistics
import sys


COLUMNS = (
    "exit",
    "wall_seconds",
    "user_seconds",
    "system_seconds",
    "cpu_percent",
    "max_rss_kb",
    "major_faults",
    "minor_faults",
    "filesystem_inputs",
    "filesystem_outputs",
    "involuntary_context_switches",
    "voluntary_context_switches",
    "command",
)


def percentile(values: list[float], fraction: float) -> float:
    ordered = sorted(values)
    if not ordered:
        return 0.0
    index = max(0, math.ceil(len(ordered) * fraction) - 1)
    return ordered[index]


def main() -> int:
    if len(sys.argv) != 2:
        print(f"Usage: {sys.argv[0]} <tpc-invocations.tsv>", file=sys.stderr)
        return 2

    metrics_path = pathlib.Path(sys.argv[1])
    if not metrics_path.is_file():
        print(f"No compiler invocation metrics found at {metrics_path}")
        return 0

    records: list[dict[str, str]] = []
    malformed = 0
    with metrics_path.open(newline="", encoding="utf-8", errors="replace") as stream:
        for row in csv.reader(stream, delimiter="\t"):
            if len(row) < len(COLUMNS):
                malformed += 1
                continue
            if len(row) > len(COLUMNS):
                row = row[: len(COLUMNS) - 1] + ["\t".join(row[len(COLUMNS) - 1 :])]
            records.append(dict(zip(COLUMNS, row)))

    if not records:
        print(f"No complete compiler invocation records found (malformed={malformed})")
        return 0

    wall = [float(record["wall_seconds"]) for record in records]
    user = [float(record["user_seconds"]) for record in records]
    system = [float(record["system_seconds"]) for record in records]
    rss = [int(record["max_rss_kb"]) for record in records]
    major_faults = [int(record["major_faults"]) for record in records]
    minor_faults = [int(record["minor_faults"]) for record in records]
    fs_inputs = [int(record["filesystem_inputs"]) for record in records]
    fs_outputs = [int(record["filesystem_outputs"]) for record in records]
    involuntary_switches = [int(record["involuntary_context_switches"]) for record in records]
    voluntary_switches = [int(record["voluntary_context_switches"]) for record in records]
    failed = sum(record["exit"] != "0" for record in records)

    print("Per-invocation tpc metrics")
    print(f"  invocations: {len(records)}")
    print(f"  failed: {failed}")
    print(f"  malformed records: {malformed}")
    print(f"  cumulative wall time: {sum(wall):.2f} s")
    print(f"  cumulative user time: {sum(user):.2f} s")
    print(f"  cumulative system time: {sum(system):.2f} s")
    print(f"  wall mean: {statistics.fmean(wall):.3f} s")
    print(f"  wall p50: {percentile(wall, 0.50):.3f} s")
    print(f"  wall p90: {percentile(wall, 0.90):.3f} s")
    print(f"  wall p95: {percentile(wall, 0.95):.3f} s")
    print(f"  wall p99: {percentile(wall, 0.99):.3f} s")
    print(f"  wall max: {max(wall):.3f} s")
    print(f"  max RSS: {max(rss)} KiB")
    print(f"  major page faults: {sum(major_faults)}")
    print(f"  minor page faults: {sum(minor_faults)}")
    print(f"  filesystem inputs: {sum(fs_inputs)}")
    print(f"  filesystem outputs: {sum(fs_outputs)}")
    print(f"  involuntary context switches: {sum(involuntary_switches)}")
    print(f"  voluntary context switches: {sum(voluntary_switches)}")

    print("  slowest invocations:")
    for record in sorted(records, key=lambda item: float(item["wall_seconds"]), reverse=True)[:10]:
        print(
            "    "
            f"{float(record['wall_seconds']):.3f} s, "
            f"RSS {int(record['max_rss_kb'])} KiB, "
            f"major/minor faults {record['major_faults']}/{record['minor_faults']}: "
            f"{record['command']}"
        )

    return 0


if __name__ == "__main__":
    raise SystemExit(main())

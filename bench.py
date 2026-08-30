import asyncio
import time
import sys
import http.client
import urllib.parse
from concurrent.futures import ThreadPoolExecutor

def run_sync_worker(host, port, path, deadline, stats):
    conn = http.client.HTTPConnection(host, port, timeout=10)
    latencies = []
    success = 0
    fail = 0
    while time.perf_counter() < deadline:
        t0 = time.perf_counter()
        try:
            conn.request("GET", path)
            res = conn.getresponse()
            res.read()
            lat = (time.perf_counter() - t0) * 1000.0
            latencies.append(lat)
            if res.status == 200:
                success += 1
            else:
                fail += 1
        except Exception:
            fail += 1
            try:
                conn.close()
                conn = http.client.HTTPConnection(host, port, timeout=10)
            except Exception:
                pass
    try:
        conn.close()
    except Exception:
        pass
    stats.append((success, fail, latencies))

def run_bench(url, duration=10, concurrency=50):
    parsed = urllib.parse.urlparse(url)
    host = parsed.hostname or "127.0.0.1"
    port = parsed.port or 80
    path = parsed.path or "/"
    if parsed.query:
        path += "?" + parsed.query

    print(f"Benchmarking {url} | Concurrency: {concurrency} | Duration: {duration}s ...")
    
    # Warmup
    try:
        w_conn = http.client.HTTPConnection(host, port, timeout=2)
        w_conn.request("GET", path)
        w_res = w_conn.getresponse()
        w_res.read()
        w_conn.close()
    except Exception:
        pass

    deadline = time.perf_counter() + duration
    start_time = time.perf_counter()
    stats = []
    
    with ThreadPoolExecutor(max_workers=concurrency) as executor:
        futures = [executor.submit(run_sync_worker, host, port, path, deadline, stats) for _ in range(concurrency)]
        for f in futures:
            f.result()
            
    total_time = time.perf_counter() - start_time
    total_success = sum(s[0] for s in stats)
    total_fail = sum(s[1] for s in stats)
    all_latencies = []
    for s in stats:
        all_latencies.extend(s[2])

    all_latencies.sort()
    total_requests = total_success + total_fail
    qps = total_requests / total_time if total_time > 0 else 0
    avg_lat = (sum(all_latencies) / len(all_latencies)) if all_latencies else 0
    p50 = all_latencies[int(len(all_latencies) * 0.50)] if all_latencies else 0
    p90 = all_latencies[int(len(all_latencies) * 0.90)] if all_latencies else 0
    p99 = all_latencies[int(len(all_latencies) * 0.99)] if all_latencies else 0

    print("\n================== Benchmark Result ==================")
    print(f"Total Requests:     {total_requests}")
    print(f"Successful:         {total_success}")
    print(f"Failed:             {total_fail}")
    print(f"Duration:           {total_time:.2f} s")
    print(f"Throughput (QPS):   {qps:.2f} req/sec")
    print(f"Avg Latency:        {avg_lat:.2f} ms")
    print(f"50% Latency (P50):  {p50:.2f} ms")
    print(f"90% Latency (P90):  {p90:.2f} ms")
    print(f"99% Latency (P99):  {p99:.2f} ms")
    print("======================================================\n")

if __name__ == '__main__':
    url = sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8787/index/json'
    concurrency = int(sys.argv[2]) if len(sys.argv) > 2 else 50
    duration = int(sys.argv[3]) if len(sys.argv) > 3 else 10
    run_bench(url, duration, concurrency)

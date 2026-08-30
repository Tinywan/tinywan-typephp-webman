<?php
use native_types;

function gen_random (int $n) {
    global $LAST;
    return( ($n * ($LAST = ($LAST * IA + IC) % IM)) / IM );
}

function heapsort_r(int $n, &$ra) {
    $l = ($n >> 1) + 1;
    $ir = $n;

    while (1) {
    if ($l > 1) {
        $rra = $ra[--$l];
    } else {
        $rra = $ra[$ir];
        $ra[$ir] = $ra[1];
        if (--$ir == 1) {
        $ra[1] = $rra;
        return;
        }
    }
    $i = $l;
    $j = $l << 1;
    while ($j <= $ir) {
        if (($j < $ir) && ($ra[$j] < $ra[$j+1])) {
        $j++;
        }
        if ($rra < $ra[$j]) {
        $ra[$i] = $ra[$j];
        $j += ($i = $j);
        } else {
        $j = $ir + 1;
        }
    }
    $ra[$i] = $rra;
    }
}

function heapsort(int $N) {
  global $LAST;

  define("IM", 139968);
  define("IA", 3877);
  define("IC", 29573);

  $LAST = 42;
  for ($i=1; $i<=$N; $i++) {
    $ary[$i] = gen_random(1);
  }
  heapsort_r($N, $ary);
  printf("%.10f\n", $ary[$N]);
}

function gethrtime(): float
{
    $hrtime = hrtime();
    return (($hrtime[0] * 1000000000.0 + $hrtime[1]) / 1000000000.0);
}

function start_test()
{
    ob_start();
    return gethrtime();
}

function end_test($start, $name)
{
    global $total;
    $end = gethrtime();
    ob_end_clean();
    $total += ($end - $start);
    $num = number_format($end - $start, 3);
    $pad = str_repeat(" ", 24 - strlen($name) - strlen($num));

    echo $name . $pad . $num . "\n";
    ob_start();
    return gethrtime();
}

function total()
{
    global $total;
    $pad = str_repeat("-", 24);
    echo $pad . "\n";
    $num = number_format($total, 3);
    $pad = str_repeat(" ", 24 - strlen("Total") - strlen($num));
    echo "Total" . $pad . $num . "\n";
}

function main()
{
    if (function_exists("date_default_timezone_set")) {
        date_default_timezone_set("UTC");
    }

    $t0 = $t = start_test();
    heapsort(20000);
    $t = end_test($t, "heapsort(20000)");
    total();
}

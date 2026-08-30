<?php
use native_types;

function simple(): void
{
    $a = 0;
    $total_count = 10000000;
    for ($i = 0; $i < $total_count; $i++)
        $a++;

    assert($a == $total_count);

    $thisisanotherlongname = 0;
    for ($thisisalongname = 0; $thisisalongname < $total_count; $thisisalongname++)
        $thisisanotherlongname++;

    assert($thisisanotherlongname == $total_count);
}

/****/

function simplecall(): void
{
    $total = 0;
    for ($i = 0; $i < 1000000; $i++)
        $total += strlen("hallo" . $i);
    print "$total\n";
}

/****/

function hallo(string $a): void {
}

function simpleucall(): void {
  for ($i = 0; $i < 1000000; $i++)
    hallo("hallo");
}

/****/

function hallo2(string $a): void {
}


function simpleudcall(): void {
  for ($i = 0; $i < 1000000; $i++)
    hallo2("hallo");
}

/****/

function mandel(): void {
  $w1=50;
  $h1=150;
  $recen=-.45;
  $imcen=0.0;
  $r=0.7;
  $s=0;  $rec=0;  $imc=0;  $re=0;  $im=0;  $re2=0;  $im2=0;
  $x=0;  $y=0;  $w2=0;  $h2=0;  $color=0;
  $s=2*$r/$w1;
  $w2=40;
  $h2=12;
  for ($y=0 ; $y<=$w1; $y=$y+1) {
    $imc=$s*($y-$h2)+$imcen;
    for ($x=0 ; $x<=$h1; $x=$x+1) {
      $rec=$s*($x-$w2)+$recen;
      $re=$rec;
      $im=$imc;
      $color=1000;
      $re2=$re*$re;
      $im2=$im*$im;
      while( ((($re2+$im2)<1000000) && $color>0)) {
        $im=$re*$im*2+$imc;
        $re=$re2-$im2+$rec;
        $re2=$re*$re;
        $im2=$im*$im;
        $color=$color-1;
      }
      if ( $color==0 ) {
        print "_";
      } else {
        print "#";
      }
    }
    print "<br>";
    flush();
  }
}

/****/

function mandel2(): void {
  $b = " .:,;!/>)|&IH%*#";
  //float r, i, z, Z, t, c, C;
  for ($y=30; printf("\n"), $C = $y*0.1 - 1.5, $y--;){
    for ($x=0; $c = $x*0.04 - 2, $z=0, $Z=0, $x++ < 75;){
      for ($r=$c, $i=$C, $k=0; $t = $z*$z - $Z*$Z + $r, $Z = 2*$z*$Z + $i, $z=$t, $k<5000; $k++)
        if ($z*$z + $Z*$Z > 500000) break;
      echo $b[$k%16];
    }
  }
}

/****/

function Ack(int $m, int $n): int {
  if($m == 0) return $n+1;
  if($n == 0) return Ack($m-1, 1);
  return Ack($m - 1, Ack($m, ($n - 1)));
}

function ackermann(int $n): void {
  $r = Ack(3, $n);
  print "Ack(3,$n): $r\n";
}

/****/

function ary(int $n): void {
  for ($i=0; $i<$n; $i++) {
    $X[$i] = $i;
  }
  for ($i=$n-1; $i>=0; $i--) {
    $Y[$i] = $X[$i];
  }
  $last = $n-1;
  print "$Y[$last]\n";
}

/****/

function ary2(int $n): void {
  for ($i=0; $i<$n;) {
    $X[$i] = $i; ++$i;
    $X[$i] = $i; ++$i;
    $X[$i] = $i; ++$i;
    $X[$i] = $i; ++$i;
    $X[$i] = $i; ++$i;

    $X[$i] = $i; ++$i;
    $X[$i] = $i; ++$i;
    $X[$i] = $i; ++$i;
    $X[$i] = $i; ++$i;
    $X[$i] = $i; ++$i;
  }
  for ($i=$n-1; $i>=0;) {
    $Y[$i] = $X[$i]; --$i;
    $Y[$i] = $X[$i]; --$i;
    $Y[$i] = $X[$i]; --$i;
    $Y[$i] = $X[$i]; --$i;
    $Y[$i] = $X[$i]; --$i;

    $Y[$i] = $X[$i]; --$i;
    $Y[$i] = $X[$i]; --$i;
    $Y[$i] = $X[$i]; --$i;
    $Y[$i] = $X[$i]; --$i;
    $Y[$i] = $X[$i]; --$i;
  }
  $last = $n-1;
  print "$Y[$last]\n";
}

/****/

function ary3(int $n): void {
  for ($i=0; $i<$n; $i++) {
    $X[$i] = $i + 1;
    $Y[$i] = 0;
  }
  for ($k=0; $k<1000; $k++) {
    for ($i=$n-1; $i>=0; $i--) {
      $Y[$i] += $X[$i];
    }
  }
  $last = $n-1;
  print "$Y[0] $Y[$last]\n";
}

/****/

function fibo_r(int $n): int {
    return(($n < 2) ? 1 : fibo_r($n - 2) + fibo_r($n - 1));
}

function fibo(int $n): void {
  $r = fibo_r($n);
  print "$r\n";
}

/****/

function hash1(int $n): void {
  for ($i = 1; $i <= $n; $i++) {
    $X[dechex($i)] = $i;
  }
  $c = 0;
  for ($i = $n; $i > 0; $i--) {
    if ($X[dechex($i)]) { $c++; }
  }
  print "$c\n";
}

/****/

function hash2(int $n): void {
  for ($i = 0; $i < $n; $i++) {
    $hash1["foo_$i"] = $i;
    $hash2["foo_$i"] = 0;
  }
  for ($i = $n; $i > 0; $i--) {
    foreach($hash1 as $key => $value) $hash2[$key] += $value;
  }
  $first = "foo_0";
  $last  = "foo_".($n-1);
  print "$hash1[$first] $hash1[$last] $hash2[$first] $hash2[$last]\n";
}

/****/

function gen_random(int $n): float {
    global $LAST;
    return( ($n * ($LAST = ($LAST * IA + IC) % IM)) / IM );
}

function heapsort_r(int $n, array &$ra): void {
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

function heapsort(int $N): void {
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

/****/

function mkmatrix(int $rows, int $cols): array {
    $count = 1;
    $mx = array();
    for ($i=0; $i<$rows; $i++) {
        for ($j=0; $j<$cols; $j++) {
            $mx[$i][$j] = $count++;
        }
    }
    return ($mx);
}

function mmult(int $rows, int $cols, array $m1, array $m2): array {
    $m3 = array();
    for ($i=0; $i<$rows; $i++) {
    for ($j=0; $j<$cols; $j++) {
        $x = 0;
        for ($k=0; $k<$cols; $k++) {
        $x += $m1[$i][$k] * $m2[$k][$j];
        }
        $m3[$i][$j] = $x;
    }
    }
    return($m3);
}

function matrix(int $n): void {
  $SIZE = 30;
  $m1 = mkmatrix($SIZE, $SIZE);
  $m2 = mkmatrix($SIZE, $SIZE);
  while ($n--) {
    $mm = mmult($SIZE, $SIZE, $m1, $m2);
  }
  print "{$mm[0][0]} {$mm[2][3]} {$mm[3][2]} {$mm[4][4]}\n";
}

/****/

function nestedloop(int $n): void {
  $x = 0;
  for ($a=0; $a<$n; $a++)
    for ($b=0; $b<$n; $b++)
      for ($c=0; $c<$n; $c++)
        for ($d=0; $d<$n; $d++)
          for ($e=0; $e<$n; $e++)
            for ($f=0; $f<$n; $f++)
             $x++;
  print "$x\n";
}

/****/

function sieve(int $n): void {
  $count = 0;
  while ($n-- > 0) {
    $count = 0;
    $flags = range (0,8192);
    for ($i=2; $i<8193; $i++) {
      if ($flags[$i] > 0) {
        for ($k=$i+$i; $k <= 8192; $k+=$i) {
          $flags[$k] = 0;
        }
        $count++;
      }
    }
  }
  print "Count: $count\n";
}

/****/

function strcat(int $n): void {
  $str = "";
  while ($n-- > 0) {
    $str .= "hello\n";
  }
  $len = strlen($str);
  print "$len\n";
}

/*****/

function gethrtime(): float
{
    $hrtime = hrtime();
    return (($hrtime[0] * 1000000000.0 + $hrtime[1]) / 1000000000.0);
}

function start_test(): float
{
    ob_start();
    return gethrtime();
}

function end_test(float $start, string $name): float
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

function total(): void
{
    global $total;
    $pad = str_repeat("-", 24);
    echo $pad . "\n";
    $num = number_format($total, 3);
    $pad = str_repeat(" ", 24 - strlen("Total") - strlen($num));
    echo "Total" . $pad . $num . "\n";
}

function main(): void
{
    if (function_exists("date_default_timezone_set")) {
        date_default_timezone_set("UTC");
    }

    $t0 = $t = start_test();
    simple();
    $t = end_test($t, "simple");
    simplecall();
    $t = end_test($t, "simplecall");
    simpleucall();
    $t = end_test($t, "simpleucall");
    simpleudcall();
    $t = end_test($t, "simpleudcall");
    mandel();
    $t = end_test($t, "mandel");
    mandel2();
    $t = end_test($t, "mandel2");
    ackermann(7);
    $t = end_test($t, "ackermann(7)");
    ary(50000);
    $t = end_test($t, "ary(50000)");
    ary2(50000);
    $t = end_test($t, "ary2(50000)");
    ary3(2000);
    $t = end_test($t, "ary3(2000)");
    fibo(30);
    $t = end_test($t, "fibo(30)");
    hash1(50000);
    $t = end_test($t, "hash1(50000)");
    hash2(500);
    $t = end_test($t, "hash2(500)");
    heapsort(20000);
    $t = end_test($t, "heapsort(20000)");
    matrix(20);
    $t = end_test($t, "matrix(20)");
    nestedloop(12);
    $t = end_test($t, "nestedloop(12)");
    sieve(30);
    $t = end_test($t, "sieve(30)");
    strcat(200000);
    $t = end_test($t, "strcat(200000)");
    total();
}

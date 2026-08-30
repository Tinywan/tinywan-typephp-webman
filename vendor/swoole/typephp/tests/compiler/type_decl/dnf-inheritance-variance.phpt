--TEST--
DNF method signatures support parameter contravariance and return covariance
--FILE--
<?php

interface DnfVarianceLeft {}
interface DnfVarianceRight {}

final class DnfVarianceBoth implements DnfVarianceLeft, DnfVarianceRight {}
final class DnfVarianceFallback {}
final class DnfVarianceOnlyLeft implements DnfVarianceLeft {}

interface DnfVarianceContract
{
    public function adapt(
        (DnfVarianceLeft&DnfVarianceRight)|DnfVarianceFallback $value,
    ): DnfVarianceLeft|DnfVarianceFallback;
}

final class DnfVarianceImplementation implements DnfVarianceContract
{
    // A DNF parameter may be widened in an implementation.
    public function adapt(
        DnfVarianceLeft|DnfVarianceFallback $value,
    ): (DnfVarianceLeft&DnfVarianceRight)|DnfVarianceFallback {
        // A union return may be narrowed to a DNF type.
        if ($value instanceof DnfVarianceFallback) {
            return $value;
        }
        return new DnfVarianceBoth();
    }
}

function invoke_dnf_variance(
    DnfVarianceContract $implementation,
    (DnfVarianceLeft&DnfVarianceRight)|DnfVarianceFallback $value,
): DnfVarianceLeft|DnfVarianceFallback {
    return $implementation->adapt($value);
}

function main(): void
{
    $implementation = new DnfVarianceImplementation();

    $both = invoke_dnf_variance($implementation, new DnfVarianceBoth());
    $fallback = invoke_dnf_variance($implementation, new DnfVarianceFallback());

    var_dump($both instanceof DnfVarianceBoth);
    var_dump($fallback instanceof DnfVarianceFallback);

    // Exercise the wider implementation parameter through the concrete type.
    var_dump($implementation->adapt(new DnfVarianceOnlyLeft()) instanceof DnfVarianceBoth);
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)

<?php

interface CovarianceLeft
{
}

interface CovarianceRight
{
}

class CovarianceBoth implements CovarianceLeft, CovarianceRight
{
}

interface IntersectionNarrowingContract
{
    public function intersection(): CovarianceLeft;
}

class IntersectionNarrowingImpl implements IntersectionNarrowingContract
{
    public function intersection(): CovarianceLeft&CovarianceRight
    {
        return new CovarianceBoth();
    }
}

interface IntersectionContract
{
    public function concrete(): CovarianceLeft&CovarianceRight;
}

class IntersectionImpl implements IntersectionContract
{
    public function concrete(): CovarianceBoth
    {
        return new CovarianceBoth();
    }
}

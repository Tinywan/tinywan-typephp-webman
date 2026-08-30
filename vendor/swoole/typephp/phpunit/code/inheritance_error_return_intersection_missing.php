<?php

interface IntersectionLeft
{
}

interface IntersectionRight
{
}

interface IntersectionReturnParent
{
    public function value(): IntersectionLeft&IntersectionRight;
}

class IntersectionReturnChild implements IntersectionReturnParent
{
    public function value(): IntersectionLeft
    {
        return new class implements IntersectionLeft {
        };
    }
}

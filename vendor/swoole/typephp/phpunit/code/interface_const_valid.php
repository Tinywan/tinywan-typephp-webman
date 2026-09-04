<?php
interface Base
{
    const X = 1;
}

// Diamond: the same original declaration reached through two paths.
interface L extends Base {}
interface R extends Base {}
class Diamond implements L, R {}

interface Typed
{
    const int|string N = 1;
    const ?int M = null;
}

// Covariant narrowing of a typed interface constant.
class Narrowed implements Typed
{
    const int N = 2;
    const int M = 5;
}

interface Untyped
{
    const V = 1;
}

// An untyped interface constant may be redefined with any value and type.
class Redefined implements Untyped
{
    const V = 'other';
}

interface AConst
{
    const W = 1;
}

interface BConst
{
    const W = 2;
}

// The class's own declaration resolves the two-interface ambiguity.
class Resolves implements AConst, BConst
{
    const W = 3;
}

// Enum cases live in a separate table and never conflict with constants.
enum CaseName implements Untyped
{
    case V;
}

function main() {}

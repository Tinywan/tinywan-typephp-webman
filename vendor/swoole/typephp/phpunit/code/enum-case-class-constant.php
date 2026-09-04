<?php
enum CodegenEnum: int
{
    case B = 4;
    case A = 1 + 1;
}

enum CodegenTyped
{
    case A;
}

class CodegenHolder
{
    public const CB = CodegenEnum::B;
    public const PICKED = true ? CodegenEnum::A : CodegenEnum::B;
    public const CodegenTyped CASE_VALUE = CodegenTyped::A;
    public const MODE = RoundingMode::HalfEven;
}

function main(): void
{
    var_dump(CodegenHolder::CB === CodegenEnum::B);
}

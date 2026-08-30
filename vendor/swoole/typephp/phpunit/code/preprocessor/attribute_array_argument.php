<?php

#[Attribute(Attribute::TARGET_CLASS)]
final class PreprocessorAttributeArrayArgument
{
    public function __construct(public array $methods = [], public array $options = [])
    {
    }
}

final class PreprocessorAttributeArrayValues
{
    public const METHODS = ['GET', 'POST'];
}

#[PreprocessorAttributeArrayArgument(
    methods: PreprocessorAttributeArrayValues::METHODS,
    options: [
        'enabled' => true,
        'limit' => 10,
        'ratio' => 1.5,
        'nullable' => null,
        'nested' => ['first', 7 => 'last'],
    ],
)]
class PreprocessorAttributeArrayArgumentController
{
}

#[PreprocessorAttributeArrayArgument(
    methods: self::METHODS,
    options: self::OPTIONS,
)]
class PreprocessorAttributePrivateConstantController
{
    private const METHODS = ['PUT'];
    private const OPTIONS = ['private' => true];
}

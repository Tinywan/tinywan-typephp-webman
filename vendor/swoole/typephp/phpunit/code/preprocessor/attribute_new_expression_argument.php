<?php

#[Attribute(Attribute::TARGET_METHOD)]
class PreprocessorAttributeSubscribedService
{
    public function __construct(
        public string $key,
        public ?PreprocessorAttributeRequired $attribute = null,
    ) {
    }
}

class PreprocessorAttributeRequired
{
    public function __construct(public bool $enabled = true)
    {
    }
}

class PreprocessorAttributeSubscriber
{
    #[PreprocessorAttributeSubscribedService(key: 'logger', attribute: new PreprocessorAttributeRequired(false))]
    public function logger(): string
    {
        return 'logger';
    }
}

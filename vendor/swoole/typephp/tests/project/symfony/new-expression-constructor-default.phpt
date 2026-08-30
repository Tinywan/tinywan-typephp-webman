--TEST--
Symfony AssetMapper pattern: new expression as constructor default
--FILE--
<?php

class Compressor
{
    public function compress(string $value): string
    {
        return 'gz:'.$value;
    }
}

class GzipAsset
{
    public function __construct(
        private readonly Compressor $compressor = new Compressor(),
    ) {
    }

    public function encode(string $value): string
    {
        return $this->compressor->compress($value);
    }
}

function main(): void
{
    var_dump((new GzipAsset())->encode('asset'));
}
?>
--EXPECT--
string(8) "gz:asset"

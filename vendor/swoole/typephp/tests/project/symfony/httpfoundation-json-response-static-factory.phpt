--TEST--
Symfony HttpFoundation pattern: null default new ArrayObject and new static JSON factory
--FILE--
<?php

class JsonResponseLike
{
    public mixed $data;
    public bool $json;

    public function __construct(mixed $data = null, bool $json = false)
    {
        if ($json && !is_string($data) && !is_numeric($data) && !$data instanceof Stringable) {
            throw new TypeError(sprintf('bad data "%s"', get_debug_type($data)));
        }

        $data ??= new ArrayObject();
        $this->data = $data;
        $this->json = $json;
    }

    public static function fromJsonString(string $data): static
    {
        return new static($data, true);
    }
}

class CustomJsonResponseLike extends JsonResponseLike
{
}

function main(): void
{
    $response = new JsonResponseLike();
    var_dump($response->data instanceof ArrayObject);
    var_dump($response->json);

    $custom = CustomJsonResponseLike::fromJsonString('{"ok":true}');
    var_dump($custom::class);
    var_dump($custom->data);
    var_dump($custom->json);
}
?>
--EXPECT--
bool(true)
bool(false)
string(22) "CustomJsonResponseLike"
string(11) "{"ok":true}"
bool(true)

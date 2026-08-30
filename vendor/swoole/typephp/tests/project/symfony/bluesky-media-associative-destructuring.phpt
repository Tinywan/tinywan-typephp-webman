--TEST--
Symfony Notifier Bluesky pattern: foreach associative array destructuring
--FILE--
<?php

final class MediaFile
{
    public function __construct(private string $name, private string $contentType)
    {
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

function describe_media(array $media): array
{
    $uploaded = [];

    foreach ($media as ['file' => $file, 'description' => $description]) {
        $uploaded[] = [
            'alt' => $description,
            'name' => $file->getName(),
            'mimeType' => $file->getContentType(),
        ];
    }

    return $uploaded;
}

function main(): void
{
    var_dump(describe_media([
        ['file' => new MediaFile('first.png', 'image/png'), 'description' => 'First'],
        ['description' => 'Second', 'file' => new MediaFile('second.jpg', 'image/jpeg')],
    ]));
}
?>
--EXPECT--
array(2) {
  [0]=>
  array(3) {
    ["alt"]=>
    string(5) "First"
    ["name"]=>
    string(9) "first.png"
    ["mimeType"]=>
    string(9) "image/png"
  }
  [1]=>
  array(3) {
    ["alt"]=>
    string(6) "Second"
    ["name"]=>
    string(10) "second.jpg"
    ["mimeType"]=>
    string(10) "image/jpeg"
  }
}

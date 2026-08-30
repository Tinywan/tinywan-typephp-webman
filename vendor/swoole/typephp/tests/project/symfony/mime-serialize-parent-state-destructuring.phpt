--TEST--
Symfony Mime style __serialize/__unserialize with parent state and array destructuring
--XFAIL--
Known AOT bug: parent::__unserialize() does not restore parent private state in this pattern.
--FILE--
<?php
class SymfonyMimeParentMessage
{
    private array $headers = [];

    public function header(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function __serialize(): array
    {
        return [$this->headers];
    }

    public function __unserialize(array $data): void
    {
        [$this->headers] = $data;
    }
}

class SymfonyMimeEmailCase extends SymfonyMimeParentMessage
{
    private ?string $text = null;
    private ?string $textCharset = null;
    private ?string $html = null;
    private ?string $htmlCharset = null;
    private array $attachments = [];

    public function configure(): void
    {
        $this->text = 'text body';
        $this->textCharset = 'utf-8';
        $this->html = '<b>html</b>';
        $this->htmlCharset = 'utf-8';
        $this->attachments = ['a.txt', 'b.txt'];
        $this->header('Subject', 'demo');
    }

    public function __serialize(): array
    {
        return [$this->text, $this->textCharset, $this->html, $this->htmlCharset, $this->attachments, parent::__serialize()];
    }

    public function __unserialize(array $data): void
    {
        [$this->text, $this->textCharset, $this->html, $this->htmlCharset, $this->attachments, $parentData] = $data;

        parent::__unserialize($parentData);
    }

    public function summary(): string
    {
        return $this->text . '|' . $this->html . '|' . $this->getHeader('Subject') . '|' . count($this->attachments);
    }
}

function main(): void
{
    $email = new SymfonyMimeEmailCase();
    $email->configure();

    $restored = unserialize(serialize($email));
    var_dump($restored->summary());
}
?>
--EXPECT--
string(28) "text body|<b>html</b>|demo|2"

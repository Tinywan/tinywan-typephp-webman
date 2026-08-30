--TEST--
Symfony Notifier pattern: nullsafe options array with coalesce assign
--FILE--
<?php

final class MessageOptions
{
    public function __construct(private array $options)
    {
    }

    public function toArray(): array
    {
        return $this->options;
    }
}

final class ChatMessage
{
    public function __construct(
        private string $subject,
        private ?MessageOptions $options = null,
        private ?string $recipientId = null,
    ) {
    }

    public function getOptions(): ?MessageOptions
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return $this->recipientId;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }
}

function normalize_chat_payload(ChatMessage $message, string $defaultChannel): array
{
    $options = $message->getOptions()?->toArray() ?? [];
    $options['channel'] ??= $message->getRecipientId() ?: $defaultChannel;
    $options['text'] = $message->getSubject();

    return array_filter($options);
}

function main(): void
{
    var_dump(normalize_chat_payload(new ChatMessage('deploy', null, null), '#ops'));
    var_dump(normalize_chat_payload(new ChatMessage('alert', new MessageOptions(['channel' => '#custom', 'emoji' => ''])), '#ops'));
}
?>
--EXPECT--
array(2) {
  ["channel"]=>
  string(4) "#ops"
  ["text"]=>
  string(6) "deploy"
}
array(2) {
  ["channel"]=>
  string(7) "#custom"
  ["text"]=>
  string(5) "alert"
}

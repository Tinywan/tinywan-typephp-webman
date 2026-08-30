--TEST--
Symfony AssetMapper style match statement with nullsafe side effect and throw arm
--FILE--
<?php
interface SymfonyMissingImportLogger
{
    public function warning(string $message): void;
}

class SymfonyMissingImportMemoryLogger implements SymfonyMissingImportLogger
{
    public array $messages = [];

    public function warning(string $message): void
    {
        $this->messages[] = $message;
    }
}

class SymfonyMissingImportHandler
{
    public const IGNORE = 'ignore';
    public const WARN = 'warn';
    public const STRICT = 'strict';

    public function __construct(
        private string $mode,
        private ?SymfonyMissingImportLogger $logger = null,
    ) {
    }

    public function handle(string $message): void
    {
        match ($this->mode) {
            self::IGNORE => null,
            self::WARN => $this->logger?->warning($message),
            self::STRICT => throw new RuntimeException($message),
        };
    }
}

function main(): void
{
    $logger = new SymfonyMissingImportMemoryLogger();
    (new SymfonyMissingImportHandler(SymfonyMissingImportHandler::IGNORE))->handle('ignored');
    (new SymfonyMissingImportHandler(SymfonyMissingImportHandler::WARN, $logger))->handle('missing.js');

    var_dump($logger->messages);

    try {
        (new SymfonyMissingImportHandler(SymfonyMissingImportHandler::STRICT))->handle('strict.js');
    } catch (RuntimeException $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
array(1) {
  [0]=>
  string(10) "missing.js"
}
strict.js

--TEST--
Symfony pattern: object runtime class in arrow function array_map
--FILE--
<?php

class SymfonyLikeTraceableAuthenticator
{
    public function __construct(private object $authenticator)
    {
    }

    public function getAuthenticator(): object
    {
        return $this->authenticator;
    }
}

class SymfonyLikePasswordAuthenticator
{
}

class SymfonyLikeTokenAuthenticator
{
}

class SymfonyLikeDebugFirewallCommand
{
    public function listAuthenticatorClasses(array $authenticators): array
    {
        return array_map(
            static fn ($authenticator) => [($authenticator instanceof SymfonyLikeTraceableAuthenticator ? $authenticator->getAuthenticator() : $authenticator)::class],
            $authenticators
        );
    }
}

function main(): void
{
    $command = new SymfonyLikeDebugFirewallCommand();

    var_dump($command->listAuthenticatorClasses([
        new SymfonyLikeTraceableAuthenticator(new SymfonyLikePasswordAuthenticator()),
        new SymfonyLikeTokenAuthenticator(),
    ]));
}
?>
--EXPECT--
array(2) {
  [0]=>
  array(1) {
    [0]=>
    string(32) "SymfonyLikePasswordAuthenticator"
  }
  [1]=>
  array(1) {
    [0]=>
    string(29) "SymfonyLikeTokenAuthenticator"
  }
}

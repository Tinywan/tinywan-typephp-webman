--TEST--
__NAMESPACE__ resolves in namespace, class, and global scopes
--FILE--
<?php
namespace Project\Feature {
    #[\Attribute(\Attribute::TARGET_CLASS)]
    class NamespaceName
    {
        public function __construct(public string $name)
        {
        }
    }

    const CURRENT_NAMESPACE = __NAMESPACE__;

    function namespaceName(): string
    {
        return __NAMESPACE__;
    }

    #[NamespaceName(__NAMESPACE__)]
    class Scope
    {
        public function namespaceName(): string
        {
            return __NAMESPACE__;
        }
    }
}

namespace {
    function main(): void
    {
        var_dump(__NAMESPACE__);
        var_dump(Project\Feature\namespaceName());
        var_dump((new Project\Feature\Scope())->namespaceName());
        var_dump(Project\Feature\CURRENT_NAMESPACE);
        $class = new ReflectionClass(Project\Feature\Scope::class);
        var_dump($class->getAttributes(Project\Feature\NamespaceName::class)[0]->getArguments()[0]);
    }
}
?>
--EXPECT--
string(0) ""
string(15) "Project\Feature"
string(15) "Project\Feature"
string(15) "Project\Feature"
string(15) "Project\Feature"

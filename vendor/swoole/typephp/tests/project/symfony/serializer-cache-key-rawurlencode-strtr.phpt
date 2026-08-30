--TEST--
Symfony Serializer pattern: cache key with rawurlencode and strtr
--FILE--
<?php

function metadata_cache_key(string $class): string
{
    return rawurlencode(strtr($class, '\\', '_'));
}

function main(): void
{
    var_dump(metadata_cache_key('Symfony\\Component\\Serializer\\Mapping\\ClassMetadata'));
    var_dump(metadata_cache_key('App\\Model\\User Profile'));
}
?>
--EXPECT--
string(50) "Symfony_Component_Serializer_Mapping_ClassMetadata"
string(24) "App_Model_User%20Profile"

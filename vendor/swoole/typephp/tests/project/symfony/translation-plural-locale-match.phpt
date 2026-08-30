--TEST--
Symfony Translation pattern: complex conditional expression as match subject
--FILE--
<?php

function plural_locale_bucket(string $locale): int
{
    return match ('pt_BR' !== $locale && 'en_US_POSIX' !== $locale && strlen($locale) > 3 ? substr($locale, 0, strrpos($locale, '_')) : $locale) {
        'en',
        'fr' => 1,
        'pt_BR' => 2,
        'en_US_POSIX' => 3,
        default => 0,
    };
}

function main(): void
{
    var_dump(plural_locale_bucket('en_GB'));
    var_dump(plural_locale_bucket('fr_CA'));
    var_dump(plural_locale_bucket('pt_BR'));
    var_dump(plural_locale_bucket('en_US_POSIX'));
    var_dump(plural_locale_bucket('zh_Hant'));
}
?>
--EXPECT--
int(1)
int(1)
int(2)
int(3)
int(0)

--TEST--
Ordinary constants are case-sensitive and magic constants are case-insensitive
--FILE--
<?php
namespace ConstantCase {
    const ExactName = 'exact';

    trait MagicTrait
    {
        public function traitNameMatches(): bool
        {
            return __TrAiT__ === __TRAIT__;
        }
    }

    class MagicScope
    {
        use MagicTrait;

        public function namesMatch(): array
        {
            return [
                __ClAsS__ === __CLASS__,
                __FuNcTiOn__ === __FUNCTION__,
                __MeThOd__ === __METHOD__,
                __NaMeSpAcE__ === __NAMESPACE__,
            ];
        }
    }

    function functionNameMatches(): bool
    {
        return __FuNcTiOn__ === __FUNCTION__;
    }
}

namespace {
    function main(): void
    {
        var_dump(\ConstantCase\ExactName);
        var_dump(\constantcase\ExactName);
        try {
            var_dump(\ConstantCase\exactname);
        } catch (\Error $error) {
            echo "ordinary constant is case-sensitive\n";
        }

        var_dump(__FiLe__ === __FILE__);
        var_dump(__DiR__ === __DIR__);
        var_dump(is_int(__LiNe__));
        var_dump(__NaMeSpAcE__ === __NAMESPACE__);
        var_dump(\ConstantCase\functionNameMatches());
        var_dump((new \ConstantCase\MagicScope())->namesMatch());
        var_dump((new \ConstantCase\MagicScope())->traitNameMatches());
    }
}
?>
--EXPECT--
string(5) "exact"
string(5) "exact"
ordinary constant is case-sensitive
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
array(4) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  bool(true)
  [3]=>
  bool(true)
}
bool(true)

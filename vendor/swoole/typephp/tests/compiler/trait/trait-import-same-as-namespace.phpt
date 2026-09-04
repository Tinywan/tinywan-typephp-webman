--TEST--
Trait types retain rooted imports when an imported class has the same short name as its namespace
--FILE--
<?php
namespace App {
    use Carbon\Carbon;
    use Carbon\CarbonInterface;

    trait Checker {
        public CarbonInterface $value;

        public function accept(CarbonInterface $value): CarbonInterface {
            $this->value = $value;
            return $value;
        }

        public function acceptNullable(?CarbonInterface $value): ?CarbonInterface {
            return $value;
        }

        public function check(mixed $value): bool {
            return $value instanceof CarbonInterface;
        }

        public function make(): CarbonInterface {
            return Carbon::parse('value');
        }

        public function importedNames(): array {
            return [
                Carbon::class,
                CarbonInterface::class,
                Carbon\CarbonInterface::class,
            ];
        }
    }

    class Example {
        use Checker;
    }
}

namespace {
    function main(): void {
        eval(<<<'PHP'
namespace Carbon;
interface CarbonInterface {}
class Carbon implements CarbonInterface {
    public static function parse(string $value): CarbonInterface {
        return new self();
    }
}
PHP);

        $example = new App\Example();
        $value = new Carbon\Carbon();

        var_dump($example->accept($value) === $value);
        var_dump($example->acceptNullable($value) === $value);
        var_dump($example->acceptNullable(null));
        var_dump($example->check($value));
        var_dump($example->make() instanceof Carbon\CarbonInterface);

        $method = new ReflectionMethod(App\Example::class, 'accept');
        echo $method->getParameters()[0]->getType()->getName(), "\n";
        echo $method->getReturnType()->getName(), "\n";

        $property = new ReflectionProperty(App\Example::class, 'value');
        echo $property->getType()->getName(), "\n";

        foreach ($example->importedNames() as $name) {
            echo $name, "\n";
        }
    }
}
?>
--EXPECT--
bool(true)
bool(true)
NULL
bool(true)
bool(true)
Carbon\CarbonInterface
Carbon\CarbonInterface
Carbon\CarbonInterface
Carbon\Carbon
Carbon\CarbonInterface
Carbon\Carbon\CarbonInterface

#include <phpx.h>
#include <iostream>

using namespace php;

Int php_fn_test(Int a, Int b) {
    auto c = a + b;
    call("var_dump", {c});
    call("var_dump", {call("php_uname")});
    return c;
}

static php::Str prop_base {ZEND_STRL("base"), true};

void php_foo____construct(php::Object& this_, php::Int value) {
    std::cout << "hashCode: " << prop_base.str()->h << "\n";
    this_.setProperty(prop_base, value);
}

php::Int php_foo__bar(php::Object &this_, php::Int a, php::Int b) {
    std::cout << "hashCode: " << prop_base.str()->h << "\n";
    return this_.getProperty(prop_base).toInt() + a + b;
}

<?php
class Foo {
    private function bar() {
        return true;
    }

    public function foo(array $list) {
        foreach ($list as $o) {
            if ($o->bar()) {
                return $this;
            }
        }
        return null;
    }
}


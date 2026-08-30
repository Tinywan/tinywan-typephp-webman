<?php
trait AliasModifierTrait {
    public function hello() {}
}

class AliasModifierUser {
    use AliasModifierTrait {
        hello as private;
    }
}

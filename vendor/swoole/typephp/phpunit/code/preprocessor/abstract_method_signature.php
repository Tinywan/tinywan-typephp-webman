<?php
abstract class PreprocessorAbstractSignature {
    abstract public function load(int $id, ?string $name = null): self;
}

<?php
interface ParentA {}
interface ParentB {}
interface Demo extends ParentA, ParentB {
    public const VERSION = '1.0';
    public function run(int $id, ?string $name = null): ParentA|ParentB;
}

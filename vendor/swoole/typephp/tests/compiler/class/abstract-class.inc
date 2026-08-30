<?php
namespace App1 {
    interface Speakable {
        public function speak(): string;
    }
    abstract class Animal implements Speakable {
        protected string $name;
    }
    class Dog extends Animal {
        public function speak(): string {
            return "woof";
        }
    }
}
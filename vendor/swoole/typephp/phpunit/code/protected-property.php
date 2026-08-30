<?php
class Config {
    protected static array $settings = [];
}

class DevConfig extends Config {
}

function main() {
    // DevConfig 使用自己的 settings
    \DevConfig::$settings = ['dev' => true, 'debug' => true];
}

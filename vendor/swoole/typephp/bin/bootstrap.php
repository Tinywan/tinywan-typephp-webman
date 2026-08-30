<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

define('TYPEPHP_ROOT_PATH', dirname(__DIR__));
define('TYPEPHP_DEBUG', true);

// Composer bin proxies provide the consuming project's autoloader. A source
// checkout and a packaged compiler keep their own autoloader below TYPEPHP_ROOT_PATH.
$autoloadPath = $GLOBALS['_composer_autoload_path'] ?? TYPEPHP_ROOT_PATH . '/vendor/autoload.php';
unset($GLOBALS['_composer_autoload_path']);
require $autoloadPath;
unset($autoloadPath);

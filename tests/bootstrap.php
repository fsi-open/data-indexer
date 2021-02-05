<?php

error_reporting(E_ALL | E_STRICT);

define('TESTS_TEMP_DIR', __DIR__ . '/temp');
define('VENDOR_PATH', realpath(__DIR__ . '/../vendor'));

if (!file_exists(__DIR__.'/../vendor/autoload.php')) {
    die('Install vendors using command: composer.phar install');
}
if (!file_exists(TESTS_TEMP_DIR . '/cache')) {
    if (!mkdir(TESTS_TEMP_DIR . '/cache', 0777, true)) {
        die(sprintf('Failed to create temp cache directory for tests "%s"', TESTS_TEMP_DIR . '/cache'));
    }
}

$loader = require_once __DIR__.'/../vendor/autoload.php';

\Doctrine\Common\Annotations\AnnotationRegistry::registerFile(
    VENDOR_PATH.'/doctrine/orm/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php'
);

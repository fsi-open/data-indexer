<?php

use Doctrine\Common\Annotations\AnnotationRegistry;

define('TESTS_TEMP_DIR', __DIR__ . '/temp');
define('VENDOR_PATH', dirname(__DIR__) . '/vendor');

if (false === file_exists(__DIR__.'/../vendor/autoload.php')) {
    die('Install vendors using command: composer.phar install');
}
if (false === file_exists(TESTS_TEMP_DIR . '/cache')) {
    $cacheDirectory = TESTS_TEMP_DIR . '/cache';
    if (false === mkdir($cacheDirectory = TESTS_TEMP_DIR . '/cache', 0777, true) && false === is_dir($cacheDirectory)) {
        die(sprintf('Failed to create temp cache directory for tests "%s"', TESTS_TEMP_DIR . '/cache'));
    }
}

$loader = require_once __DIR__ . '/../vendor/autoload.php';

AnnotationRegistry::registerFile(
    VENDOR_PATH . '/doctrine/orm/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php'
);

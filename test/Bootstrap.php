<?php
use MelisCore\ServiceManagerGrabber;

error_reporting(E_ALL | E_STRICT);

$cwd = __DIR__;
chdir(dirname(__DIR__));

// Assume we use composer
$loader = require_once  '../../../vendor/autoload.php';
$loader->add("MelisComposerDeployTest\\", $cwd);
$loader->register();

ServiceManagerGrabber::setServiceConfig();
ob_start();
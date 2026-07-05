<?php

/**
 * Bootstrap the app.
 * @author wasilij.de
 * @version 1.0
 * @date 2025-06-05
 */

// Load Composer autoloader
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';


// init Config with default config files
use Platomat\Platon\Core\Config;
Config::initialize(__DIR__ . DIRECTORY_SEPARATOR);

// Set timezone to Berlin/Europe
date_default_timezone_set(Config::read('App.timezone'));
<?php

/**
 * Notifier Server app.
 * This is a sample file. Just copy it without "sample" in the filename.
 * Maybe: add it to gitignore.
 * @author wasilij.de
 * @version 1.0
 * @date 2025-06-05
 */


// bootstrap app
require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use Platomat\Platon\Core\Config;
use Platomat\Notifier\Server;

// Initialize server with required and optional configuration
Config::extendConfig('NotifierServer.default', [
  'live' => [
    'debug'       => false,
    'domain'      => 'example.com',

    'Email'       => [
      // default email from, if client does not define
      //'defaultFrom' => 'default-from@example.com',

      // define default to email here, to avoid setting it up in the client code usage
      // string or array or string comma separated
      //'defaultTo'   => 'default-to@example.com',
      'sendMethod'      => 'smtp',
      'fallbackEnabled' => false,
      'smtpConfig'      => 'live',
    ],
  ],
]);

Config::extendConfig('Smtp.default', [
  'live' => [
    'host'        => 'smpt.exapmple.com',
    'port'        => 587,
    'username'    => 'server@example.com',
    'password'    => 'my_secret',
    'encryption'  => 'tls', // 'tls' or 'ssl'
  ],
]);

$server = new Server('NotifierServer.live');
$server->handleRequest();
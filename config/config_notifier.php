<?php

/**
 * Config for notifier.
 * @author wasilij.de
 * @version 1.0
 * @date 2025-06-06
 */

$appRootDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

return [
  // global app settings
  'App' => [
    'debug'     => false,
    'timezone'  => 'Europe/Berlin',
  ],

  // this is the server config
  'NotifierServer' => [
    'default' => [
      // the domain of this server, used for API key generation
      'domain'      => 'example.com',
      // rate limit to prevent flood of emails
      'RateLimit'   => [
        // enable or disable
        'enabled'       => true,
        // for example: 3 requests per 10 seconds
        'per10seconds'  => 3,
        // where to store the requests to perform checking on
        'requestsDir'   => $appRootDir . 'tmp' . DIRECTORY_SEPARATOR . 'notifier' . DIRECTORY_SEPARATOR . 'rate-limits' . DIRECTORY_SEPARATOR,
      ],
      // email config
      'Email' => [
        // default email from, if client does not define
        'defaultFrom'     => '',
        // default email to, if client does not define
        'defaultTo'       => '',
        // 'mail' or 'smtp'
        'sendMethod'      => 'mail',
        // try alternative method if primary fails
        'fallbackEnabled' => true,
        // which config key from Smtp config array
        'smtpConfig'      => 'default',
      ],
      // json files of clients can use the notifier
      // files will be generated via CLI: cli-create-client-access.*
      'clientsDir' => $appRootDir . 'data' . DIRECTORY_SEPARATOR . 'notifier' . DIRECTORY_SEPARATOR . 'clients' . DIRECTORY_SEPARATOR,
      // reverse proxy IPs that may set X-Forwarded-For; empty = use REMOTE_ADDR only
      'trustedProxies' => [],
    ],
  ],

  // this is the client config
  'NotifierClient' => [
    'default' => [
      'serverUrl'       => 'http://example.com',
      // client API key from cli-create-client-access.*
      'apiKey'          => '',
      // optional: system name for email subject prefix
      'name'            => '',
      // Email config
      'Email' => [
        // define default from email here, to avoid setting it up in the client code usage
        'from'  => 'test-sender@example.com',
        // define default to email here, to avoid setting it up in the client code usage
        // string or array or string comma separated
        'to'    => 'test-recipient@example.com',
        // Request timeout in seconds
      ],
    ],
  ],

  // this is for common things
  'Common' => [
    // dir to store received mails from client
    'debugDir'      => $appRootDir . 'tmp' . DIRECTORY_SEPARATOR . 'debug' . DIRECTORY_SEPARATOR,
    // dir to store failed email could not be sent from client
    'failuresDir'   => $appRootDir . 'tmp' . DIRECTORY_SEPARATOR . 'failures' . DIRECTORY_SEPARATOR,
  ],

  // smpt config
  'Smtp' => [
    // default is here
    'default' => [
      'host'        => 'smtp.example.com',
      'port'        => 587,
      'username'    => 'your-email@example.com',
      'password'    => 'your-app-password',
      'encryption'  => 'tls', // 'tls' or 'ssl'
    ],
    // test could have a custom smpt config definition here or pass it directly to server class
    // live could have a custom smpt config definition here or pass it directly to server class
  ],

  // logging config
  'Logging' => [
    // defaul logger
    'default' => [
      // directory to store the log files
      'dir'       => $appRootDir . 'logs' . DIRECTORY_SEPARATOR,
      // the log file
      'filename'  => 'debug.log',
      // max size of a file before archive
      'maxSize'   => '10MB',
      // number of files to archive
      'maxFiles'  => 5
    ],
  ],

  'HttpClient' => [
    'notifierClient' => [
      'debug'           => false,
      'defaultHeaders'  => [
        'User-Agent'      => 'Platomat-Notifier-Client/1.0',
        'Accept'          => 'application/json',
        'Content-Type'    => 'application/json',
      ],
      'httpErrors'      => true, // to handle 404 and some other codes as exceptions
      'timeout'         => 30,
      'maxRetries'      => 3,
      'retryOnStatus'   => [404, 429, 500, 502, 503, 504],
      'minWaitMs'       => 0,
      'maxWaitMs'       => 0,
    ],
  ],
];
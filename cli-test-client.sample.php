<?php

/**
 * Test the client.
 * @author wasilij.de
 * @version 1.0
 * @date 2025-06-05
 */


// bootstrap app
require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use Platomat\Platon\Core\Config;
use Platomat\Notifier\Client;

// Initialize client with required and optional configuration
Config::extendConfig('NotifierClient.default', [
  'myConfig' => [
    'serverUrl' => 'https://example.com',
    'apiKey'    => 'TODO',
    // optional prefix for subject
    'name'      => 'MyTestSystem',
    'Email'     => [
      'from' => 'test-client@example.com',
      'to'   => 'auto-notification@example.com',
    ],
  ],
]);

$client = new Client('NotifierClient.myConfig');

// Send email
$result = $client->sendMail(
  'Test Subject',
  '<h1>HTML Email Content</h1><p>This is a test email.</p>',
  // $to = mixed array or string comma separated or just string
  // $from = string
);

if ($result['success']) {
  echo "Email sent successfully!" . PHP_EOL;
} else {
  echo "Failed to send email: " . $result['error'] . PHP_EOL;
}
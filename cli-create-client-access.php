<?php

/**
 * CLI
 * Create a client access, return the generated API-Key.
 * @author wasilij.de
 * @version 1.0
 * @date 2025-06-05
 */

// bootstrap app
require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';


use Platomat\Notifier\Common;
use Platomat\Platon\Core\Config;


// Ensure this script runs only in CLI
if (php_sapi_name() !== 'cli') {
  die('This script can only be run from command line.' . PHP_EOL);
}

$allowedFrom  = isset($argv[1]) ? trim($argv[1]) : '';
$allowedTo    = isset($argv[2]) ? trim($argv[2]) : '';
$allowedHosts = isset($argv[3]) ? array_map('trim', explode(',', $argv[3])) : [];
$description  = isset($argv[4]) ? trim($argv[4]) : '';

// Clean empty values from allowedHosts
$allowedHosts = array_filter($allowedHosts, fn($host) => !empty($host));

// Validate email if provided
if (!empty($allowedFrom) && !Common::validateEmail($allowedFrom)) {
  echo "Error: Invalid sender email address provided." . PHP_EOL;
  exit(1);
}

// Load server config to get domain
$serverDomain = Config::read('NotifierServer.default.domain', 'localhost');

// Create clients directory if it doesn't exist
$clientsDir = Config::read('NotifierServer.default.clientsDir');
if (!is_dir($clientsDir)) {
  mkdir($clientsDir, 0755, true);
}

// Generate new API key
$salt = bin2hex(random_bytes(32));
$apiKey = Common::generateApiKey($serverDomain, $salt);

// Create client data - always include all fields for later editing
$clientData = [
  'description'   => $description,
  'allowed_from'  => $allowedFrom,
  'allowed_to'    => $allowedTo,
  'allowed_hosts' => $allowedHosts,
  'created_at'    => date('Y-m-d H:i:s'),
  'salt'          => $salt
];

// Save client data
$clientFile = $clientsDir . $apiKey . '.json';
file_put_contents($clientFile, json_encode($clientData, JSON_PRETTY_PRINT), LOCK_EX);

// Set proper permissions
chmod($clientFile, 0600);

echo "Client access created successfully!" . PHP_EOL;
echo "API Key: {$apiKey}" . PHP_EOL;
echo "File: {$clientFile}" . PHP_EOL;
echo PHP_EOL;

// Show restrictions
echo "=== Access Restrictions ===" . PHP_EOL;

if (!empty($description)) {
  echo "Description: {$description}" . PHP_EOL;
} else {
  echo "Description: No description provided" . PHP_EOL;
}

if (!empty($allowedFrom)) {
  echo "Sender Email: {$allowedFrom}" . PHP_EOL;
} else {
  echo "Sender Email: No restrictions (server default will be used)" . PHP_EOL;
}

if (!empty($allowedTo)) {
  echo "Allowed Recipients: {$allowedTo}" . PHP_EOL;
} else {
  echo "Allowed Recipients: No restrictions (can send to anyone)" . PHP_EOL;
}

if (!empty($allowedHosts)) {
  echo "Allowed Hosts: " . implode(', ', $allowedHosts) . PHP_EOL;
} else {
  echo "Allowed Hosts: No restrictions (can connect from anywhere)" . PHP_EOL;
}

echo PHP_EOL;
echo "=== Client Configuration Example ===" . PHP_EOL;
echo "<?php" . PHP_EOL;
echo "return [" . PHP_EOL;
echo "    'NotifierClient' => [" . PHP_EOL;
echo "        'serverUrl' => '" . $serverDomain . "'," . PHP_EOL;
echo "        'apiKey' => '{$apiKey}'," . PHP_EOL;
if (!empty($allowedFrom)) {
  echo "        'emailFrom' => '{$allowedFrom}'," . PHP_EOL;
}
if (!empty($allowedTo)) {
  echo "        'emailTo' => '{$allowedTo}'," . PHP_EOL;
}
if (!empty($allowedHosts)) {
  echo "        'allowedHosts' => '" . implode(',', $allowedHosts) . "'," . PHP_EOL;
}
echo "        'name' => 'Your System Name'" . PHP_EOL;
echo "    ]" . PHP_EOL;
echo "];" . PHP_EOL;
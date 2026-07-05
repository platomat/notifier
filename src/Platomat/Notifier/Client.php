<?php

namespace Platomat\Notifier;

use Platomat\Platon\Core\Base;
use Platomat\Platon\Core\Config;
use Platomat\Platon\Http\HttpClient;


/**
 * Email notification client.
 * @author wasilij.de
 * @version 1.0
 * @date 2025-06-05
 */
class Client extends Base {


  /**
   * Constructor.
   * */
  public function __construct (string $configKey = 'NotifierClient.default') {
    parent::__construct($configKey);
  }


  /**
   * Send email via notification server.
   */
  public function sendMail (string $subject, string $message, mixed $to = '', string $from = ''): array {
    $clientName = Config::read("{$this->configKey}.name", 'unnamed client');
    $finalSubject = $clientName ? "[{$clientName}] {$subject}" : $subject;

    // take default config email to, if defined
    if (empty($to)) {
      $to = Config::read("{$this->configKey}.Email.to", '');
      $this->logger->info("Email to is empty, take from config: $to");
    }

    // Handle multiple recipients - convert array to comma-separated string
    $toStr = is_array($to) ? implode(',', $to) : $to;

    $emailData = [
      'to'      => $toStr,
      'subject' => $finalSubject,
      'message' => $message
    ];

    // Add from if provided or from config
    if (!empty($from)) {
      $emailData['from'] = $from;
    } elseif (!empty(Config::read("{$this->configKey}.Email.from"))) {
      $emailData['from'] = Config::read("{$this->configKey}.Email.from");
      $this->logger->info("Email from is empty, take from config: {$emailData['from']}");
    }

    // Validate required config
    if (empty(Config::read("{$this->configKey}.apiKey", ''))) {
      $error = 'Missing required configuration: apiKey';
      $this->logger->error($error);
      return ['success' => false, 'error' => $error];
    }

    // Validate email data
    $validationErrors = Common::validateEmailData($emailData);
    if (!empty($validationErrors)) {
      $error = 'Validation failed: ' . implode(', ', $validationErrors);
      $this->logger->error($error);
      return ['success' => false, 'error' => $error];
    }

    // Save debug email if enabled
    if (Config::read("App.debug", false)) {
      Common::saveDebugEmail($emailData, 'client');
    }

    // Attempt to send with retries
    $result = $this->sendRequest($emailData);

    if ($result['success']) {
      $this->logger->info('Email sent successfully', [
        'to'      => $toStr,
        'subject' => $finalSubject
      ]);
      return $result;
    }

    $this->logger->warning('Send attempts failed', [
      'error'       => $result['error']
    ]);

    // if not authorized, do not try again
    if (isset($result['status']) and $result['status'] == 401) {
      $this->logger->error('Not authorized', [
        'result' => $result,
      ]);

      return [
        'success' => false,
        'error' => $result['error'] ?? 'Not authorized',
      ];
    }

    // if forbidden, do not try again
    if (isset($result['status']) and $result['status'] == 403) {
      $this->logger->error('Forbidden', [
        'result' => $result,
      ]);

      return [
        'success' => false,
        'error' => $result['error'] ?? 'Forbidden: check the email address from or to',
      ];
    }

    // All attempts failed - save to failures folder
    $failureFile = Common::saveFailedEmail($emailData, $result['error'] ?? 'Unknown error');

    $this->logger->error('All send attempts failed, saved to failures', [
      'failureFile' => $failureFile,
    ]);

    return [
      'success'     => false,
      'error'       => 'Failed to send email after all attempts',
      'failureFile' => $failureFile
    ];
  }


  /**
   * Send HTTP request to notification server.
   */
  private function sendRequest (array $emailData): array {
    $serverUrl = Config::read("{$this->configKey}.serverUrl");

    if (empty($serverUrl)) {
      return ['success' => false, 'error' => 'Server URL not configured'];
    }

    // Create HttpClient instance
    $httpClient = new HttpClient('HttpClient.notifierClient');
    $headers = [
      'Authorization' => 'Bearer ' . Config::read("{$this->configKey}.apiKey", '')
    ];

    try {
      $response     = $httpClient->post($serverUrl, $emailData, $headers);
      $httpCode     = $response['status'];
      $responseData = json_decode($response['body'],true);

      if ($httpCode !== 200) {
        $error = $responseData['error'] ?? 'HTTP error ' . $httpCode;
        return ['success' => false, 'status' => $httpCode, 'error' => $error];
      }

      // Ensure we have a valid response structure
      if (empty($responseData)) {
        return ['success' => false, 'error' => 'Empty or invalid JSON response from server'];
      }

      // add status and return response
      $responseData['status'] = $httpCode;

      return $responseData;

    } catch (\Exception $e) {
      return ['success' => false, 'error' => 'Request failed: ' . $e->getMessage()];
    }
  }
}
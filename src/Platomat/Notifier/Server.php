<?php

namespace Platomat\Notifier;

use Platomat\Platon\Core\Base;
use Platomat\Platon\Core\Config;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


/**
 * Email notification server.
 * @author wasilij.de
 * @version 1.0
 * @date 2025-06-05
 */
class Server extends Base {


  /**
   * Constructor.
   * */
  public function __construct (string $configKey = 'NotifierServer.default') {
    parent::__construct($configKey);
  }


  /**
   * Handle incoming client request.
   */
  public function handleRequest(): void {
    header('Content-Type: application/json');

    try {
      if (!Common::isSecureConnection()) {
        $this->_sendError(403, 'HTTPS required');
        return;
      }

      // Only allow POST requests
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $this->_sendError(405, 'Method not allowed');
        return;
      }

      // Get request data
      $input        = file_get_contents('php://input');
      $requestData  = json_decode($input, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->_sendError(400, 'Invalid JSON data');
        return;
      }

      // Validate API key and restrictions
      $validationResult = $this->_validateRequest($requestData);
      if (!$validationResult['valid']) {
        $this->_sendError($validationResult['status'], $validationResult['message']);
        return;
      }

      // Check rate limiting
      if (!$this->_checkRateLimit()) {
        $this->_sendError(429, 'Rate limit exceeded');
        return;
      }

      // Validate email data
      $validationErrors = Common::validateEmailData($requestData);
      if (!empty($validationErrors)) {
        $this->_sendError(400, 'Validation failed', $validationErrors);
        return;
      }

      // Send email
      $result = $this->_sendEmail($requestData);

      if ($result['success']) {
        $this->logger->info('Email sent successfully', [
          'to'      => $requestData['to'],
          'from'    => $requestData['from'],
          'subject' => $requestData['subject']
        ]);

        echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
      } else {
        $this->logger->error('Failed to send email', [
          'error' => $result['error'],
          'to' => $requestData['to']
        ]);

        $this->_sendError(500, 'Failed to send email');
      }

    } catch (\Exception $e) {
      $this->logger->error('Server error: ' . $e->getMessage());
      $this->_sendError(500, 'Internal server error');
    }
  }


  /**
   * Validate request: apiKey and client restrictions.
   */
  private function _validateRequest(array &$requestData): array {
    // Get API key from Authorization header instead of request body
    $apiKey = Common::getApiKeyFromHeader();

    if (empty($apiKey)) {
      return ['valid' => false, 'status' => 401, 'message' => 'API key is required'];
    }

    if (!Common::isValidApiKey($apiKey)) {
      return ['valid' => false, 'status' => 401, 'message' => 'Invalid API key'];
    }

    $clientFile = Config::read("{$this->configKey}.clientsDir") . $apiKey . '.json';
    if (!file_exists($clientFile)) {
      $this->logger->warning('Invalid API key used', ['apiKey' => substr($apiKey, 0, 8) . '...']);
      return ['valid' => false, 'status' => 401, 'message' => 'Invalid API key'];
    }

    $clientData = json_decode(file_get_contents($clientFile), true);

    // Set default from email if not provided
    if (empty($requestData['from'])) {
      $requestData['from'] = Config::read("{$this->configKey}.Email.defaultFrom");
    }

    // Set default to email if not provided
    if (empty($requestData['to'])) {
      $requestData['to'] = Config::read("{$this->configKey}.Email.defaultTo");
    }

    // Check from email restriction if defined in client access
    if (!empty($clientData['allowed_from'])) {
      if ($clientData['allowed_from'] !== $requestData['from']) {
        $this->logger->warning('From email not allowed for client', [
          'expected' => $clientData['allowed_from'],
          'provided' => $requestData['from']
        ]);
        return ['valid' => false, 'status' => 403, 'message' => 'Check emailFrom'];
      }
    }

    // Check allowed recipients restriction
    if (!empty($clientData['allowed_to'])) {
        // Convert allowed_to to array if it's a string
      $allowedRecipients = [];
      if (is_string($clientData['allowed_to'])) {
        if ($clientData['allowed_to'] === '*' || trim($clientData['allowed_to']) === '') {
          // Wildcard or empty string = allow all
          $allowedRecipients = ['*'];
        } else {
          // Comma-separated string
          $allowedRecipients = array_map('trim', explode(',', $clientData['allowed_to']));
        }
      } elseif (is_array($clientData['allowed_to'])) {
        $allowedRecipients = $clientData['allowed_to'];
      }

      // Check if wildcard is allowed
      if (!in_array('*', $allowedRecipients)) {
        // Validate each recipient against allowed list
        $requestRecipients = array_map('trim', explode(',', $requestData['to']));
        foreach ($requestRecipients as $recipient) {
          if (!in_array($recipient, $allowedRecipients)) {
            $this->logger->warning('Recipient not allowed for client', [
              'recipient' => $recipient,
              'allowed_to' => $allowedRecipients
            ]);
            return ['valid' => false, 'status' => 403, 'message' => 'Check emailTo'];
          }
        }
      }
    }

    // Check IP restrictions
    if (!empty($clientData['allowed_hosts'])) {
      $clientIp = Common::getClientIp();
      if (!Common::isIpAllowed($clientIp, $clientData['allowed_hosts'])) {
        $this->logger->warning('IP not allowed for client', [
          'client_ip' => $clientIp,
          'allowed_hosts' => $clientData['allowed_hosts']
        ]);
        return ['valid' => false, 'status' => 403, 'message' => 'Host is not allowed'];
      }
    }

    return ['valid' => true];
  }


  /**
   * Check rate limiting per client (persistent storage).
   * TODO: move to custom class: RequestsRateLimiter
   * TODO: return 429, if rate limit exceeded
   * */
  private function _checkRateLimit (): bool {
    $apiKey = Common::getApiKeyFromHeader();

    if (empty($apiKey)) {
      $this->logger->error('Not authorized request');
      return false;
    }

    // check, if rate limit is enabled
    if (!Config::read("{$this->configKey}.RateLimit.enabled", false)) {
      return true;
    }

    $limit        = Config::read("{$this->configKey}.RateLimit.per10seconds", 10);
    $now          = time();
    $windowStart  = $now - 10;

    // Rate limit file path
    $rateLimitDir = Config::read("{$this->configKey}.RateLimit.requestsDir");
    if (empty($rateLimitDir)) {
      $this->logger->error('Missing rate-limit dir in config: NotifierServer.RateLimit.requestsDir');
      return false;
    }

    // Create directory if it doesn't exist
    if (!is_dir($rateLimitDir)) {
      mkdir($rateLimitDir, 0755, true);
    }

    $rateLimitFile = $rateLimitDir . substr(hash('sha256', $apiKey), 0, 16) . '.json';

    // Load existing rate limit data
    $rateLimitData = [];
    if (file_exists($rateLimitFile)) {
      $content = file_get_contents($rateLimitFile);
      if ($content !== false) {
        $rateLimitData = json_decode($content, true) ?: [];
      }
    }

    // Clean old entries (older than 10 seconds)
    $rateLimitData = array_filter($rateLimitData, fn($timestamp) => $timestamp > $windowStart);

    // Check if limit exceeded
    if (count($rateLimitData) >= $limit) {
      return false;
    }

    // Add current request timestamp
    $rateLimitData[] = $now;

    // Save updated rate limit data
    file_put_contents($rateLimitFile, json_encode($rateLimitData), LOCK_EX);

    // Cleanup old rate limit files occasionally (1% chance)
    if (rand(1, 100) === 1) {
      $this->_cleanupOldRateLimitFiles();
    }

    return true;
  }


  /**
   * Clean up old rate limit files.
   */
  private function _cleanupOldRateLimitFiles (): void {
    $rateLimitDir = Config::read("{$this->configKey}.RateLimit.requestsDir");
    if (!is_dir($rateLimitDir)) {
      return;
    }

    $files = glob($rateLimitDir . '*.json');
    $cutoff = time() - 3600; // Files older than 1 hour

    foreach ($files as $file) {
      if (filemtime($file) < $cutoff) {
        unlink($file);
      }
    }
  }


  /**
   * Send email using configured method.
   */
  private function _sendEmail (array $emailData): array {
    $method = Config::read("{$this->configKey}.Email.sendMethod", 'mail');

    // Save debug email if enabled
    if (Config::read("App.debug", false)) {
      Common::saveDebugEmail($emailData, 'server');
    }

    // Try primary method first
    $result = $this->_attemptSend($emailData, $method);

    // Fallback to alternative method if configured
    if (!$result['success'] && Config::read("{$this->configKey}.Email.fallbackEnabled", true)) {
      $fallbackMethod = $method === 'smtp' ? 'mail' : 'smtp';
      $this->logger->info('Primary method failed, trying fallback', ['fallback_method' => $fallbackMethod]);
      $result = $this->_attemptSend($emailData, $fallbackMethod);
    }

    return $result;
  }


  /**
   * Attempt to send email with specified method.
   */
  private function _attemptSend (array $emailData, string $method): array {
    try {
      if ($method === 'smtp') {
        // choose the right Smtp config
        $smtpConfigKey = Config::read("{$this->configKey}.Email.smtpConfig", 'default');
        $smtpConfig = Config::read('Smtp.' . $smtpConfigKey, []);
        return $this->_sendViaSmtp($emailData, $smtpConfig);
      } else {
        return $this->_sendViaPhpMail($emailData);
      }
    } catch (\Exception $e) {
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }


  /**
   * Send email via SMTP.
   */
  private function _sendViaSmtp (array $emailData, array $smtpConfig = []): array {
    if (empty($smtpConfig)) {
      // take default config
      $smtpConfig = Config::read('Smtp.default', []);
      if (empty($smtpConfig)) {
        return ['success' => false, 'error' => 'Missing default Smtp config'];
      }
    }

    $mail = new PHPMailer(true);

    try {
      // Server settings
      $mail->isSMTP();
      $mail->Host       = $smtpConfig['host'];
      $mail->SMTPAuth   = true;
      $mail->Username   = $smtpConfig['username'];
      $mail->Password   = $smtpConfig['password'];
      $mail->SMTPSecure = $smtpConfig['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port       = $smtpConfig['port'] ?? 587;

      // Recipients
      $mail->setFrom($emailData['from']);

      // Handle multiple recipients
      $recipients = explode(',', $emailData['to']);
      foreach ($recipients as $recipient) {
        $recipient = trim($recipient);
        if (!empty($recipient)) {
          $mail->addAddress($recipient);
        }
      }

      // Content
      $mail->isHTML(true);
      $mail->Subject = $emailData['subject'];
      $mail->Body = $emailData['message'];

      $mail->send();
      return ['success' => true];

    } catch (Exception $e) {
      return ['success' => false, 'error' => $mail->ErrorInfo];
    }
  }


  /**
   * Send email via PHP mail() function.
   */
  private function _sendViaPhpMail (array $emailData): array {
    $headers = [
      'MIME-Version: 1.0',
      'Content-type: text/html; charset=UTF-8',
      'From: ' . $emailData['from'],
      'Reply-To: ' . $emailData['from'],
      'X-Mailer: PHP/' . phpversion()
    ];

    // Handle multiple recipients - PHP mail() can handle comma-separated addresses
    $success = mail(
      $emailData['to'], // PHP mail() supports comma-separated recipients
      $emailData['subject'],
      $emailData['message'],
      implode("\r\n", $headers)
    );

    if ($success) {
      return ['success' => true];
    } else {
      return ['success' => false, 'error' => 'PHP mail() function failed'];
    }
  }


  /**
   * Send JSON error response.
   */
  private function _sendError (int $code, string $message, array $details = []): void {
    http_response_code($code);

    $response = ['success' => false, 'error' => $message];
    if (!empty($details) && Config::read("App.debug", false)) {
      $response['details'] = $details;
    }

    echo json_encode($response);
  }
}
<?php

namespace Platomat\Notifier;

use Platomat\Platon\Core\Config;


/**
 * Common functions for both server and client.
 * @author wasilij.de
 * @version 1.0
 * @date 2025-06-05
 */
class Common {

  /**
   * Generate API key based on server domain and salt.
   */
  public static function generateApiKey (string $serverDomain, string $salt = ''): string {
    if (empty($salt)) {
      $salt = bin2hex(random_bytes(32));
    }

    return hash('sha256', $serverDomain . $salt . time());
  }


  /**
   * Validate API key format (64-char SHA-256 hex).
   */
  public static function isValidApiKey (string $apiKey): bool {
    return (bool) preg_match('/^[a-f0-9]{64}$/', $apiKey);
  }


  /**
   * Check if the current request uses HTTPS (with local dev exceptions).
   */
  public static function isSecureConnection (): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
      return true;
    }

    if (($_SERVER['SERVER_PORT'] ?? '') == 443) {
      return true;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';

    if (preg_match('/\.test$/i', $host)) {
      return true;
    }

    if (in_array($host, ['localhost', '127.0.0.1'], true)) {
      return true;
    }

    if (preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host)) {
      return true;
    }

    return false;
  }


  /**
   * Extract API key from Authorization header.
   * TODO: move to request class
   */
  public static function getApiKeyFromHeader(string $authType = 'Bearer'): string {
    // Try different ways to get the Authorization header
    $authHeader = '';

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
      $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
      $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
      $headers = apache_request_headers();
      if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
      }
    } elseif (function_exists('getallheaders')) {
      $headers = getallheaders();
      if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
      }
    }

    // Extract token from "Bearer TOKEN" format
    if (!empty($authHeader) && str_starts_with($authHeader, $authType . ' ')) {
      return substr($authHeader, strlen($authType)+1); // Remove "Bearer " prefix
    }

    return '';
  }


  /**
   * Validate email address.
   */
  public static function validateEmail (string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
  }


  /**
   * Validate required fields for email sending.
   */
  public static function validateEmailData (array $data): array {
    $errors = [];

    // to is optional - server will set default if missing
    if (!empty($data['to'])) {
      $recipients = explode(',', $data['to']);
      foreach ($recipients as $recipient) {
        $recipient = trim($recipient);
        if (!self::validateEmail($recipient)) {
          $errors[] = 'Invalid recipient email address: ' . $recipient;
        }
      }
    }

    // from is optional - server will set default if missing
    if (!empty($data['from']) && !self::validateEmail($data['from'])) {
      $errors[] = 'Invalid sender email address';
    }

    if (empty($data['subject'])) {
      $errors[] = 'Subject is required';
    }

    if (empty($data['message'])) {
      $errors[] = 'Message is required';
    }

    return $errors;
  }


  /**
   * Check if IP address is in allowed list.
   */
  public static function isIpAllowed (string $clientIp, array $allowedIps): bool {
    if (empty($allowedIps)) {
      return true; // No restrictions
    }

    foreach ($allowedIps as $allowedIp) {
      // Support for CIDR notation
      if (str_contains($allowedIp, '/')) {
        if (self::ipInCidr($clientIp, $allowedIp)) {
          return true;
        }
      } else {
        if ($clientIp === $allowedIp) {
          return true;
        }
      }
    }

    return false;
  }


  /**
   * Check if IP is in CIDR range.
   */
  private static function ipInCidr (string $ip, string $cidr): bool {
    [$subnet, $bits] = explode('/', $cidr);

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      $ip = ip2long($ip);
      $subnet = ip2long($subnet);
      $mask = -1 << (32 - $bits);
      $subnet &= $mask;
      return ($ip & $mask) == $subnet;
    }

    return false;
  }


  /**
   * Get client IP address; forwarded headers only when behind a trusted proxy.
   */
  public static function getClientIp (): string {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $trustedProxies = Config::read('NotifierServer.default.trustedProxies', []);

    if (empty($trustedProxies) || !in_array($remoteAddr, $trustedProxies, true)) {
      return $remoteAddr;
    }

    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'];

    foreach ($ipKeys as $key) {
      if (empty($_SERVER[$key])) {
        continue;
      }

      $ips = explode(',', $_SERVER[$key]);
      $ip = trim($ips[0]);

      if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
      }
    }

    return $remoteAddr;
  }


  /**
   * Save debug email to tmp/debug folder.
   */
  public static function saveDebugEmail (array $emailData, string $prefix = ''): string {
    $debugDir = Config::read('Common.debugDir');
    if (!is_dir($debugDir)) {
      mkdir($debugDir, 0755, true);
    }

    $timestamp      = date('Y-m-d--H-i-s');
    $emailDataJson  = json_encode($emailData);
    $hash           = substr(md5($emailDataJson), 0, 8);
    $filename       = $prefix ? "{$prefix}_{$timestamp}_{$hash}.html" : "{$timestamp}_{$hash}.html";

    $filepath       = $debugDir . '/' . $filename;
    file_put_contents($filepath, $emailDataJson);

    return $filename;
  }


  /**
   * Save failed email to tmp/failures folder.
   */
  public static function saveFailedEmail (array $emailData, string $error): string {
    $failuresDir = Config::read('Common.failuresDir');
    if (!is_dir($failuresDir)) {
      mkdir($failuresDir, 0755, true);
    }

    $timestamp  = date('Y-m-d--H-i-s');
    $content    = json_encode(['email_data' => $emailData, 'error' => $error, 'timestamp' => $timestamp], JSON_PRETTY_PRINT);
    $hash       = substr(md5($content), 0, 8);
    $filename   = "{$timestamp}_{$hash}.html";

    $filepath   = $failuresDir . '/' . $filename;
    file_put_contents($filepath, $content);

    return $filename;
  }
}
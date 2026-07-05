# Platomat Notifier

A PHP email notification system with server/client architecture for sending emails from multiple systems without requiring SMTP configuration on each client.

The application runs standalone or as a Composer dependency. Server and client are bundled in one package so separate apps are not required.

## Design Specification

### Purpose

Centralized email delivery: client systems send HTTP requests to a notifier server, which handles authentication, validation, and actual mail delivery. Clients do not need a local SMTP setup.

### Architecture

| Component | Responsibility |
|-----------|----------------|
| **Server** (`index.php` → `Server::handleRequest()`) | Validates API key, host/IP restrictions, and request parameters; sends the email |
| **Client** (`Client::sendMail()`) | Sends a POST request to the server with `to`, `subject`, `message`, and the configured from-address |
| **Common** | Shared utilities used by server and client |

### Technical Decisions

- **PHP** 8.3 minimum
- **SMTP** via PHPMailer (no custom SMTP implementation)
- **HTTP client** cURL
- **Autoloading** PSR-4 via Composer
- **Config format** PHP arrays for server/client settings; JSON for per-client access records on the server
- **Config merge** Constructor arguments are merged recursively with the default config
- **Config access** Dot notation, CakePHP-style: `Config::read('foo.bar', 'default')`
- **Timezone** Europe/Berlin

### Authentication & Security

- API keys, minimum 64 characters, generated as `hash('sha256', SERVER_DOMAIN . CLIENT_EMAIL . SALT . time())`
- One JSON file per client in `data/notifier/clients/<API_KEY>.json` (email, allowed hosts/IPs)
- HTTPS only
- Rate limiting per client (configurable number of emails per 10-second window)
- Optional host/IP whitelist per client
- Server responds with JSON on errors: status codes and short messages, no sensitive details
- `.htaccess` blocks direct web access to project files

### Email Behaviour

- HTML emails only; no CC/BCC
- Attachments planned for a later version
- Server: configurable primary method (`mail()` or SMTP) with automatic fallback to the other method
- Client: up to 3 retry attempts; failures are saved to `tmp/failures/`

### Client Configuration

Besides server URL and API key, the client config includes:

- **Client name** — prepended to the subject, e.g. `[MySystem1] Original subject`
- **From address** — sent with each request; the server validates it against the client's restrictions

### CLI: Create Client Access

`cli-create-client-access.php` / `cli-create-client-access.sh` (CLI only):

1. Client email address (allowed sender)
2. Optional comma-separated list of allowed hosts or IPs

If a matching client already exists, the existing API key is printed. Otherwise a new key is generated and saved.

### Debug Mode

When `debug => true` in config:

- **Server & client** save outgoing/incoming emails to `tmp/debug/`
- **Client** saves undeliverable emails to `tmp/failures/`
- Filenames: `Y-m-d--H-i-s_<content-hash>.html`

No statistics or metrics are collected beyond debug logging.

### Code Conventions

- Comments in English
- Opening brace on the same line as method definitions
- Two blank lines after each method
- Typed parameters and return types

## Features

- **Server/Client Architecture**: Centralized email sending with distributed clients
- **API Key Authentication**: Secure access with optional host/IP restrictions
- **Dual Email Methods**: Support for both PHP `mail()` and SMTP with automatic fallback
- **Rate Limiting**: Configurable per-client rate limiting (emails per 10 seconds)
- **Retry Logic**: Automatic retries on client side with failure logging
- **Debug Mode**: Save emails to files for debugging purposes
- **Logging**: Comprehensive logging with different levels
- **HTTPS Only**: Enforced secure connections

## Requirements

- PHP 8.3+
- cURL extension
- JSON extension
- Composer

## Installation

1. **Install via Composer:**
   ```bash
   composer install
   ```

2. **Configure Server/Client:**
   - Edit `config/config_notifier.php`
    - OR: setup client and server via constructor
   - Set your domain, SMTP settings, and other preferences
   - Set server URL and other client settings

4. **Create Client Access:**
   ```bash
   # use bash for interactive client setup
   /bin/bash cli-create-client-access.sh
   # OR
   php create-client-access.php <allowedFrom> <$allowedTo> <allowedHosts>
   ```

5. **Set Permissions:**
   ```bash
   chmod 755 logs tmp data/notifier/clients
   chmod 600 data/notifier/clients/*.json
   ```

## Usage

### Server Setup

Deploy the notifier to your web server and ensure `index.php` is accessible via HTTPS:

```php
// index.php handles all server requests automatically
// Configure via config/config_notifier_server.php or pass config array
```

### Client Usage

See example files
- index.sample.php (Server)
- cli-test-client.sample.php (Client)


## Configuration

### Server Configuration (`config/config_server.php`)

Have alook into config/config_server.php file.


## Security Features

- **HTTPS Enforcement**: All requests must use HTTPS
- **API Key Authentication**: Each client requires a unique API key
- **IP/Host Restrictions**: Optional whitelist of allowed client IPs/hosts
- **Rate Limiting**: Configurable limits per client
- **Input Validation**: All email data is validated before processing
- **Secure Headers**: Security headers automatically added


## Debug Mode

Enable debug mode in configuration:

```php
'debug' => true
```

When enabled:
- **Server**: Saves incoming emails to `tmp/debug/`
- **Client**: Saves outgoing emails to `tmp/debug/`
- Failed emails are saved to `tmp/failures/`

Debug files use format: `Y-m-d--H-i-s_[hash].html`

## Logging

Logs are written to `logs/debug.log` with different levels:
- **DEBUG**: Detailed debug information
- **INFO**: General information (successful sends)
- **WARNING**: Warning conditions (rate limits, invalid IPs)
- **ERROR**: Error conditions (send failures, validation errors)

## Directory Structure

```
notifier/
├── index.php                          # Server entry point
├── cli-create-client-access.sh        # CLI tool for client creation, interactive mode
├── cli-create-client-access.php       # CLI tool for client creation
├── composer.json                      # Dependencies
├── .htaccess                          # Web server security
├── src/Platomat/
│   ├── Notifier/
│   │   ├── Server.php                 # Server implementation
│   │   ├── Client.php                 # Client implementation
│   │   └── Common.php                 # Shared utilities
│   ├── Core/
│   │   └── Config.php                 # Configuration management
│   └── Logging/
│       └── Logger.php                 # Logging implementation
├── config/
│   └── config_notifier.php            # configuration
├── data/notifier/clients/             # Client access files (API keys)
├── logs/                              # Log files
└── tmp/
    ├── debug/                         # Debug emails
    └── failures/                      # Failed email attempts
```

## Error Codes

- **400**: Bad Request (validation failed, invalid JSON)
- **401**: Unauthorized (invalid API key, email mismatch)
- **405**: Method Not Allowed (non-POST request)
- **429**: Too Many Requests (rate limit exceeded)
- **500**: Internal Server Error (email send failure, server error)

## Git

Ignored paths (see `.gitignore`):

- `tmp/`
- `logs/`
- `data/notifier/clients/`
- `vendor/`
- `composer.lock`
- `cli-test-client-local.php`
- `test-server-local.php`
- `index.php`
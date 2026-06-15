<?php
/**
 * Configuration File
 * Loads environment variables and sets global constants
 */

// Load .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load environment file
$envFile = dirname(__DIR__, 2) . '/.env';
loadEnv($envFile);

// Application Constants
define('APP_NAME', getenv('APP_NAME') ?: 'CyberAI Platform');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost');
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_SECRET', getenv('APP_SECRET') ?: 'fallback_secret_key_change_me');

// Database Constants
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'cyber_ai_platform');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// AI Provider Configuration
define('AI_PROVIDER', getenv('AI_PROVIDER') ?: 'local');

// Gemini API
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL', getenv('GEMINI_MODEL') ?: 'gemini-1.5-pro');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');

// Ollama API
define('OLLAMA_API_URL', getenv('OLLAMA_API_URL') ?: 'http://host.docker.internal:11434');
define('OLLAMA_MODEL', getenv('OLLAMA_MODEL') ?: 'llama3');

// Email
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', getenv('MAIL_PORT') ?: '587');
define('MAIL_USER', getenv('MAIL_USER') ?: '');
define('MAIL_PASS', getenv('MAIL_PASS') ?: '');
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'security@platform.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'CyberAI Platform');

// Telegram
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '');
define('TELEGRAM_CHAT_ID', getenv('TELEGRAM_CHAT_ID') ?: '');

// Paths
define('BASE_PATH', dirname(__DIR__, 2));
define('DASHBOARD_PATH', dirname(__DIR__));
define('LOGS_PATH', BASE_PATH . '/logs');
define('ASSETS_URL', APP_URL . '/dashboard/assets');

// Session
define('SESSION_LIFETIME', getenv('SESSION_LIFETIME') ?: 7200);
define('SESSION_NAME', getenv('SESSION_NAME') ?: 'cyber_ai_session');

// Security
define('CSRF_TOKEN_LENGTH', 32);
define('PASSWORD_MIN_LENGTH', 8);

// Pagination
define('ITEMS_PER_PAGE', 20);

// Severity Levels
define('SEVERITY_CRITICAL', 'critical');
define('SEVERITY_HIGH', 'high');
define('SEVERITY_MEDIUM', 'medium');
define('SEVERITY_LOW', 'low');
define('SEVERITY_INFO', 'info');

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_ANALYST', 'analyst');
define('ROLE_VIEWER', 'viewer');

// Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set session name
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

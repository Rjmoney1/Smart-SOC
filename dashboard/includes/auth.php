<?php
/**
 * Authentication System
 * Handles login, logout, session management, CSRF, and RBAC
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class Auth {
    private static $instance = null;
    private Database $db;

    private function __construct() {
        $this->db = Database::getInstance();
        $this->startSession();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }

    public function login(string $username, string $password): array {
        // Rate limiting check
        if ($this->isRateLimited($username)) {
            return ['success' => false, 'message' => 'Too many failed attempts. Try again in 15 minutes.'];
        }

        $user = $this->db->fetch(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1",
            [$username, $username]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            $this->logFailedAttempt($username);
            return ['success' => false, 'message' => 'Invalid credentials. Please try again.'];
        }

        // Regenerate session to prevent fixation
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['avatar']    = $user['avatar'];
        $_SESSION['login_at']  = time();
        $_SESSION['csrf_token'] = $this->generateCsrfToken();

        // Update last login
        $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        $this->clearFailedAttempts($username);

        // Log successful login
        logActivity('auth', 'login', "User {$user['username']} logged in", 'info', $user['id']);

        return ['success' => true, 'role' => $user['role']];
    }

    public function logout(): void {
        if ($this->isLoggedIn()) {
            logActivity('auth', 'logout', "User {$_SESSION['username']} logged out", 'info', $_SESSION['user_id']);
        }
        session_unset();
        session_destroy();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
    }

    public function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            header('Location: ' . APP_URL . '/dashboard/login.php');
            exit;
        }
        // Session timeout
        if (isset($_SESSION['login_at']) && (time() - $_SESSION['login_at']) > SESSION_LIFETIME) {
            $this->logout();
            header('Location: ' . APP_URL . '/dashboard/login.php?timeout=1');
            exit;
        }
    }

    public function requireRole(string ...$roles): void {
        $this->requireLogin();
        if (!in_array($_SESSION['role'] ?? '', $roles)) {
            http_response_code(403);
            include DASHBOARD_PATH . '/includes/403.php';
            exit;
        }
    }

    public function getCurrentUser(): ?array {
        if (!$this->isLoggedIn()) return null;
        return [
            'id'        => $_SESSION['user_id'],
            'username'  => $_SESSION['username'],
            'email'     => $_SESSION['email'],
            'role'      => $_SESSION['role'],
            'full_name' => $_SESSION['full_name'],
            'avatar'    => $_SESSION['avatar'],
        ];
    }

    public function generateCsrfToken(): string {
        $token = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public function verifyCsrfToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public function register(array $data): array {
        // Validate inputs
        if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.'];
        }
        if ($data['password'] !== $data['confirm_password']) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }

        // Check existing
        $existing = $this->db->fetch("SELECT id FROM users WHERE username = ? OR email = ?", [$data['username'], $data['email']]);
        if ($existing) {
            return ['success' => false, 'message' => 'Username or email already exists.'];
        }

        $hashed = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $userId = $this->db->insert('users', [
            'username'   => htmlspecialchars($data['username']),
            'email'      => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
            'password'   => $hashed,
            'full_name'  => htmlspecialchars($data['full_name']),
            'role'       => $data['role'] ?? ROLE_ANALYST,
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        logActivity('auth', 'register', "New user registered: {$data['username']}", 'info', $userId);
        return ['success' => true, 'user_id' => $userId];
    }

    private function isRateLimited(string $username): bool {
        $key = 'failed_' . md5($username . $_SERVER['REMOTE_ADDR']);
        $attempts = $_SESSION[$key . '_count'] ?? 0;
        $lastAttempt = $_SESSION[$key . '_time'] ?? 0;
        if ($attempts >= 5 && (time() - $lastAttempt) < 900) {
            return true;
        }
        if ((time() - $lastAttempt) >= 900) {
            $_SESSION[$key . '_count'] = 0;
        }
        return false;
    }

    private function logFailedAttempt(string $username): void {
        $key = 'failed_' . md5($username . $_SERVER['REMOTE_ADDR']);
        $_SESSION[$key . '_count'] = ($_SESSION[$key . '_count'] ?? 0) + 1;
        $_SESSION[$key . '_time'] = time();
    }

    private function clearFailedAttempts(string $username): void {
        $key = 'failed_' . md5($username . $_SERVER['REMOTE_ADDR']);
        unset($_SESSION[$key . '_count'], $_SESSION[$key . '_time']);
    }
}

// Global Auth Helper
function auth(): Auth {
    return Auth::getInstance();
}

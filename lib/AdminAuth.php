<?php

declare(strict_types=1);

final class AdminAuth
{
    private const SESSION_USER_ID = 'self_admin_user_id';
    private const SESSION_USERNAME = 'self_admin_username';
    private const SESSION_AUTH_VERSION = 'self_admin_auth_version';
    private const SESSION_LAST_ACTIVITY = 'self_admin_last_activity';
    private const CSRF_KEY = 'self_admin_csrf';

    private const IDLE_TIMEOUT_SECONDS = 28800;

    private AdminUserStore $users;
    private LoginRateLimiter $rateLimiter;
    private ?string $rootDir;

    public function __construct(?string $rootDir = null)
    {
        $this->rootDir = $rootDir;
        $this->users = new AdminUserStore($rootDir);
        $this->rateLimiter = new LoginRateLimiter($rootDir);
    }

    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    public function isLoggedIn(): bool
    {
        $this->startSession();

        if (!$this->sessionIsFresh()) {
            return false;
        }

        $userId = $_SESSION[self::SESSION_USER_ID] ?? '';
        if (!is_string($userId) || $userId === '') {
            return false;
        }

        $user = $this->users->findById($userId);
        if ($user === null || !empty($user['disabled'])) {
            return false;
        }

        $sessionVersion = (int) ($_SESSION[self::SESSION_AUTH_VERSION] ?? 0);
        if ($sessionVersion !== (int) ($user['auth_version'] ?? 1)) {
            return false;
        }

        $_SESSION[self::SESSION_LAST_ACTIVITY] = time();

        return true;
    }

    /**
     * @return array{id: string, username: string}|null
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $userId = (string) ($_SESSION[self::SESSION_USER_ID] ?? '');
        $user = $this->users->findById($userId);

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user['id'],
            'username' => $user['username'],
        ];
    }

    public function getCurrentUserId(): ?string
    {
        return $this->getCurrentUser()['id'] ?? null;
    }

    public function login(string $username, string $password): bool
    {
        $ip = Security::clientIp();
        if ($this->isLoginLocked($ip)) {
            return false;
        }

        $user = $this->users->findByUsername($username);
        if ($user === null || !$this->users->verifyPassword($user, $password)) {
            $this->rateLimiter->recordFailure($ip);

            return false;
        }

        $this->rateLimiter->clear($ip);
        $this->startSession();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = $user['id'];
        $_SESSION[self::SESSION_USERNAME] = $user['username'];
        $_SESSION[self::SESSION_AUTH_VERSION] = (int) ($user['auth_version'] ?? 1);
        $_SESSION[self::SESSION_LAST_ACTIVITY] = time();
        $this->regenerateCsrfToken();

        return true;
    }

    public function logout(): void
    {
        $this->startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }
        session_destroy();
    }

    public function requireLogin(): void
    {
        if ($this->isLoggedIn()) {
            return;
        }

        header('Location: login.php');
        exit;
    }

    public function getCsrfToken(): string
    {
        $this->startSession();
        if (empty($_SESSION[self::CSRF_KEY])) {
            $this->regenerateCsrfToken();
        }

        return (string) $_SESSION[self::CSRF_KEY];
    }

    public function verifyCsrf(?string $token): bool
    {
        $this->startSession();
        $expected = $_SESSION[self::CSRF_KEY] ?? '';

        return is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }

    public function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e($this->getCsrfToken()) . '">';
    }

    public function isConfigured(): bool
    {
        return $this->users->isConfigured();
    }

    public function getUserStore(): AdminUserStore
    {
        return $this->users;
    }

    public function isLoginLocked(?string $ip = null): bool
    {
        return $this->rateLimiter->isLocked($ip ?? Security::clientIp());
    }

    public function loginLockRemainingSeconds(?string $ip = null): int
    {
        return $this->rateLimiter->lockRemainingSeconds($ip ?? Security::clientIp());
    }

    private function sessionIsFresh(): bool
    {
        $last = (int) ($_SESSION[self::SESSION_LAST_ACTIVITY] ?? 0);
        if ($last === 0) {
            return true;
        }

        return (time() - $last) < self::IDLE_TIMEOUT_SECONDS;
    }

    private function regenerateCsrfToken(): void
    {
        $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
    }

    private function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return !empty($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443';
    }
}

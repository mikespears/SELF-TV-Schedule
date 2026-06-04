<?php

declare(strict_types=1);

final class AdminUserStore
{
    private const MIN_PASSWORD_LENGTH = 10;
    private const MIN_USERNAME_LENGTH = 3;
    private const MAX_USERNAME_LENGTH = 32;

    private string $usersPath;
    private string $legacySecretsPath;

    public function __construct(?string $rootDir = null)
    {
        $root = $rootDir ?? dirname(__DIR__);
        $this->usersPath = $root . '/data/admin/users.json';
        $this->legacySecretsPath = $root . '/data/admin.secrets.php';
    }

    public function isConfigured(): bool
    {
        return $this->countActiveUsers() > 0;
    }

    public function countActiveUsers(): int
    {
        $count = 0;
        foreach ($this->loadUsers() as $user) {
            if (empty($user['disabled'])) {
                $count++;
            }
        }

        return $count;
    }

    public function countUsers(): int
    {
        return count($this->loadUsers());
    }

    /**
     * @return list<array{id: string, username: string, password_hash: string, created_at: string, disabled: bool, auth_version: int}>
     */
    public function loadUsers(): array
    {
        $this->migrateLegacySecretsIfNeeded();

        if (!is_file($this->usersPath)) {
            return [];
        }

        $body = @file_get_contents($this->usersPath);
        if ($body === false) {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            error_log('SELF Schedule Display: invalid admin users.json');

            return [];
        }

        if (!is_array($decoded) || !isset($decoded['users']) || !is_array($decoded['users'])) {
            return [];
        }

        $users = [];
        foreach ($decoded['users'] as $user) {
            if (!is_array($user)) {
                continue;
            }
            $normalized = $this->normalizeUser($user);
            if ($normalized !== null) {
                $users[] = $normalized;
            }
        }

        usort($users, static fn (array $a, array $b): int => strcmp($a['username'], $b['username']));

        return $users;
    }

    /**
     * @return array{id: string, username: string, password_hash: string, created_at: string, disabled: bool, auth_version: int}|null
     */
    public function findByUsername(string $username): ?array
    {
        $needle = strtolower(trim($username));

        foreach ($this->loadUsers() as $user) {
            if (strcasecmp($user['username'], $needle) === 0) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @return array{id: string, username: string, password_hash: string, created_at: string, disabled: bool, auth_version: int}|null
     */
    public function findById(string $id): ?array
    {
        foreach ($this->loadUsers() as $user) {
            if ($user['id'] === $id) {
                return $user;
            }
        }

        return null;
    }

    public function verifyPassword(array $user, string $password): bool
    {
        if (!empty($user['disabled'])) {
            return false;
        }

        return password_verify($password, (string) $user['password_hash']);
    }

    /**
     * @return array{id: string, username: string, password_hash: string, created_at: string, disabled: bool, auth_version: int}
     */
    public function createUser(string $username, string $password, bool $disabled = false): array
    {
        $username = $this->validateUsername($username);
        $this->validatePassword($password);

        if ($this->findByUsername($username) !== null) {
            throw new InvalidArgumentException('That username is already in use.');
        }

        $user = [
            'id' => $this->generateId(),
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
            'disabled' => $disabled,
            'auth_version' => 1,
        ];

        $users = $this->loadUsers();
        $users[] = $user;
        $this->writeUsers($users);

        return $user;
    }

    public function updatePassword(string $userId, string $newPassword): void
    {
        $this->validatePassword($newPassword);
        $users = $this->loadUsers();
        $found = false;

        foreach ($users as &$user) {
            if ($user['id'] !== $userId) {
                continue;
            }
            $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $found = true;
            break;
        }
        unset($user);

        if (!$found) {
            throw new InvalidArgumentException('User not found.');
        }

        $this->writeUsers($users);
    }

    public function setDisabled(string $userId, bool $disabled): void
    {
        $users = $this->loadUsers();
        $found = false;

        foreach ($users as &$user) {
            if ($user['id'] !== $userId) {
                continue;
            }
            if ($disabled && $this->countActiveUsers() <= 1 && empty($user['disabled'])) {
                throw new InvalidArgumentException('Cannot disable the last active admin user.');
            }
            $user['disabled'] = $disabled;
            $found = true;
            break;
        }
        unset($user);

        if (!$found) {
            throw new InvalidArgumentException('User not found.');
        }

        $this->writeUsers($users);
    }

    public function deleteUser(string $userId): void
    {
        $users = $this->loadUsers();
        if (count($users) <= 1) {
            throw new InvalidArgumentException('Cannot delete the only admin user.');
        }

        $filtered = array_values(array_filter(
            $users,
            static fn (array $user): bool => $user['id'] !== $userId
        ));

        if (count($filtered) === count($users)) {
            throw new InvalidArgumentException('User not found.');
        }

        $this->writeUsers($filtered);
    }

    public static function hashPassword(string $password): string
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.');
        }

        return password_hash($password, PASSWORD_DEFAULT);
    }

    private function migrateLegacySecretsIfNeeded(): void
    {
        if (is_file($this->usersPath) || !is_file($this->legacySecretsPath)) {
            return;
        }

        $hash = $this->readLegacyPasswordHash();
        if ($hash === '') {
            return;
        }

        $user = [
            'id' => $this->generateId(),
            'username' => 'admin',
            'password_hash' => $hash,
            'created_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
            'disabled' => false,
            'auth_version' => 1,
        ];

        $this->writeUsers([$user]);
        error_log('SELF Schedule Display: migrated legacy admin.secrets.php to data/admin/users.json');
    }

    /**
     * @param array<string, mixed> $user
     * @return array{id: string, username: string, password_hash: string, created_at: string, disabled: bool, auth_version: int}|null
     */
    private function normalizeUser(array $user): ?array
    {
        $id = trim((string) ($user['id'] ?? ''));
        $username = trim((string) ($user['username'] ?? ''));
        $hash = trim((string) ($user['password_hash'] ?? ''));

        if ($id === '' || $username === '' || $hash === '') {
            return null;
        }

        try {
            $username = $this->validateUsername($username);
        } catch (InvalidArgumentException) {
            return null;
        }

        return [
            'id' => $id,
            'username' => $username,
            'password_hash' => $hash,
            'created_at' => trim((string) ($user['created_at'] ?? '')) ?: gmdate('c'),
            'disabled' => !empty($user['disabled']),
            'auth_version' => max(1, (int) ($user['auth_version'] ?? 1)),
        ];
    }

    /**
     * @return list<array{id: string, username: string, created_at: string, disabled: bool}>
     */
    public function listUsersPublic(): array
    {
        return array_map(
            static fn (array $user): array => [
                'id' => $user['id'],
                'username' => $user['username'],
                'created_at' => $user['created_at'],
                'disabled' => $user['disabled'],
            ],
            $this->loadUsers()
        );
    }

    /**
     * @param list<array{id: string, username: string, password_hash: string, created_at: string, disabled: bool, auth_version: int}> $users
     */
    private function writeUsers(array $users): void
    {
        Security::writePrivateFile(
            $this->usersPath,
            json_encode(['users' => $users], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private function readLegacyPasswordHash(): string
    {
        $body = @file_get_contents($this->legacySecretsPath);
        if ($body === false) {
            return '';
        }

        if (preg_match("/['\"]password_hash['\"]\s*=>\s*['\"]([^'\"]+)['\"]/", $body, $matches) === 1) {
            return trim($matches[1]);
        }

        return '';
    }

    private function validateUsername(string $username): string
    {
        $username = strtolower(trim($username));
        $length = strlen($username);

        if ($length < self::MIN_USERNAME_LENGTH || $length > self::MAX_USERNAME_LENGTH) {
            throw new InvalidArgumentException(
                'Username must be ' . self::MIN_USERNAME_LENGTH . '–' . self::MAX_USERNAME_LENGTH . ' characters.'
            );
        }

        if (preg_match('/^[a-z0-9][a-z0-9._-]*[a-z0-9]$/', $username) !== 1 && preg_match('/^[a-z0-9]{3}$/', $username) !== 1) {
            throw new InvalidArgumentException(
                'Username may use lowercase letters, numbers, dots, underscores, and hyphens.'
            );
        }

        return $username;
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(
                'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'
            );
        }
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}

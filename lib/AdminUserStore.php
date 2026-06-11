<?php

declare(strict_types=1);

final class AdminUserStore
{
    private const MIN_PASSWORD_LENGTH = 10;
    private const MIN_USERNAME_LENGTH = 3;
    private const MAX_USERNAME_LENGTH = 32;

    private string $rootDir;
    private string $usersPath;
    private string $legacySecretsPath;

    public function __construct(?string $rootDir = null)
    {
        $this->rootDir = $rootDir ?? dirname(__DIR__);
        $this->usersPath = $this->rootDir . '/data/admin/users.json';
        $this->legacySecretsPath = $this->rootDir . '/data/admin.secrets.php';
    }

    public function isConfigured(): bool
    {
        return $this->countActiveUsers() > 0;
    }

    public function countActiveUsers(): int
    {
        if ($this->usesDatabase()) {
            $pdo = Database::connection($this->rootDir);

            return (int) $pdo->query('SELECT COUNT(*) FROM admin_users WHERE disabled = 0')->fetchColumn();
        }

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
        if ($this->usesDatabase()) {
            $pdo = Database::connection($this->rootDir);

            return (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        }

        return count($this->loadUsers());
    }

    public function getStorageBackend(): string
    {
        return $this->usesDatabase() ? 'database' : 'file';
    }

    /**
     * @return list<array{id: string, username: string, password_hash: string, created_at: string, disabled: bool, auth_version: int}>
     */
    public function loadUsers(): array
    {
        $this->migrateLegacySecretsIfNeeded();

        if ($this->usesDatabase()) {
            return $this->loadUsersFromDatabase();
        }

        return $this->loadUsersFromFile();
    }

    /**
     * @return array{id: string, username: string, password_hash: string, created_at: string, disabled: bool, auth_version: int}|null
     */
    public function findByUsername(string $username): ?array
    {
        $needle = strtolower(trim($username));

        if ($this->usesDatabase()) {
            $pdo = Database::connection($this->rootDir);
            $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = :username LIMIT 1');
            $stmt->execute(['username' => $needle]);
            $row = $stmt->fetch();

            return is_array($row) ? $this->mapDatabaseRow($row) : null;
        }

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
        if ($this->usesDatabase()) {
            $pdo = Database::connection($this->rootDir);
            $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();

            return is_array($row) ? $this->mapDatabaseRow($row) : null;
        }

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

        if ($this->usesDatabase()) {
            $this->insertUserToDatabase($user);

            return $user;
        }

        $users = $this->loadUsersFromFile();
        $users[] = $user;
        $this->writeUsersToFile($users);

        return $user;
    }

    public function updatePassword(string $userId, string $newPassword): void
    {
        $this->validatePassword($newPassword);

        if ($this->usesDatabase()) {
            $pdo = Database::connection($this->rootDir);
            $stmt = $pdo->prepare(
                'UPDATE admin_users
                 SET password_hash = :password_hash, auth_version = auth_version + 1
                 WHERE id = :id'
            );
            $stmt->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $userId,
            ]);
            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException('User not found.');
            }

            return;
        }

        $users = $this->loadUsersFromFile();
        $found = false;

        foreach ($users as &$user) {
            if ($user['id'] !== $userId) {
                continue;
            }
            $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $user['auth_version'] = (int) ($user['auth_version'] ?? 1) + 1;
            $found = true;
            break;
        }
        unset($user);

        if (!$found) {
            throw new InvalidArgumentException('User not found.');
        }

        $this->writeUsersToFile($users);
    }

    public function setDisabled(string $userId, bool $disabled): void
    {
        if ($this->usesDatabase()) {
            $user = $this->findById($userId);
            if ($user === null) {
                throw new InvalidArgumentException('User not found.');
            }
            if ($disabled && empty($user['disabled']) && $this->countActiveUsers() <= 1) {
                throw new InvalidArgumentException('Cannot disable the last active admin user.');
            }

            $pdo = Database::connection($this->rootDir);
            $stmt = $pdo->prepare('UPDATE admin_users SET disabled = :disabled WHERE id = :id');
            $stmt->execute(['disabled' => $disabled ? 1 : 0, 'id' => $userId]);

            return;
        }

        $users = $this->loadUsersFromFile();
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

        $this->writeUsersToFile($users);
    }

    public function deleteUser(string $userId): void
    {
        if ($this->usesDatabase()) {
            if ($this->countUsers() <= 1) {
                throw new InvalidArgumentException('Cannot delete the only admin user.');
            }

            $pdo = Database::connection($this->rootDir);
            $stmt = $pdo->prepare('DELETE FROM admin_users WHERE id = :id');
            $stmt->execute(['id' => $userId]);
            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException('User not found.');
            }

            return;
        }

        $users = $this->loadUsersFromFile();
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

        $this->writeUsersToFile($filtered);
    }

    public static function hashPassword(string $password): string
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.');
        }

        return password_hash($password, PASSWORD_DEFAULT);
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

    private function usesDatabase(): bool
    {
        return Database::isConfigured($this->rootDir);
    }

    private function migrateLegacySecretsIfNeeded(): void
    {
        if (!is_file($this->legacySecretsPath)) {
            return;
        }

        if ($this->usesDatabase()) {
            if ($this->countUsers() > 0) {
                return;
            }
        } elseif (is_file($this->usersPath)) {
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

        if ($this->usesDatabase()) {
            $this->insertUserToDatabase($user);
            error_log('SELF Schedule Display: migrated legacy admin.secrets.php to database');
        } else {
            $this->writeUsersToFile([$user]);
            error_log('SELF Schedule Display: migrated legacy admin.secrets.php to data/admin/users.json');
        }
    }

    /**
     * @return list<array{id: string, username: string, password_hash: string, created_at: string, disabled: bool, auth_version: int}>
     */
    private function loadUsersFromFile(): array
    {
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
     * @return list<array{id: string, username: string, password_hash: string, created_at: string, disabled: bool, auth_version: int}>
     */
    private function loadUsersFromDatabase(): array
    {
        $pdo = Database::connection($this->rootDir);
        $rows = $pdo->query('SELECT * FROM admin_users ORDER BY username')->fetchAll();
        $users = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mapped = $this->mapDatabaseRow($row);
            if ($mapped !== null) {
                $users[] = $mapped;
            }
        }

        return $users;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: string, username: string, password_hash: string, created_at: string, disabled: bool, auth_version: int}|null
     */
    private function mapDatabaseRow(array $row): ?array
    {
        return $this->normalizeUser([
            'id' => $row['id'] ?? '',
            'username' => $row['username'] ?? '',
            'password_hash' => $row['password_hash'] ?? '',
            'created_at' => $row['created_at'] ?? '',
            'disabled' => !empty($row['disabled']),
            'auth_version' => (int) ($row['auth_version'] ?? 1),
        ]);
    }

    /**
     * @param array{id: string, username: string, password_hash: string, created_at: string, disabled: bool, auth_version: int} $user
     */
    private function insertUserToDatabase(array $user): void
    {
        $pdo = Database::connection($this->rootDir);
        $createdAt = $user['created_at'];
        try {
            $createdAt = (new DateTimeImmutable($createdAt))->format('Y-m-d H:i:s.u');
        } catch (Exception) {
            $createdAt = gmdate('Y-m-d H:i:s');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO admin_users (id, username, password_hash, created_at, disabled, auth_version)
             VALUES (:id, :username, :password_hash, :created_at, :disabled, :auth_version)'
        );
        $stmt->execute([
            'id' => $user['id'],
            'username' => $user['username'],
            'password_hash' => $user['password_hash'],
            'created_at' => $createdAt,
            'disabled' => !empty($user['disabled']) ? 1 : 0,
            'auth_version' => max(1, (int) $user['auth_version']),
        ]);
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

        $createdAt = trim((string) ($user['created_at'] ?? ''));
        if ($createdAt !== '') {
            try {
                $createdAt = (new DateTimeImmutable($createdAt))->format(DateTimeInterface::ATOM);
            } catch (Exception) {
                $createdAt = gmdate('c');
            }
        } else {
            $createdAt = gmdate('c');
        }

        return [
            'id' => $id,
            'username' => $username,
            'password_hash' => $hash,
            'created_at' => $createdAt,
            'disabled' => !empty($user['disabled']),
            'auth_version' => max(1, (int) ($user['auth_version'] ?? 1)),
        ];
    }

    /**
     * @param list<array{id: string, username: string, password_hash: string, created_at: string, disabled: bool, auth_version: int}> $users
     */
    private function writeUsersToFile(array $users): void
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

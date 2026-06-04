<?php

declare(strict_types=1);

final class SetupToken
{
    private string $tokenPath;

    public function __construct(?string $rootDir = null)
    {
        $this->tokenPath = ($rootDir ?? dirname(__DIR__)) . '/data/admin/setup.token';
    }

    public function isRequired(): bool
    {
        return is_file($this->tokenPath);
    }

    public function verify(?string $submitted): bool
    {
        if (!is_file($this->tokenPath)) {
            return false;
        }

        $expected = trim((string) @file_get_contents($this->tokenPath));
        $submitted = trim((string) $submitted);

        return $expected !== '' && $submitted !== '' && hash_equals($expected, $submitted);
    }

    public function consume(): void
    {
        if (is_file($this->tokenPath)) {
            @unlink($this->tokenPath);
        }
    }

    public static function tokenPathForDocs(?string $rootDir = null): string
    {
        return ($rootDir ?? dirname(__DIR__)) . '/data/admin/setup.token';
    }
}

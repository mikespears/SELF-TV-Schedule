<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);

require $rootDir . '/bootstrap.php';

$store = new ConfigStore($rootDir);
/** @var array<string, mixed> $config */
$config = $store->load();

require $rootDir . '/lib/LoginRateLimiter.php';
require $rootDir . '/lib/SetupToken.php';
require $rootDir . '/lib/AdminUserStore.php';
require $rootDir . '/lib/AdminAuth.php';
$auth = new AdminAuth($rootDir);
$setupToken = new SetupToken($rootDir);

$auth->startSession();
Security::sendSecurityHeaders();

/**
 * @param 'success'|'error'|'warning' $type
 */
function adminFlash(string $type, string $message): void
{
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

/** @return array{type: string, message: string}|null */
function adminConsumeFlash(): ?array
{
    if (empty($_SESSION['admin_flash'])) {
        return null;
    }

    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);

    return is_array($flash) ? $flash : null;
}

function adminRedirect(string $path, ?string $anchor = null): never
{
    $url = $path;
    if ($anchor !== null && $anchor !== '') {
        $url .= '#' . $anchor;
    }
    header('Location: ' . $url);
    exit;
}

function adminTestNowForInput(?string $testNow): string
{
    if ($testNow === null || $testNow === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($testNow);
    } catch (Exception) {
        return '';
    }

    return $dt->format('Y-m-d\TH:i');
}

function adminTestNowQuery(?string $testNow): string
{
    if ($testNow === null || $testNow === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($testNow);
    } catch (Exception) {
        return '';
    }

    return $dt->format('Y-m-d\TH:i');
}

<?php

declare(strict_types=1);

/**
 * @param array<string, string>|string|null $value
 */
function localize(mixed $value, string $locale = 'en'): string
{
    if ($value === null) {
        return '';
    }

    if (is_string($value)) {
        return $value;
    }

    if (!is_array($value)) {
        return (string) $value;
    }

    if (isset($value[$locale]) && $value[$locale] !== '') {
        return (string) $value[$locale];
    }

    foreach ($value as $text) {
        if ($text !== '') {
            return (string) $text;
        }
    }

    return '';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param array<string, mixed> $config
 */
function resolveReferenceNow(array $config): DateTimeImmutable
{
    $tz = new DateTimeZone((string) $config['timezone']);
    $realNow = new DateTimeImmutable('now', $tz);

    if (empty($config['allow_test_clock'])) {
        return $realNow;
    }

    $raw = null;
    if (isset($_GET['now']) && is_string($_GET['now']) && $_GET['now'] !== '') {
        $raw = $_GET['now'];
    } elseif (isset($config['test_now']) && is_string($config['test_now']) && $config['test_now'] !== '') {
        $raw = $config['test_now'];
    }

    if ($raw === null) {
        return $realNow;
    }

    // Date only (YYYY-MM-DD) → noon on that day for predictable "during the conference" views.
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
        $raw .= 'T12:00:00';
    }

    try {
        return new DateTimeImmutable($raw, $tz);
    } catch (Exception) {
        return $realNow;
    }
}

/**
 * @param array<string, mixed> $config
 */
function isTestClockActive(array $config, DateTimeImmutable $referenceNow): bool
{
    if (empty($config['allow_test_clock'])) {
        return false;
    }

    $realNow = new DateTimeImmutable('now', new DateTimeZone((string) $config['timezone']));

    return $referenceNow->format('Y-m-d H:i:s') !== $realNow->format('Y-m-d H:i:s');
}

function formatLastUpdated(?int $timestamp, string $timezone): string
{
    if ($timestamp === null) {
        return '';
    }

    $updated = (new DateTimeImmutable('@' . $timestamp))
        ->setTimezone(new DateTimeZone($timezone));

    return $updated->format('g:i A');
}

/**
 * @param array{now: ?array<string, mixed>, up_next: ?array<string, mixed>, today: list<array<string, mixed>>} $view
 */
function scheduleIdleHeroMessage(array $view): string
{
    if ($view['today'] === []) {
        return 'No sessions in this room today.';
    }

    return 'No sessions in progress right now.';
}

function logScheduleException(Throwable $exception): void
{
    error_log(
        'SELF Schedule Display: ' . $exception->getMessage()
        . ' in ' . $exception->getFile() . ':' . $exception->getLine()
    );
}

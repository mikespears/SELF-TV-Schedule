<?php

declare(strict_types=1);

final class ScheduleService
{
    private DateTimeZone $timezone;
    private string $locale;

    public function __construct(string $timezone, string $locale = 'en')
    {
        $this->timezone = new DateTimeZone($timezone);
        $this->locale = $locale;
    }

    /**
     * @param list<array<string, mixed>> $allSlots
     * @return list<array<string, mixed>>
     */
    public function slotsForRoom(array $allSlots, int $roomId): array
    {
        $filtered = array_values(array_filter(
            $allSlots,
            static fn (array $slot): bool => (int) ($slot['room']['id'] ?? 0) === $roomId
        ));

        return $filtered;
    }

    /**
     * @param list<array<string, mixed>> $roomSlots
     * @return array{
     *   now: ?array<string, mixed>,
     *   up_next: ?array<string, mixed>,
     *   today: list<array<string, mixed>>
     * }
     */
    public function buildRoomView(array $roomSlots, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', $this->timezone);
        $today = $now->format('Y-m-d');

        $todaySlots = array_values(array_filter(
            $roomSlots,
            fn (array $slot): bool => $this->slotDate($slot) === $today
        ));

        $current = null;
        $upNext = null;

        foreach ($todaySlots as $slot) {
            $start = $this->slotStart($slot);
            $end = $this->slotEnd($slot);

            if ($start === null || $end === null) {
                continue;
            }

            if ($now >= $start && $now < $end) {
                $current = $slot;
            } elseif ($start > $now && $upNext === null) {
                $upNext = $slot;
            }
        }

        if ($current === null && $upNext === null) {
            foreach ($todaySlots as $slot) {
                $start = $this->slotStart($slot);
                if ($start !== null && $start > $now) {
                    $upNext = $slot;
                    break;
                }
            }
        }

        return [
            'now' => $current,
            'up_next' => $upNext,
            'today' => $todaySlots,
        ];
    }

    /** @param array<string, mixed> $slot */
    public function slotTitle(array $slot): string
    {
        $submission = $slot['submission'] ?? null;
        if (is_array($submission) && isset($submission['title'])) {
            $title = localize($submission['title'], $this->locale);
            if ($title !== '') {
                return $title;
            }
        }

        return localize($slot['description'] ?? null, $this->locale);
    }

    /** @param array<string, mixed> $slot */
    public function slotDescription(array $slot): string
    {
        $submission = $slot['submission'] ?? null;
        if (!is_array($submission)) {
            return '';
        }

        foreach (['abstract', 'description'] as $field) {
            if (!isset($submission[$field])) {
                continue;
            }
            $text = localize($submission[$field], $this->locale);
            $text = trim(strip_tags($text));
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /** @param array<string, mixed> $slot */
    public function slotSpeakers(array $slot): string
    {
        $submission = $slot['submission'] ?? null;
        if (!is_array($submission)) {
            return '';
        }

        $speakers = $submission['speakers'] ?? [];
        if (!is_array($speakers) || $speakers === []) {
            return '';
        }

        $names = [];
        foreach ($speakers as $speaker) {
            if (!is_array($speaker)) {
                continue;
            }
            $name = (string) ($speaker['name'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return implode(', ', $names);
    }

    /** @param array<string, mixed> $slot */
    public function formatTimeRange(array $slot): string
    {
        $start = $this->slotStart($slot);
        $end = $this->slotEnd($slot);

        if ($start === null || $end === null) {
            return '';
        }

        return $start->format('g:i A') . ' – ' . $end->format('g:i A');
    }

    /** @param array<string, mixed> $slot */
    public function slotStatus(array $slot, DateTimeImmutable $now): string
    {
        $start = $this->slotStart($slot);
        $end = $this->slotEnd($slot);

        if ($start === null || $end === null) {
            return 'unknown';
        }

        if ($now >= $start && $now < $end) {
            return 'now';
        }

        if ($start > $now) {
            return 'upcoming';
        }

        return 'past';
    }

    /** @param array<string, mixed> $slot */
    private function slotDate(array $slot): ?string
    {
        $start = $this->slotStart($slot);

        return $start?->format('Y-m-d');
    }

    /** @param array<string, mixed> $slot */
    private function slotStart(array $slot): ?DateTimeImmutable
    {
        $raw = $slot['start'] ?? null;

        return is_string($raw) && $raw !== ''
            ? new DateTimeImmutable($raw)->setTimezone($this->timezone)
            : null;
    }

    /** @param array<string, mixed> $slot */
    private function slotEnd(array $slot): ?DateTimeImmutable
    {
        $raw = $slot['end'] ?? null;

        return is_string($raw) && $raw !== ''
            ? new DateTimeImmutable($raw)->setTimezone($this->timezone)
            : null;
    }
}

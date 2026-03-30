<?php

function is_legacy_subject_duration($storedValue): bool
{
    $minutes = (int) $storedValue;
    return $minutes > 0 && $minutes <= 20;
}

function normalize_subject_duration_minutes($storedValue): int
{
    $minutes = (int) $storedValue;
    if ($minutes <= 0) {
        return 0;
    }

    // Older records stored plain hours (1, 2, 3...). New records store minutes.
    if ($minutes <= 20) {
        return $minutes * 60;
    }

    return $minutes;
}

function split_subject_duration_minutes($storedValue): array
{
    $minutes = normalize_subject_duration_minutes($storedValue);

    return [
        "hours" => intdiv($minutes, 60),
        "minutes" => $minutes % 60,
        "total_minutes" => $minutes,
    ];
}

function format_subject_duration_minutes($storedValue): string
{
    $minutes = normalize_subject_duration_minutes($storedValue);
    if ($minutes <= 0) {
        return "0 min";
    }

    $parts = [];
    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    if ($hours > 0) {
        $parts[] = $hours . " hr" . ($hours === 1 ? "" : "s");
    }

    if ($remainingMinutes > 0) {
        $parts[] = $remainingMinutes . " min";
    }

    return implode(" ", $parts);
}

function weekly_subject_duration_minutes($storedValue): int
{
    $minutes = normalize_subject_duration_minutes($storedValue);

    // Older values were already stored as weekly hours.
    if (is_legacy_subject_duration($storedValue)) {
        return $minutes;
    }

    return $minutes * 5;
}

function minutes_between_times(?string $startTime, ?string $endTime): int
{
    if (!$startTime || !$endTime) {
        return 0;
    }

    $start =
        DateTime::createFromFormat("H:i:s", $startTime) ?:
        DateTime::createFromFormat("H:i", $startTime);
    $end =
        DateTime::createFromFormat("H:i:s", $endTime) ?:
        DateTime::createFromFormat("H:i", $endTime);

    if (!$start || !$end) {
        return 0;
    }

    $diffSeconds = $end->getTimestamp() - $start->getTimestamp();
    if ($diffSeconds <= 0) {
        return 0;
    }

    return (int) round($diffSeconds / 60);
}

function read_subject_duration_minutes_from_request(array $source): int
{
    if (
        isset($source["duration_hours"]) ||
        isset($source["duration_minutes"])
    ) {
        $hours = max(0, (int) ($source["duration_hours"] ?? 0));
        $minutes = max(0, (int) ($source["duration_minutes"] ?? 0));
        return $hours * 60 + $minutes;
    }

    return max(0, (int) ($source["hours_per_week"] ?? 0));
}

<?php

declare(strict_types=1);

namespace App\Reporting\Support;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class ReportingDateRange
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $toExclusive,
        public readonly string $timezone,
    ) {}

    public static function fromInput(?string $from, ?string $to, ?string $timezone = null): self
    {
        $timezone = $timezone ?: (string) config('app.timezone', 'UTC');
        try {
            $fromDate = CarbonImmutable::parse($from ?: now($timezone)->toDateString(), $timezone)->startOfDay();
            $toDate = CarbonImmutable::parse($to ?: $fromDate->toDateString(), $timezone)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => 'INVALID_REPORTING_DATE']);
        }

        if ($fromDate->greaterThan($toDate)) {
            throw ValidationException::withMessages(['date' => 'INVALID_REPORTING_DATE_RANGE']);
        }

        return new self($fromDate, $toDate->addDay(), $timezone);
    }

    public function contains(CarbonImmutable $timestamp): bool
    {
        return $timestamp->greaterThanOrEqualTo($this->from) && $timestamp->lessThan($this->toExclusive);
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->toExclusive->subDay()->toDateString(),
            'timezone' => $this->timezone,
            'from_timestamp' => $this->from->utc()->toIso8601String(),
            'to_exclusive_timestamp' => $this->toExclusive->utc()->toIso8601String(),
        ];
    }
}

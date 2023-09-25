<?php declare(strict_types=1);

namespace Lkrms\Support\DateParser;

use Lkrms\Contract\IDateParser;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Returns a DateTimeImmutable from a callback if a regular expression matches
 */
final class RegexDateParser implements IDateParser
{
    /**
     * @var string
     */
    private $Pattern;

    /**
     * @var callable
     */
    private $Callback;

    /**
     * @param callable $callback
     * ```php
     * fn(array $matches, ?DateTimeZone $timezone): DateTimeImmutable
     * ```
     */
    public function __construct(string $pattern, callable $callback)
    {
        $this->Pattern = $pattern;
        $this->Callback = $callback;
    }

    public function parse(string $value, ?DateTimeZone $timezone = null): ?DateTimeImmutable
    {
        if (preg_match($this->Pattern, $value, $matches)) {
            return ($this->Callback)($matches, $timezone);
        }

        return null;
    }

    public static function dotNet(): IDateParser
    {
        return new self(
            '/^\/Date\((?<seconds>[0-9]+)(?<milliseconds>[0-9]{3})(?<offset>[-+][0-9]{4})?\)\/$/',
            function (array $matches, ?DateTimeZone $timezone): DateTimeImmutable {
                $date = new DateTimeImmutable(
                    // PHP 7.4 requires 6 digits after the decimal point
                    sprintf(
                        '@%s.%s000',
                        $matches['seconds'],
                        $matches['milliseconds']
                    )
                );
                if (!$timezone && ($matches['offset'] ?? null)) {
                    $timezone = new DateTimeZone($matches['offset']);
                }

                return $timezone
                    ? $date->setTimezone($timezone)
                    : $date;
            }
        );
    }
}

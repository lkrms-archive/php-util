<?php declare(strict_types=1);

namespace Lkrms\Utility;

use DateTimeZone;
use Lkrms\Facade\Console;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * Work with .env files and environment variables
 *
 */
final class Environment
{
    /**
     * Load values into the environment from a file
     *
     * Values are loaded from a .env file to `getenv()`, `$_ENV` and `$_SERVER`.
     * Variables already present in the environment are never overwritten, but
     * if they appear in the .env file multiple times, the last value is used.
     *
     * Each line in `$filename` should be a shell-compatible variable
     * assignment. Unquoted values cannot contain whitespace, `"`, `'`, `$`,
     * backticks or glob characters. Double-quoted values cannot contain `"`,
     * `$`, or backticks unless they are escaped. Single-quoted values may
     * contain single quotes as long as they look like this: `'\''`. Lines
     * starting with `#` are ignored.
     *
     * @throws RuntimeException if `$filename` cannot be opened.
     * @throws UnexpectedValueException if `$filename` cannot be parsed.
     */
    public function loadFile(string $filename): void
    {
        $lines = file($filename, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException("Could not open $filename");
        }

        $queue = [];
        foreach ($lines as $i => $line) {
            $l = $i + 1;
            if (!trim($line) || substr($line, 0, 1) === '#') {
                continue;
            }
            if (!preg_match(
                '/^([A-Z_][A-Z0-9_]*)=("(([^"$`]|\\\\["$`])*)"|\'(([^\']|\'\\\\\'\')*)\'|[^]"$\'*?`\s[]*)$/i',
                $line,
                $match
            )) {
                throw new UnexpectedValueException("Invalid entry at line $l in $filename");
            }
            $name = $match[1];
            if (getenv($name) !== false ||
                    array_key_exists($name, $_ENV) ||
                    array_key_exists($name, $_SERVER)) {
                continue;
            }
            if ($match[3] ?? null) {
                $value = preg_replace('/\\\\(["$\`])/', '\1', $match[3]);
            } elseif ($match[5] ?? null) {
                $value = str_replace("'\\''", "'", $match[5]);
            } else {
                $value = $match[2];
            }
            $queue[$name] = $value;
        }
        foreach ($queue as $name => $value) {
            $this->set($name, $value);
        }
    }

    /**
     * Apply values from the environment to the running script
     *
     * Specifically:
     * - Set locale from `LC_ALL`, `LC_COLLATE`, `LC_CTYPE`, `LC_MONETARY`,
     *   `LC_NUMERIC`, `LC_TIME`, `LC_MESSAGES` and/or `LANG`
     * - If `TZ` is a valid timezone, pass it to `date_default_timezone_set`
     */
    public function apply(): void
    {
        if (setlocale(LC_ALL, '') === false) {
            Console::debug('Invalid locale');
        }

        if ($tz = preg_replace('/^:?(.*\/zoneinfo\/)?/', '', $this->get('TZ', ''))) {
            try {
                $timezone = new DateTimeZone($tz);
                date_default_timezone_set($timezone->getName());
            } catch (Throwable $ex) {
                Console::debug('Not a valid timezone:', $tz, $ex);
            }
        }
    }

    /**
     * Set an environment variable
     *
     * `$value` is loaded to `getenv()`, `$_ENV` and `$_SERVER`.
     *
     */
    public function set(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    /**
     * Unset an environment variable
     *
     * The value is removed from `getenv()`, `$_ENV` and `$_SERVER`.
     *
     */
    public function unset(string $name): void
    {
        putenv($name);
        unset($_ENV[$name]);
        unset($_SERVER[$name]);
    }

    /**
     * @return string|false
     */
    private function _get(string $name)
    {
        return $_ENV[$name]
            ?? $_SERVER[$name]
            ?? (($local = getenv($name, true)) !== false
                ? $local
                : getenv($name));
    }

    /**
     * True if a variable exists in the environment
     *
     */
    public function has(string $name): bool
    {
        return $this->_get($name) !== false;
    }

    /**
     * Get an environment variable
     *
     * Looks for `$name` in `$_ENV`, `$_SERVER` and `getenv()`, in that order,
     * and returns the first value it finds.
     *
     * Returns `$default` if `$name` is not found in the environment.
     *
     * @template T of string
     * @phpstan-param T|null $default
     * @phpstan-return T|string
     * @throws RuntimeException if `$name` is not set and `$default` is not
     * given.
     */
    public function get(string $name, ?string $default = null): ?string
    {
        $value = $this->_get($name);
        if ($value === false) {
            if (func_num_args() < 2) {
                throw new RuntimeException("Environment variable $name is not set");
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Get an integer value from the environment
     *
     * Returns `$default` if `$name` is empty or unset, otherwise casts it as an
     * `int` before returning.
     *
     * @template T of int
     * @phpstan-param T|null $default
     * @phpstan-return T|int
     * @throws RuntimeException if `$name` is not set and `$default` is not
     * given.
     */
    public function getInt(string $name, ?int $default = null): ?int
    {
        if (func_num_args() < 2) {
            $value = $this->get($name);
        } else {
            $value = $this->get($name, null);
        }
        if (trim((string) $value) === '') {
            return $default;
        }

        return (int) $value;
    }

    /**
     * Get a boolean value from the environment
     *
     * Returns `$default` if `$name` is empty or unset, `false` if it's `"n"`,
     * `"no"`, `"false"` or `"0"`, otherwise `true`. Comparison is not
     * case-sensitive.
     *
     * @template T of bool
     * @phpstan-param T|null $default
     * @phpstan-return T|bool
     * @throws RuntimeException if `$name` is not set and `$default` is not
     * given.
     */
    public function getBool(string $name, ?bool $default = null): ?bool
    {
        if (func_num_args() < 2) {
            $value = $this->get($name);
        } else {
            $value = $this->get($name, null);
        }
        if (trim((string) $value) === '') {
            return $default;
        }
        if (preg_match('/^(no?|false)/i', $value)) {
            return false;
        }

        return (bool) $value;
    }

    /**
     * Get a list of strings from the environment
     *
     * Returns `$default` if `$name` is not set or an empty array if it's empty,
     * otherwise splits it into an array on `$delimiter` before returning.
     *
     * @template T of string[]
     * @phpstan-param T|null $default
     * @phpstan-return T|string[]
     * @param string[]|null $default
     * @return string[]|null
     * @throws RuntimeException if `$name` is not set and `$default` is not
     * given.
     */
    public function getList(string $name, ?array $default = null, string $delimiter = ','): ?array
    {
        if (!$delimiter) {
            throw new UnexpectedValueException('Invalid delimiter');
        }

        if (func_num_args() < 2) {
            $value = $this->get($name);
        } else {
            $value = $this->get($name, null);

            if (is_null($value)) {
                return $default;
            }
        }

        return $value ? explode($delimiter, $value) : [];
    }

    private function getOrSetBool(string $name, ?bool $newState = null): bool
    {
        if (func_num_args() > 1 && !is_null($newState)) {
            if ($newState) {
                $this->set($name, '1');
            } else {
                $this->unset($name);
            }
        }

        return (bool) $this->get($name, '');
    }

    /**
     * Optionally turn dry-run mode on or off, then return its current state
     *
     * Dry-run mode can also be enabled by setting the `DRY_RUN` environment
     * variable.
     *
     */
    public function dryRun(?bool $newState = null): bool
    {
        return $this->getOrSetBool('DRY_RUN', ...func_get_args());
    }

    /**
     * Optionally turn debug mode on or off, then return its current state
     *
     * Debug mode can also be enabled by setting the `DEBUG` environment
     * variable.
     *
     */
    public function debug(?bool $newState = null): bool
    {
        return $this->getOrSetBool('DEBUG', ...func_get_args());
    }

    /**
     * True if the current locale for character classification and conversion
     * (LC_CTYPE) supports UTF-8
     *
     */
    public function isLocaleUtf8(): bool
    {
        if (($locale = setlocale(LC_CTYPE, '0')) === false) {
            Console::warnOnce('Invalid locale settings');

            return false;
        }

        return (bool) preg_match('/\.utf-?8$/i', $locale);
    }

    /**
     * Get the current user's home directory from the environment
     *
     */
    public function home(): ?string
    {
        if ($home = $this->get('HOME', null)) {
            return $home;
        }
        if (($homeDrive = $this->get('HOMEDRIVE', null)) &&
                ($homePath = $this->get('HOMEPATH', null))) {
            return $homeDrive . $homePath;
        }

        return null;
    }
}

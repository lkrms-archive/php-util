<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Support\Catalog\RegularExpression as Regex;

/**
 * Perform true/false tests on values
 */
final class Test
{
    /**
     * True if $value is a boolean or boolean string
     *
     * The following are regarded as boolean strings (case-insensitive):
     *
     * - `"0"`
     * - `"1"`
     * - `"disable"`
     * - `"disabled"`
     * - `"enable"`
     * - `"enabled"`
     * - `"f"`
     * - `"false"`
     * - `"n"`
     * - `"no"`
     * - `"off"`
     * - `"on"`
     * - `"t"`
     * - `"true"`
     * - `"y"`
     * - `"yes"`
     *
     * @param mixed $value
     */
    public static function isBoolValue($value): bool
    {
        return is_bool($value) ||
            (is_string($value) && Pcre::match('/^' . Regex::BOOLEAN_STRING . '$/', $value));
    }

    /**
     * True if $value is an integer or integer string
     *
     * @param mixed $value
     */
    public static function isIntValue($value): bool
    {
        return is_int($value) ||
            (is_string($value) && Pcre::match('/^' . Regex::INTEGER_STRING . '$/', $value));
    }

    /**
     * True if $value is a float or float string
     *
     * @param mixed $value
     */
    public static function isFloatValue($value): bool
    {
        return is_float($value) ||
            (is_string($value) && is_numeric($value) && !self::isIntValue($value));
    }

    /**
     * True if $value is an array with consecutive integer keys numbered from 0
     *
     * @param mixed $value
     */
    public static function isListArray($value, bool $allowEmpty = false): bool
    {
        return is_array($value) &&
            ($value
                ? array_keys($value) === range(0, count($value) - 1)
                : $allowEmpty);
    }

    /**
     * True if $value is an array with one or more string keys
     *
     * @param mixed $value
     */
    public static function isAssociativeArray($value, bool $allowEmpty = false): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if (!$value) {
            return $allowEmpty;
        }
        foreach (array_keys($value) as $key) {
            if (is_string($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True if $value is an array with no string keys
     *
     * @param mixed $value
     */
    public static function isIndexedArray($value, bool $allowEmpty = false): bool
    {
        return is_array($value) &&
            ($value
                ? !self::isAssociativeArray($value)
                : $allowEmpty);
    }

    /**
     * True if $value is a string[] or int[]
     *
     * @param mixed $value
     */
    public static function isArrayOfArrayKey($value, bool $allowEmpty = false): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if (!$value) {
            return $allowEmpty;
        }
        if (is_int($last = array_pop($value))) {
            foreach ($value as $item) {
                if (!is_int($item)) {
                    return false;
                }
            }

            return true;
        }
        if (is_string($last)) {
            foreach ($value as $item) {
                if (!is_string($item)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * True if $value is an int[]
     *
     * @param mixed $value
     */
    public static function isArrayOfInt($value, bool $allowEmpty = false): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if (!$value) {
            return $allowEmpty;
        }
        foreach ($value as $item) {
            if (!is_int($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * True if $value is a string[]
     *
     * @param mixed $value
     */
    public static function isArrayOfString($value, bool $allowEmpty = false): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if (!$value) {
            return $allowEmpty;
        }
        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * True if $value is an array filled exclusively with $itemValue
     *
     * @param mixed $value
     * @param mixed $itemValue
     */
    public static function isArrayOfValue($value, $itemValue, bool $strict = true, bool $allowEmpty = false): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if (!$value) {
            return $allowEmpty;
        }
        foreach ($value as $item) {
            if (($strict && $item !== $itemValue) ||
                    (!$strict && $item != $itemValue)) {
                return false;
            }
        }

        return true;
    }

    /**
     * True if $value is an array of $class instances
     *
     * @template T of object
     *
     * @param mixed $value
     * @param class-string<T> $class
     * @phpstan-assert-if-true T[] $value
     */
    public static function isArrayOf($value, string $class, bool $allowEmpty = false): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if (!$value) {
            return $allowEmpty;
        }
        foreach ($value as $item) {
            if (!is_a($item, $class)) {
                return false;
            }
        }

        return true;
    }

    /**
     * True if $value is a number within a range
     *
     * @template T of int|float
     * @param T $value
     * @param T $min
     * @param T $max
     */
    public static function isBetween($value, $min, $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    /**
     * True if an object or class implements an interface
     *
     * @param object|class-string $class
     * @param class-string $interface
     */
    public static function classImplements($class, string $interface): bool
    {
        return in_array($interface, class_implements($class) ?: [], true);
    }

    /**
     * True if $path starts with 'phar://'
     */
    public static function isPharUrl(string $path): bool
    {
        return count($split = explode('://', $path, 2)) === 2 &&
            $split[0] === 'phar';
    }

    /**
     * True if $path is an absolute path
     *
     * A string that starts with `/` (a forward slash), `\\` (two backslashes),
     * or `[a-z]:\` (a letter followed by a colon and backslash) is regarded as
     * an absolute path.
     */
    public static function isAbsolutePath(string $path): bool
    {
        return (bool) Pcre::match('/^(\/|\\\\\\\\|[a-z]:\\\\)/i', $path);
    }

    /**
     * True if two paths exist and refer to the same file
     */
    public static function areSameFile(string $path1, string $path2): bool
    {
        return file_exists($path1) && file_exists($path2) &&
            ($inode = fileinode($path1)) !== false &&
            fileinode($path2) === $inode;
    }

    /**
     * True if a directory exists and is writable, or doesn't exist but
     * can be created
     */
    public static function firstExistingDirectoryIsWritable(string $dir): bool
    {
        while (!file_exists($dir)) {
            $next = dirname($dir);
            if ($next === $dir) {
                break;
            }
            $dir = $next;
        }

        return is_dir($dir) && is_writable($dir);
    }

    /**
     * True if $value is a PHP reserved word
     *
     * @link https://www.php.net/manual/en/reserved.php
     */
    public static function isPhpReservedWord(string $value): bool
    {
        return in_array(strtolower($value), [
            'array',
            'bool',
            'callable',
            'enum',
            'false',
            'float',
            'int',
            'iterable',
            'mixed',
            'never',
            'null',
            'numeric',
            'object',
            'parent',
            'resource',
            'self',
            'static',
            'string',
            'true',
            'void',
        ]);
    }
}

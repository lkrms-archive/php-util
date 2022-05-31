<?php

declare(strict_types=1);

namespace Lkrms\Util;

use Closure;
use DateTimeInterface;
use DateTimeZone;
use Lkrms\Core\Utility;
use Lkrms\Support\DateFormatter;
use Stringable;
use UnexpectedValueException;

/**
 * Convert data from one type/format/structure to another
 *
 * Examples:
 * - normalise alphanumeric text
 * - convert a list array to a map array
 * - pluralise a singular noun
 * - extract a class name from a FQCN
 */
final class Convert extends Utility
{
    /**
     * "snake_case"
     */
    public const IDENTIFIER_CASE_SNAKE = 0;

    /**
     * "kebab-case"
     */
    public const IDENTIFIER_CASE_KEBAB = 1;

    /**
     * "PascalCase"
     */
    public const IDENTIFIER_CASE_PASCAL = 2;

    /**
     * "camelCase"
     */
    public const IDENTIFIER_CASE_CAMEL = 3;

    /**
     * If a variable isn't an indexed array, make it the first element of one
     *
     * @param mixed $value The variable being checked.
     * @return array Either `$value` or `[$value]`.
     */
    public static function toArray($value): array
    {
        return Test::isIndexedArray($value) ? $value : [$value];
    }

    /**
     * If a variable isn't a list, make it the first element of one
     *
     * @param mixed $value The variable being checked.
     * @return array Either `$value` or `[$value]`.
     */
    public static function toList($value): array
    {
        return Test::isListArray($value, true) ? $value : [$value];
    }

    /**
     * Convert a value to a DateTimeZone instance
     *
     * @param DateTimeZone|string|null $value
     * @return DateTimeZone|null
     */
    public static function toTimezone($value): ?DateTimeZone
    {
        if ($value instanceof DateTimeZone)
        {
            return $value;
        }
        elseif (is_null($value))
        {
            return null;
        }
        elseif (is_string($value))
        {
            return new DateTimeZone($value);
        }
        throw new UnexpectedValueException("Invalid timezone");
    }

    /**
     * If a variable is 'falsey', make it null
     *
     * @param mixed $value The variable being checked.
     * @return mixed Either `$value` or `null`.
     */
    public static function emptyToNull($value)
    {
        return !$value ? null : $value;
    }

    /**
     * Remove the namespace from a fully-qualified class name
     *
     * @param string $class
     * @return string
     */
    public static function classToBasename(string $class): string
    {
        return substr(strrchr("\\" . $class, "\\"), 1);
    }

    /**
     * Remove the class from a method name
     *
     * @param string $method
     * @return string
     */
    public static function methodToFunction(string $method): string
    {
        return preg_replace('/^.*?([a-z0-9_]*)$/i', '$1', $method);
    }

    /**
     * Create a map from a list
     *
     * For example, to map from each array's `id` to the array itself:
     *
     * ```php
     * $list = [
     *     ['id' => 32, 'name' => 'Greta'],
     *     ['id' => 71, 'name' => 'Terry'],
     * ];
     *
     * $map = Convert::listToMap($list, 'id');
     *
     * print_r($map);
     * ```
     *
     * ```
     * Array
     * (
     *     [32] => Array
     *         (
     *             [id] => 32
     *             [name] => Greta
     *         )
     *
     *     [71] => Array
     *         (
     *             [id] => 71
     *             [name] => Terry
     *         )
     *
     * )
     * ```
     *
     * @param array $list
     * @param string|Closure $key Either the index or property name to use when
     * retrieving keys from arrays and objects in `$list`, or a closure that
     * returns a key for each item in `$list`.
     * @return array
     */
    public static function listToMap(array $list, $key): array
    {
        if ($key instanceof Closure)
        {
            $callback = $key;
        }
        else
        {
            $callback = function ($item) use ($key)
            {
                if (is_array($item))
                {
                    return $item[$key];
                }
                elseif (is_object($item))
                {
                    return $item->$key;
                }
                else
                {
                    throw new UnexpectedValueException("Item is not an array or object");
                }
            };
        }

        return array_combine(
            array_map($callback, $list),
            $list
        );
    }

    /**
     * Remove zero-width values from an array before imploding it
     *
     * @param string $separator
     * @param array $array
     * @return string
     */
    public static function sparseToString(string $separator, array $array): string
    {
        return implode($separator, array_filter(
            $array,
            function ($value) { return strlen((string)$value) > 0; }
        ));
    }

    /**
     * Convert a scalar to a string
     *
     * @param mixed $value
     * @return string|false Returns `false` if `$value` is not a scalar
     */
    public static function scalarToString($value)
    {
        if (is_scalar($value))
        {
            return (string)$value;
        }
        else
        {
            return false;
        }
    }

    /**
     * If a number is 1, return $singular, otherwise return $plural
     *
     * @param int $number
     * @param string $singular
     * @param string|null $plural If `null`, `{$singular}s` will be used instead
     * @param bool $includeNumber Return `$number $noun` instead of `$noun`
     * @return string
     */
    public static function numberToNoun(int $number, string $singular, string $plural = null, bool $includeNumber = false): string
    {
        if ($number == 1)
        {
            $noun = $singular;
        }
        else
        {
            $noun = is_null($plural) ? $singular . "s" : $plural;
        }

        if ($includeNumber)
        {
            return "$number $noun";
        }

        return $noun;
    }

    /**
     * Return the plural of a singular noun
     *
     * @param string $noun
     * @return string
     */
    public static function nounToPlural(string $noun): string
    {
        if (preg_match('/(?:(sh?|ch|x|z|(?<!^phot)(?<!^pian)(?<!^hal)o)|([^aeiou]y)|(is)|(on))$/i', $noun, $matches))
        {
            if ($matches[1])
            {
                return $noun . "es";
            }
            elseif ($matches[2])
            {
                return substr_replace($noun, "ies", -1);
            }
            elseif ($matches[3])
            {
                return substr_replace($noun, "es", -2);
            }
            elseif ($matches[4])
            {
                return substr_replace($noun, "a", -2);
            }
        }

        return $noun . "s";
    }

    /**
     * Convert php.ini values like "128M" to bytes
     *
     * @param string $size From the PHP FAQ: "The available options are K (for
     * Kilobytes), M (for Megabytes) and G (for Gigabytes), and are all
     * case-insensitive."
     * @return int
     */
    public static function sizeToBytes(string $size): int
    {
        if (!preg_match('/^(.+?)([KMG]?)$/', strtoupper($size), $match) || !is_numeric($match[1]))
        {
            throw new UnexpectedValueException("Invalid shorthand: '$size'");
        }

        $power = ['' => 0, 'K' => 1, 'M' => 2, 'G' => 3];

        return (int)($match[1] * (1024 ** $power[$match[2]]));
    }

    /**
     * Convert the given strings and Stringables to an array of strings
     *
     * @param string|Stringable ...$value
     * @return string[]
     */
    public static function toStrings(...$value): array
    {
        return array_map(function ($string) { return (string)$string; }, $value);
    }

    /**
     * Perform the given case conversion
     *
     * @param string $text
     * @param int $case
     * @return string
     */
    public static function toCase(string $text, int $case = self::IDENTIFIER_CASE_SNAKE): string
    {
        switch ($case)
        {
            case self::IDENTIFIER_CASE_SNAKE:

                return self::toSnakeCase($text);

            case self::IDENTIFIER_CASE_KEBAB:

                return self::toKebabCase($text);

            case self::IDENTIFIER_CASE_PASCAL:

                return self::toPascalCase($text);

            case self::IDENTIFIER_CASE_CAMEL:

                return self::toCamelCase($text);
        }

        throw new UnexpectedValueException("Invalid case: $case");
    }

    /**
     * Convert an identifier to snake_case
     *
     * @param string $text The identifier to convert.
     * @return string
     */
    public static function toSnakeCase(string $text): string
    {
        $text = preg_replace("/[^[:alnum:]]+/", "_", $text);
        $text = preg_replace("/([[:lower:]])([[:upper:]])/", '$1_$2', $text);

        return strtolower(trim($text, "_"));
    }

    /**
     * Convert an identifier to kebab-case
     *
     * @param string $text
     * @return string
     */
    public static function toKebabCase(string $text): string
    {
        $text = preg_replace("/[^[:alnum:]]+/", "-", $text);
        $text = preg_replace("/([[:lower:]])([[:upper:]])/", '$1-$2', $text);

        return strtolower(trim($text, "-"));
    }

    /**
     * Convert an identifier to PascalCase
     *
     * @param string $text
     * @return string
     */
    public static function toPascalCase(string $text): string
    {
        $text = preg_replace_callback(
            '/([[:upper:]]?[[:lower:][:digit:]]+|([[:upper:]](?![[:lower:]]))+)/',
            function (array $matches) { return ucfirst(strtolower($matches[0])); },
            $text
        );

        return preg_replace("/[^[:alnum:]]+/", "", $text);
    }

    /**
     * Convert an identifier to camelCase
     *
     * @param string $text
     * @return string
     */
    public static function toCamelCase(string $text): string
    {
        return lcfirst(self::toPascalCase($text));
    }

    /**
     * Clean up a string for comparison with other strings
     *
     * This method is not guaranteed to be idempotent between releases.
     *
     * Here's what it currently does:
     * 1. Replaces ampersands (`&`) with ` and `
     * 2. Removes full stops (`.`)
     * 3. Replaces non-alphanumeric sequences with a space (` `)
     * 4. Trims leading and trailing spaces
     * 5. Makes letters uppercase
     *
     * @param string $text
     * @return string
     */
    public static function toNormal(string $text)
    {
        $replace = [
            "/(?<=[^&])&(?=[^&])/u" => " and ",
            "/\.+/u"           => "",
            "/[^[:alnum:]]+/u" => " ",
        ];

        return strtoupper(trim(preg_replace(
            array_keys($replace),
            array_values($replace),
            $text
        )));
    }

    /**
     * A wrapper for get_object_vars
     *
     * Because you can't exclude `private` and `protected` properties from
     * inside the class. (Not easily, anyway.)
     *
     * @param object $object
     * @return array
     */
    public static function objectToArray(object $object)
    {
        return get_object_vars($object);
    }

    private static function _dataToQuery(
        array $data,
        bool $forceNumericKeys,
        DateFormatter $dateFormatter,
        string & $query = null,
        string $name    = "",
        string $format  = "%s"
    ): string
    {
        if (is_null($query))
        {
            $query = "";
        }

        foreach ($data as $param => $value)
        {
            $_name = sprintf($format, $param);

            if (!is_array($value))
            {
                if (is_bool($value))
                {
                    $value = (int)$value;
                }
                elseif ($value instanceof DateTimeInterface)
                {
                    $value = $dateFormatter->format($value);
                }

                $query .= ($query ? "&" : "") . urlencode($name . $_name) . "=" . urlencode((string)$value);

                continue;
            }
            elseif (!$forceNumericKeys && Test::isListArray($value, true))
            {
                $_format = "[]";
            }
            else
            {
                $_format = "[%s]";
            }

            self::_dataToQuery($value, $forceNumericKeys, $dateFormatter, $query, $name . $_name, $_format);
        }

        return $query;
    }

    /**
     * A more API-friendly http_build_query
     *
     * Booleans are cast to integers (`0` or `1`), {@see DateTime}s are
     * formatted by `$dateFormatter`, and other values are cast to string.
     *
     * Arrays with consecutive integer keys numbered from 0 are considered to be
     * lists. By default, keys are not included when adding lists to query
     * strings. Set `$forceNumericKeys` to override this behaviour.
     *
     * @param array $data
     * @param bool $forceNumericKeys
     * @param DateFormatter|null $dateFormatter
     * @return string
     */
    public static function dataToQuery(
        array $data,
        bool $forceNumericKeys       = false,
        DateFormatter $dateFormatter = null
    ): string
    {
        return self::_dataToQuery(
            $data,
            $forceNumericKeys,
            $dateFormatter ?: new DateFormatter()
        );
    }
}
